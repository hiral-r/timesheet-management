// One-time: opens Chrome on the form so you can sign in to your Parex Google account.
// Usage:  node login.mjs "<form url>"     (or: npm run login -- "<form url>")
import { openBrowser } from './browser.mjs';

const url = process.argv[2] || process.env.GOOGLE_FORM_URL || 'https://accounts.google.com/';

const context = await openBrowser({ headless: false });
const page = context.pages()[0] || (await context.newPage());
await page.goto(url);

console.log('\nA Chrome window is open.');
console.log('1) Sign in with your company Google account (if asked).');
console.log('2) Make sure you can see the timesheet form.');
console.log('3) Close the Chrome window. Your login is saved for the "Fill form" buttons.\n');

await new Promise((resolve) => context.on('close', resolve));
console.log('Saved. You can now use "Fill form" in the timesheet app.');
