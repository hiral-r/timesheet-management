// Shared: launch Chrome with a dedicated, persistent profile (your Google login is kept here).
import { chromium } from 'playwright';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// Windows web servers (e.g. Apache/Laragon) omit user env vars, causing Playwright to check "undefined\Program Files".
if (process.platform === 'win32') {
    process.env.PROGRAMFILES = process.env.PROGRAMFILES || 'C:\\Program Files';
    process.env.HOMEDRIVE = process.env.HOMEDRIVE || 'C:';
}

const here = path.dirname(fileURLToPath(import.meta.url));
export const PROFILE_DIR = process.env.FORM_PROFILE_DIR || path.join(here, '.chrome-profile');

export async function openBrowser({ headless = false, channel: wanted } = {}) {
    const channel = wanted || process.env.FORM_BROWSER_CHANNEL || 'chrome'; // installed Google Chrome; set "chromium" to use Playwright's

    return chromium.launchPersistentContext(PROFILE_DIR, {
        channel: channel === 'chromium' ? undefined : channel,
        headless,
        viewport: { width: 1100, height: 900 },
        ignoreDefaultArgs: ['--enable-automation'],
        args: ['--disable-blink-features=AutomationControlled'],
    });
}

export function isLoginPage(url) {
    return /accounts\.google\.com|ServiceLogin|signin/i.test(url);
}
