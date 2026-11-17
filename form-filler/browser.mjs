// Shared: launch Chrome with a dedicated, persistent profile (your Google login is kept here).
import { chromium } from 'playwright';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

// Windows web servers (e.g. Apache/Laragon) omit user env vars, causing Playwright to check "undefined\Program Files".
if (process.platform === 'win32') {
    process.env.PROGRAMFILES = process.env.PROGRAMFILES || 'C:\\Program Files';
    process.env.HOMEDRIVE = process.env.HOMEDRIVE || 'C:';
}

const here = path.dirname(fileURLToPath(import.meta.url));
export const PROFILE_DIR = process.env.FORM_PROFILE_DIR || path.join(here, '.chrome-profile');

// Chrome allows one process per profile. If another Chrome (e.g. an unclosed login.mjs window)
// holds it, a new launch hands off to that instance and exits -> Playwright's opaque
// "Target page, context or browser has been closed". Detect that and fail with a clear message.
function assertProfileFree() {
    let target;
    try {
        target = fs.readlinkSync(path.join(PROFILE_DIR, 'SingletonLock')); // "<hostname>-<pid>"
    } catch {
        return; // no lock (or Windows, which uses a lockfile instead)
    }
    const pid = Number(target.split('-').pop());
    if (!pid) return;
    try {
        process.kill(pid, 0); // throws if the process is gone
    } catch {
        return; // stale lock; Chrome clears it itself
    }
    throw new Error(
        `Chrome profile is already in use by another Chrome window (PID ${pid}). ` +
        `Close the Chrome window opened by "npm run login" (or the previous form fill) and try again.`
    );
}

export async function openBrowser({ headless = false, channel: wanted } = {}) {
    assertProfileFree();
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
