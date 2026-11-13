#!/usr/bin/env node
/* ContentGuard integration checks against a real Moodle installation. GPL-3.0-or-later. */
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const project = path.resolve(__dirname, '..');
const env = {};
for (const line of fs.readFileSync(path.join(project, '.env'), 'utf8').split(/\r?\n/)) {
    const match = line.match(/^\s*([A-Z_][A-Z0-9_]*)\s*=\s*(.*?)\s*$/);
    if (!match) continue;
    let value = match[2];
    if ((value.startsWith('"') && value.endsWith('"')) ||
        (value.startsWith("'") && value.endsWith("'"))) value = value.slice(1, -1);
    env[match[1]] = value;
}
const base = process.env.CONTENTGUARD_TEST_URL || env.MOODLE_WWWROOT || 'http://localhost:8080';
const studentPassword = env.CONTENTGUARD_STUDENT_PASSWORD;
const adminPassword = env.MOODLE_ADMIN_PASSWORD;
const courseid = Number(process.env.CONTENTGUARD_TEST_COURSEID || 2);
const artifacts = path.join(project, 'artifacts');
fs.mkdirSync(artifacts, { recursive: true });
const result = { started: new Date().toISOString(), base, courseid, checks: [], pageErrors: [] };
const secrets = [studentPassword, adminPassword, env.MOODLE_DB_PASSWORD].filter(Boolean);
const redact = (value) => secrets.reduce((text, secret) => text.split(secret).join('[redacted]'), String(value));
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function check(name, run) {
    try {
        const detail = await run();
        result.checks.push({ name, passed: true, ...(detail === undefined ? {} : { detail }) });
        console.log('PASS ' + name);
    } catch (error) {
        result.checks.push({ name, passed: false, error: redact(error.message) });
        console.error('FAIL ' + name + ': ' + redact(error.message));
    }
}

async function ready() {
    // Keep each poll short; bootstrap/seed must finish before the student can sign in.
    for (let attempt = 0; attempt < 15; attempt++) {
        try {
            const response = await fetch(new URL('/login/index.php', base), {
                signal: AbortSignal.timeout(3000),
            });
            const body = await response.text();
            if (response.ok && /id="username"/.test(body) && /id="password"/.test(body)) return;
        } catch (_) { /* An installation in progress can briefly return an error. */ }
        await delay(1500);
    }
    throw new Error('Moodle login is not ready. Finish bootstrap and seed before running these checks.');
}

async function login(page, username, password) {
    // Wait for Moodle's asynchronous login-form initialisation before filling it.
    await page.goto(new URL('/login/index.php', base).href, { waitUntil: 'networkidle' });
    await page.locator('#username').fill(username);
    await page.locator('#password').fill(password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/login/index.php'), { timeout: 30000 }),
        page.locator('#loginbtn').click(),
    ]);
}

async function openDemo(page) {
    await page.goto(new URL(`/local/contentguard/demo.php?courseid=${courseid}`, base).href,
        { waitUntil: 'domcontentloaded' });
    await page.locator('#local-contentguard-demo[data-contentguard-initialised="1"]').waitFor();
    await page.locator('#local-contentguard-demo .contentguard-watermark').waitFor({ state: 'visible' });
    await page.evaluate(() => document.fonts.ready);
}

async function eventCancelled(page, selector, type, properties = {}) {
    return page.evaluate(({ selector, type, properties }) => {
        const element = document.querySelector(selector);
        if (!element) throw new Error('Missing test target: ' + selector);
        const event = type === 'keydown' ? new KeyboardEvent(type, {
            bubbles: true, cancelable: true, ...properties,
        }) : new Event(type, { bubbles: true, cancelable: true });
        element.dispatchEvent(event);
        return event.defaultPrevented;
    }, { selector, type, properties });
}

async function fitViewport(page) {
    const dimensions = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        content: document.documentElement.scrollWidth,
        article: document.querySelector('#local-contentguard-demo').getBoundingClientRect().toJSON(),
    }));
    assert.ok(dimensions.content <= dimensions.viewport + 1,
        `Horizontal overflow: content ${dimensions.content}px, viewport ${dimensions.viewport}px`);
    assert.ok(dimensions.article.width > 100, 'Protected article must remain readable.');
    return dimensions;
}

async function prepareScreenshot(page) {
    // Close the ordinary Moodle navigation drawer through its own button so the
    // mobile lesson is visible, then reset scroll before capturing fixed bars.
    for (let attempt = 0; attempt < 3; attempt++) {
        const close = page.locator('button[data-action="closedrawer"]:visible').first();
        if (!await close.count()) break;
        await close.click();
        await page.waitForTimeout(350);
    }
    await page.evaluate(() => {
        window.scrollTo(0, 0);
        for (const id of ['page', 'page-wrapper']) {
            const element = document.getElementById(id);
            if (element) element.scrollTop = 0;
        }
    });
    await page.waitForTimeout(350);
}

