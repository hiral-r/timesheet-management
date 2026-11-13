# Timesheet (personal, local)

A small Laravel 12 app that replaces the "Daily Tasks" Google Sheet:
Date · Module · Source · Task type · Task ID · Total Time · Task Description · Filled form.

## What it does

- **Paste a Jira link → summary fetched.** Paste `https://<site>.atlassian.net/browse/FLOW-198` (or just `FLOW-198`) and the
  description, Task ID, Module (FLOW → FlowPO, XOSP → XOSP), Source = Jira and Task type (from issue type) fill in automatically.
- **Start / Stop timer.** One task runs at a time; starting another stops the current one. Resume adds to the same row
  for today; "Again" on an older row copies it to today (handy for the daily "Check Log" task).
- **Manual entries** for Verbal / Log-Error work: type the time as `1:30`, `1.5` or `90m`.
- **Filled form** toggle per row, edit, delete.
- **Day totals**, date range filter (Today / Yesterday / This week / This month).
- **Copy rows for Sheet** — copies a day's rows as tab-separated text; click column B in your Google Sheet and paste.
- **Export CSV** for any date range.
- **Google Form auto-fill** — "📝 Fill form" on each row, "📝 Fill form for day" on each day. Chrome opens, fills
  Name / Module / Task ID / Task Description / Task Type / HOURS and presses Submit; successful rows become **Done**.

## Setup (once)

Requires PHP 8.2+, Composer and a local MySQL running on 127.0.0.1.

1. Open `.env` and check `DB_USERNAME` / `DB_PASSWORD` for your **local** MySQL (default `root` / empty).
   `DB_DATABASE=timesheet` is created for you. The create-db command refuses any host other than localhost.
2. Add Jira credentials to `.env`:
   ```
   JIRA_BASE_URL=https://yourcompany.atlassian.net   # optional, taken from the link if blank
   JIRA_EMAIL=you@company.com
   JIRA_API_TOKEN=...   # https://id.atlassian.com/manage-profile/security/api-tokens
   ```
   (Jira Server/Data Center: set `JIRA_AUTH=bearer` and put a Personal Access Token in `JIRA_API_TOKEN`.)
3. Run:
   ```
   cd ~/timesheet
   bash setup.sh
   php artisan serve --port=8090
   ```
4. Open http://127.0.0.1:8090

If you use Laravel Herd/Valet, you can instead link the folder and open `http://timesheet.test`.

## Google Form auto-fill (one-time setup)

Needs Node 18+ and Google Chrome.

```
cd ~/timesheet/form-filler
npm install
npm run login -- "https://docs.google.com/forms/d/e/1FAIpQLSexXYZx3jKRYMXVIuLKIlw527MtPKDgr_IJkBsJNH-ae0Rj0g/viewform"
```

A Chrome window opens: sign in with your Parex Google account, check the form shows, then close the window.
The login is stored in `form-filler/.chrome-profile` (only on this Mac) and reused by the Fill buttons.

- First test: set `GOOGLE_FORM_DRY_RUN=true` in `.env`, run `php artisan config:clear`, click "Fill form" — it fills
  but does not submit. Set it back to `false` when happy.
- The form has no Date question, so Google records the submit time. Fill the form on the same day.
- Running timers are skipped (stop them first). Entries with 0:00 are skipped.
- Mapping app → form values (e.g. FlowPO → FLOWPO, Research → Task) is in `config/timesheet.php` → `google_form`.
- On an error, a screenshot is saved to `form-filler/last-error.png`.
- "node not found": put the output of `which node` in `.env` as `NODE_BINARY=...`.
- If Google says "This browser may not be secure" during login: run `npx playwright install chromium`, then
  `FORM_BROWSER_CHANNEL=chromium npm run login -- "<form url>"`, and set `GOOGLE_FORM_BROWSER=chromium` in `.env`.

## Customising

Dropdown values, Jira project → Module mapping and issue type → Task type mapping live in `config/timesheet.php`.
After editing `.env` or config, run `php artisan config:clear` if you ever cached config.

## Files

- `app/Services/JiraService.php` — parses the link and calls Jira REST API (`/rest/api/3/issue/{key}?fields=summary,issuetype,status`)
- `app/Models/TimeEntry.php` — timer logic (accumulated seconds + `running_since`)
- `app/Http/Controllers/TimeEntryController.php` — pages and actions
- `resources/views/*.blade.php` — UI (plain CSS/JS, no npm build needed)
- `database/migrations/*_create_time_entries_table.php` — the single table
