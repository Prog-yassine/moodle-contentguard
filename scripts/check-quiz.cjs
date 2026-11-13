#!/usr/bin/env node
/* Exercise the native Moodle quiz through its student interface. GPL-3.0-or-later. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium, devices } = require('playwright');

const root = path.resolve(__dirname, '..');
const env = Object.fromEntries(fs.readFileSync(path.join(root, '.env'), 'utf8')
    .split(/\r?\n/).filter(line => line && !line.startsWith('#')).map(line => {
        const index = line.indexOf('=');
        return [line.slice(0, index), line.slice(index + 1)];
    }));
const quiz = JSON.parse(fs.readFileSync(path.join(root, 'runtime/quiz.json'), 'utf8'));
const base = env.MOODLE_WWWROOT;
const quizurl = quiz.quizurl || new URL('/mod/quiz/view.php?id=' + quiz.quizcmid, base).href;
const artifacts = path.join(root, 'artifacts');
fs.mkdirSync(artifacts, { recursive: true });
const result = { started: new Date().toISOString(), base, quizurl, checks: [], attempts: [], pageErrors: [] };
const secrets = [env.CONTENTGUARD_STUDENT_PASSWORD, env.MOODLE_ADMIN_PASSWORD, env.MOODLE_DB_PASSWORD].filter(Boolean);
const redact = value => secrets.reduce((output, secret) => output.split(secret).join('[redacted]'), String(value));

async function check(name, operation) {
    try {
        const details = await operation();
        result.checks.push({ name, passed: true, ...(details === undefined ? {} : { details }) });
        console.log('PASS ' + name);
        return details;
    } catch (error) {
        result.checks.push({ name, passed: false, error: redact(error.message) });
        throw error;
    }
}

async function eventCancelled(page, selector, type, parameters = {}) {
    return page.evaluate(({ selector, type, parameters }) => {
        const target = document.querySelector(selector);
        if (!target) throw new Error('Missing event target: ' + selector);
        const options = { bubbles: true, cancelable: true, ...parameters };
        const event = type === 'keydown' ? new KeyboardEvent(type, options) : new Event(type, options);
        target.dispatchEvent(event);
        return event.defaultPrevented;
    }, { selector, type, parameters });
}

async function login(page) {
    // Open the intended native quiz first so Moodle returns there after signing in.
    await page.goto(quizurl, { waitUntil: 'networkidle' });
    await page.locator('#username').fill('etudiant');
    await page.locator('#password').fill(env.CONTENTGUARD_STUDENT_PASSWORD);
    await Promise.all([
        page.waitForURL(url => !url.pathname.includes('/login/index.php')),
        page.locator('#loginbtn').click(),
    ]);
    if (!page.url().includes('/mod/quiz/view.php')) {
        await page.goto(quizurl, { waitUntil: 'networkidle' });
    }
    await page.waitForLoadState('networkidle');
}

async function beginAttempt(page) {
    await page.locator('.quizstartbuttondiv [type="submit"]').first().click();
    // The seed has no password, time limit or preflight rule, so Moodle opens the attempt directly.
    await page.waitForURL(url => url.pathname.endsWith('/mod/quiz/attempt.php'));
    await page.locator('#responseform').waitFor();
    await page.locator('#region-main .contentguard-watermark').waitFor();
    await page.waitForLoadState('networkidle');
}

async function centredCheck(locator) {
    await locator.evaluate(element => element.scrollIntoView({block: 'center', inline: 'center', behavior: 'instant'}));
    await locator.page().waitForTimeout(300);
    await locator.check({timeout: 15000});
}
async function answerQuestions(page) {
    const choices = page.locator('#responseform .que.multichoice');
    assert.equal(await choices.count(), 2, 'Expected two single-choice questions.');
    await centredCheck(choices.nth(0).getByRole('radio', { name: /HTML/ }));
    await centredCheck(choices.nth(1).getByRole('radio', { name: /Un disque SSD/ }));
    await centredCheck(page.locator('#responseform .que.truefalse input[type="radio"][value="1"]'));
    await page.locator('#responseform .que.shortanswer input[type="text"]').fill('HTTPS');
    assert.equal(await page.locator('#responseform input[type="radio"]:checked').count(), 3);
    assert.equal(await page.locator('#responseform .que.shortanswer input[type="text"]').inputValue(), 'HTTPS');
}

async function screenshotAttempt(page, name) {
    // Collapse any native course drawer before capturing the ordinary question form.
    const closeButtons = page.locator('.drawer.show button[data-action="closedrawer"], .drawer.show .drawertoggle');
    for (let index = 0; index < await closeButtons.count(); index++) {
        const button = closeButtons.nth(index);
        if (await button.isVisible()) await button.click();
    }
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: path.join(artifacts, 'quiz-' + name + '.png'), fullPage: name !== 'mobile' });
}

async function finishAttempt(page) {
    await Promise.all([
        page.waitForURL(url => url.pathname.endsWith('/mod/quiz/summary.php')),
        page.locator('#mod_quiz-next-nav').click(),
    ]);
    await page.waitForLoadState('networkidle');
    await page.locator('.btn-finishattempt button').click();
    const confirmation = page.locator('.modal.show [data-action="save"]');
    await confirmation.waitFor();
    await Promise.all([
        page.waitForURL(url => url.pathname.endsWith('/mod/quiz/review.php')),
        confirmation.click(),
    ]);
    const summary = page.locator('.quizreviewsummary');
    await summary.waitFor();
    const summaryText = await summary.innerText();
    assert.match(summaryText, /Termin[ée]|Finished/i, 'Attempt must be submitted, not left in progress.');
    assert.match(summaryText, /10(?:[,.]0+)?\s*(?:sur|\/|out of)\s*10/i,
        'Correct answers should receive the configured grade of 10/10.');
    return { attemptid: new URL(page.url()).searchParams.get('attempt'), reviewurl: page.url(), summary: summaryText };
}

async function runContext(browser, name, contextOptions) {
    const context = await browser.newContext(contextOptions);
    if (process.env.CONTENTGUARD_LOCAL_ROUTE === '1') {
        await context.route(base + '/**', async route => {
            const source = new URL(route.request().url());
            const response = await route.fetch({url: 'http://localhost:8080' + source.pathname + source.search,
                headers: {...route.request().headers(), host: source.host}, maxRedirects: 0});
            await route.fulfill({response});
        });
    }
    const page = await context.newPage();
    page.setDefaultTimeout(60000);
    page.setDefaultNavigationTimeout(60000);
    page.on('pageerror', error => result.pageErrors.push({ mode: name, error: redact(error.message) }));
    try {
        await check(name + ': fresh student login', () => login(page));
        await check(name + ': ordinary quiz on Classic theme', async () => {
            assert.equal(await page.locator('.contentguard-lab').count(), 0);
            assert.equal(await page.evaluate(() => window.M && M.cfg && M.cfg.theme), 'classic');
            assert.ok(await page.locator('.quizstartbuttondiv').count());
            if (process.env.CONTENTGUARD_TEST_AI === '1') assert.equal(await page.locator('.contentguard-ai-label').count(), 0);
        });
        await check(name + ': native attempt opens with four questions and watermark', async () => {
            await beginAttempt(page);
            assert.equal(await page.locator('#responseform .que').count(), 4);
            assert.equal(await page.locator('#region-main .contentguard-watermark').count(), 1);
        });
        if (process.env.CONTENTGUARD_TEST_AI === '1') {
            await check(name + ': every question carries a visible AI assessment notice and repeated watermark', async () => {
                assert.equal(await page.locator('.contentguard-ai-label').count(), 4);
                assert.equal(await page.locator('.contentguard-ai-watermark').count(), 4);
                for (const label of await page.locator('.contentguard-ai-label').all()) {
                    assert.ok(await label.isVisible());
                    assert.match(await label.innerText(), /AI: study guidance only; no direct answers/);
                }
                const tile = await page.locator('.contentguard-ai-watermark').first().evaluate(e => ({image:getComputedStyle(e).backgroundImage,pointer:getComputedStyle(e).pointerEvents}));
                assert.equal(tile.pointer, 'none');
                assert.match(decodeURIComponent(tile.image), /AI: study guidance only/);
                assert.match(decodeURIComponent(tile.image), /CG-[A-F0-9]{10}/);
            });
        }
        await check(name + ': copy restriction applies to question text', async () => {
            assert.equal(await eventCancelled(page, '#responseform .qtext', 'copy'), true);
        });
        await check(name + ': answer fields preserve their keyboard and copy actions', async () => {
            assert.equal(await eventCancelled(page, '#responseform input[type="radio"]', 'keydown', { key: 'F12' }), false);
            assert.equal(await eventCancelled(page, '#responseform .que.shortanswer input[type="text"]', 'copy'), false);
            assert.equal(await eventCancelled(page, '#responseform .que.shortanswer input[type="text"]', 'keydown',
                { key: 'c', ctrlKey: true }), false);
        });
        await check(name + ': all native answers can be selected and entered', () => answerQuestions(page));
        await check(name + ': layout fits and screenshot is captured', async () => {
            const sizes = await page.evaluate(() => ({
                viewport: document.documentElement.clientWidth,
                content: document.documentElement.scrollWidth,
            }));
            const formBox = await page.locator('#responseform').boundingBox();
            assert.ok(formBox.width <= sizes.viewport + 1, 'The visible question form must fit the viewport.');
            await screenshotAttempt(page, name);
            return sizes;
        });
        const attempt = await check(name + ': normal submission produces a finished attempt and grade 10/10',
            () => finishAttempt(page));
        result.attempts.push({ mode: name, ...attempt });
        if (process.env.CONTENTGUARD_TEST_AI === '1') await check(name + ': finished review is not labelled an assessment in progress', async () => assert.equal(await page.locator('.contentguard-ai-label').count(), 0));
    } catch (error) {
        await page.screenshot({ path: path.join(artifacts, 'quiz-' + name + '-failure.png'), fullPage: true }).catch(() => {});
        throw error;
    } finally {
        await context.close();
    }
}

(async () => {
    const browser = await chromium.launch({
        executablePath: process.env.CONTENTGUARD_CHROMIUM || '/usr/bin/chromium',
        headless: true, args: ['--no-sandbox'],
        proxy: process.env.CONTENTGUARD_LOCAL_ROUTE !== '1' && base.startsWith('https://') && process.env.HTTPS_PROXY ? {
            server: process.env.HTTPS_PROXY, bypass: 'localhost,127.0.0.1',
        } : undefined,
    });
    try {
        const { defaultBrowserType, ...phone } = devices['iPhone 13'];
        await runContext(browser, 'mobile', phone);
        await runContext(browser, 'desktop', { viewport: { width: 1440, height: 1100 } });
        await check('no unhandled browser JavaScript errors', async () => assert.deepEqual(result.pageErrors, []));
    } finally {
        await browser.close();
    }
})().catch(error => {
    result.fatal = redact(error.message);
    console.error('FAIL ' + result.fatal);
}).finally(() => {
    result.finished = new Date().toISOString();
    result.passed = !result.fatal && result.checks.every(check => check.passed);
    fs.writeFileSync(path.join(artifacts, 'quiz-results.json'), JSON.stringify(result, null, 2) + '\n');
    console.log(`${result.checks.filter(check => check.passed).length}/${result.checks.length} checks passed.`);
    process.exitCode = result.passed ? 0 : 1;
});