async function setEnabled(admin, enabled) {
    await admin.goto(new URL('/admin/settings.php?section=local_contentguard', base).href,
        { waitUntil: 'networkidle' });
    await admin.locator('input[type="checkbox"][name="s_local_contentguard_enabled"]').setChecked(enabled);
    await Promise.all([
        admin.waitForNavigation({ waitUntil: 'networkidle' }),
        admin.locator('#adminsettings button[type="submit"], #adminsettings input[type="submit"]').first().click(),
    ]);
}

async function main() {
    assert.ok(studentPassword && adminPassword, 'Missing demo credentials in project .env');
    await ready();
    const browser = await chromium.launch({
        executablePath: process.env.CONTENTGUARD_CHROMIUM || '/usr/bin/chromium',
        headless: true,
        args: ['--no-sandbox'],
        proxy: base.startsWith('https://') && process.env.HTTPS_PROXY ? {
            server: process.env.HTTPS_PROXY,
            bypass: 'localhost,127.0.0.1',
        } : undefined,
    });
    try {
        const studentContext = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
        const student = await studentContext.newPage();
        student.on('pageerror', (error) => result.pageErrors.push({ account: 'student', error: redact(error.message) }));
        await check('login page has no content protection', async () => {
            await student.goto(new URL('/login/index.php', base).href, { waitUntil: 'networkidle' });
            assert.equal(await student.locator('.contentguard-watermark').count(), 0);
        });
        await check('student login', () => login(student, 'etudiant', studentPassword));
        await openDemo(student);
        await check('watermark is visible and contains a pseudonym, not an email', async () => {
            const style = await student.locator('.contentguard-watermark').evaluate((element) => {
                const computed = getComputedStyle(element);
                return { opacity: Number(computed.opacity), image: computed.backgroundImage,
                    pointerEvents: computed.pointerEvents, ariaHidden: element.getAttribute('aria-hidden') };
            });
            assert.ok(style.opacity >= 0.05 && style.opacity <= 0.25);
            assert.ok(style.image.includes('data:image/svg+xml'));
            assert.ok(decodeURIComponent(style.image).match(/CG-[A-F0-9]{10}/));
            assert.ok(!style.image.includes('@'));
            assert.equal(style.pointerEvents, 'none');
            assert.equal(style.ariaHidden, 'true');
            return { opacity: style.opacity, pointerEvents: style.pointerEvents };
        });
        for (const type of ['copy', 'contextmenu']) {
            await check(type + ' is restricted on reading content', async () => {
                assert.equal(await eventCancelled(student, '#local-contentguard-demo p', type), true);
            });
        }
        for (const properties of [{ key: 'F12' }, { key: 'I', ctrlKey: true, shiftKey: true },
            { key: 'I', metaKey: true, altKey: true }, { key: 'c', ctrlKey: true }]) {
            await check('reading shortcut restricted: ' + JSON.stringify(properties), async () => {
                assert.equal(await eventCancelled(student, '#local-contentguard-demo', 'keydown', properties), true);
            });
        }
        for (const type of ['copy', 'contextmenu']) {
            await check(type + ' remains available in textarea', async () => {
                assert.equal(await eventCancelled(student, '#cg-answer', type), false);
            });
        }
        for (const properties of [{ key: 'c', ctrlKey: true }, { key: 'v', ctrlKey: true },
            { key: 'a', ctrlKey: true }, { key: 'F12' }, { key: 'I', ctrlKey: true, shiftKey: true }]) {
            await check('textarea shortcut remains available: ' + JSON.stringify(properties), async () => {
                assert.equal(await eventCancelled(student, '#cg-answer', 'keydown', properties), false);
            });
        }
        for (const properties of [{ key: 'f', ctrlKey: true }, { key: 'a', ctrlKey: true },
            { key: 'p', ctrlKey: true }, { key: 'Tab' }, { key: 'ArrowDown' }]) {
            await check('reading navigation remains available: ' + JSON.stringify(properties), async () => {
                assert.equal(await eventCancelled(student, '#local-contentguard-demo', 'keydown', properties), false);
            });
        }
        await check('textarea supports real keyboard input', async () => {
            await student.locator('#cg-answer').fill('Ma réponse reste modifiable.');
            await student.locator('#cg-answer').press('End');
            await student.keyboard.type(' Test clavier.');
            assert.equal(await student.locator('#cg-answer').inputValue(), 'Ma réponse reste modifiable. Test clavier.');
        });
        await check('editable fields inside a protected area remain exempt', async () => {
            // Quiz responses live inside #region-main; an outer demo textarea alone
            // would not exercise that important exemption.
            await student.evaluate(() => {
                const field = document.createElement('textarea');
                field.id = 'contentguard-nested-test';
                document.querySelector('#local-contentguard-demo').appendChild(field);
            });
            try {
                for (const type of ['copy', 'contextmenu']) {
                    assert.equal(await eventCancelled(student, '#contentguard-nested-test', type), false);
                }
                assert.equal(await eventCancelled(student, '#contentguard-nested-test', 'keydown',
                    { key: 'c', ctrlKey: true }), false);
            } finally {
                await student.locator('#contentguard-nested-test').evaluate((element) => element.remove());
            }
        });
        await check('desktop layout fits the viewport and screenshot retains watermark', async () => {
            await student.waitForTimeout(2800); // Let the live-region toast disappear before screenshots.
            await prepareScreenshot(student);
            const dimensions = await fitViewport(student);
            await student.screenshot({ path: path.join(artifacts, 'demo-desktop.png'), fullPage: true });
            return dimensions;
        });
        await check('mobile layout fits the viewport and screenshot retains watermark', async () => {
            await student.setViewportSize({ width: 390, height: 844 });
            await prepareScreenshot(student);
            const dimensions = await fitViewport(student);
            await student.screenshot({ path: path.join(artifacts, 'demo-mobile.png'), fullPage: true });
            return dimensions;
        });
        await check('student ordinary course page is protected', async () => {
            await student.goto(new URL(`/course/view.php?id=${courseid}`, base).href, { waitUntil: 'networkidle' });
            await student.locator('#region-main .contentguard-watermark').waitFor();
        });

        const adminContext = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
        const admin = await adminContext.newPage();
        admin.on('pageerror', (error) => result.pageErrors.push({ account: 'admin', error: redact(error.message) }));
        await check('administrator login', () => login(admin, 'admin', adminPassword));
        await check('administrator course page bypasses protection', async () => {
            await admin.goto(new URL(`/course/view.php?id=${courseid}`, base).href, { waitUntil: 'networkidle' });
            assert.equal(await admin.locator('.contentguard-watermark').count(), 0);
        });
        await check('administration settings page has no content protection', async () => {
            await admin.goto(new URL('/admin/settings.php?section=local_contentguard', base).href,
                { waitUntil: 'networkidle' });
            assert.equal(await admin.locator('.contentguard-watermark').count(), 0);
            assert.ok(await admin.locator('input[name="s_local_contentguard_enabled"]').count());
        });
        await check('administrator can disable and restore protection through settings', async () => {
            const enabled = admin.locator('input[type="checkbox"][name="s_local_contentguard_enabled"]');
            const original = await enabled.isChecked();
            assert.equal(original, true, 'Demo starts with protection enabled.');
            try {
                await setEnabled(admin, false);
                await student.goto(new URL(`/local/contentguard/demo.php?courseid=${courseid}`, base).href,
                    { waitUntil: 'networkidle' });
                await student.locator('.contentguard-lab[data-contentguard-active="0"]').waitFor();
                assert.equal(await student.locator('.contentguard-watermark').count(), 0);
                assert.equal(await eventCancelled(student, '#local-contentguard-demo p', 'copy'), false);
            } finally {
                await setEnabled(admin, original);
            }
            await openDemo(student);
            assert.equal(await eventCancelled(student, '#local-contentguard-demo p', 'copy'), true);
        });
        await check('administrator can explicitly test protection on demo page', async () => {
            await openDemo(admin);
            assert.equal(await eventCancelled(admin, '#local-contentguard-demo p', 'copy'), true);
        });
        await check('no unhandled browser JavaScript errors', async () => {
            assert.deepEqual(result.pageErrors, []);
        });
    } finally {
        await browser.close();
    }
}

main().catch((error) => {
    result.fatal = redact(error.message);
    console.error('FATAL ' + result.fatal);
}).finally(() => {
    result.finished = new Date().toISOString();
    result.passed = !result.fatal && result.checks.every((item) => item.passed);
    fs.writeFileSync(path.join(artifacts, 'browser-results.json'), JSON.stringify(result, null, 2) + '\n');
    console.log(`${result.checks.filter((item) => item.passed).length}/${result.checks.length} checks passed.`);
    process.exitCode = result.passed ? 0 : 1;
});
