#!/usr/bin/env node
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium, devices} = require('playwright');
const root = path.resolve(__dirname, '..');
const env = Object.fromEntries(fs.readFileSync(path.join(root, '.env'), 'utf8')
    .split(/\r?\n/).filter(line => line && !line.startsWith('#')).map(line => {
        const index = line.indexOf('=');
        return [line.slice(0, index), line.slice(index + 1)];
    }));
(async () => {
    const browser = await chromium.launch({executablePath: '/usr/bin/chromium', headless: true,
        args: ['--no-sandbox'], proxy: process.env.HTTPS_PROXY ? {
            server: process.env.HTTPS_PROXY, bypass: 'localhost,127.0.0.1',
        } : undefined});
    try {
        const {defaultBrowserType, ...phone} = devices['iPhone 13'];
        const context = await browser.newContext(phone);
        const page = await context.newPage();
        await page.goto(env.MOODLE_WWWROOT + (process.env.CONTENTGUARD_LOGIN_TARGET || '/local/contentguard/demo.php?courseid=2'), {
            waitUntil: 'networkidle', timeout: 60000,
        });
        assert.ok(page.url().includes('/login/index.php'), 'Anonymous visitor should reach login.');
        await page.locator('#username').fill('etudiant');
        await page.locator('#password').fill(env.CONTENTGUARD_STUDENT_PASSWORD);
        assert.equal(await page.locator('#username').inputValue(), 'etudiant');
        await Promise.all([
            page.waitForURL(url => !url.pathname.includes('/login/index.php'), {timeout: 45000}),
            page.locator('#loginbtn').tap(),
        ]);
        await page.locator('#region-main .contentguard-watermark').waitFor({timeout: 45000});
        const cookies = await context.cookies();
        const session = cookies.find(cookie => cookie.name === 'MoodleSession');
        assert.ok(session && session.secure && session.httpOnly);
        await page.screenshot({path: path.join(root, 'artifacts/login-mobile-success.png')});
        fs.writeFileSync(path.join(root, 'artifacts/mobile-auth-results.json'), JSON.stringify({
            passed: true, checked: new Date().toISOString(), url: page.url(),
            mode: 'Chromium with iPhone 13 viewport and user agent (not native Safari)',
            username: 'etudiant', sessionSecure: session.secure, sessionHttpOnly: session.httpOnly,
        }, null, 2) + '\n');
        console.log('PASS: fresh mobile login and protected demo reached over HTTPS.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(String(error.message).replaceAll(env.CONTENTGUARD_STUDENT_PASSWORD, '[redacted]'));
    process.exitCode = 1;
});
