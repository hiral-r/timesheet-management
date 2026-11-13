<?php

return [

    // Dropdown values (same as your Google Sheet). Edit freely.
    'modules' => ['FlowPO', 'XOSP', 'QBSP', 'Shopify Admin', 'Session', 'Other', 'Meeting', 'R&D'],

    'sources' => ['Jira', 'Verbal', 'Log-Error'],

    'task_types' => ['Improvement', 'Bug', 'New Feature','Task', 'Log error', 'Debuggin', 'Research', 'Activity', 'Other'],

    // Jira project key prefix => Module name (used when a Jira link is fetched)
    'project_modules' => [
        'FLOW' => 'FlowPO',
        'XOSP' => 'XOSP',
        'SAA'  => 'SAA',
    ],

    // Jira issue type => Task type
    'issue_type_map' => [
        'Bug' => 'Bug',
        'Improvement' => 'Improvement',
        'Story' => 'Task',
        'Task' => 'Task',
        'Sub-task' => 'Task',
        'Subtask' => 'Task',
    ],

    'jira' => [
        // e.g. https://yourcompany.atlassian.net  (blank = taken from the pasted link)
        'base_url' => env('JIRA_BASE_URL'),
        // basic = Jira Cloud (email + API token), bearer = Jira Server/DC personal access token
        'auth' => env('JIRA_AUTH', 'basic'),
        'email' => env('JIRA_EMAIL'),
        'token' => env('JIRA_API_TOKEN'),
    ],

    // Google Form auto-fill (Playwright, see form-filler/)
    'google_form' => [
        'url' => env('GOOGLE_FORM_URL'),
        'name' => env('GOOGLE_FORM_NAME', '01 - test user'),   // must match the "Name" dropdown exactly
        'email' => env('JIRA_EMAIL'),                     // only used if the form has a typed Email box
        'node' => env('NODE_BINARY', 'node'),                    // full path if "node" isn't found, e.g. /opt/homebrew/bin/node
        'headless' => (bool) env('GOOGLE_FORM_HEADLESS', false), // false = you see Chrome filling the form
        'dry_run' => (bool) env('GOOGLE_FORM_DRY_RUN', false),   // true = fill but don't press Submit (for testing)
        'browser' => env('GOOGLE_FORM_BROWSER', 'chrome'),     // chrome = your installed Google Chrome; chromium = Playwright's
        'timeout' => 600,

        // Form question titles (left side = what we send)
        'questions' => [
            'name' => 'Name',
            'module' => 'Module',
            'task_id' => 'Task ID',
            'description' => 'Task Description',
            'task_type' => 'Task Type',
            'hours' => 'HOURS (HH:MM)',
        ],

        // App Module => form "Module" option
        'module_map' => [
            'FlowPO' => 'FLOWPO',
            'XOSP' => 'XOSP',
            'QBSP' => 'QBSP',
            'Shopify Admin' => 'SHOPIFY ADMIN',
            'FLowpo Admin' => 'FLOWPO ADMIN',
            'Session' => 'SESSION',
            'Other' => 'OTHER',
            'Meeting' => 'MEETING',
            'R&D' => 'R&D',
        ],

        // App Task type => form "Task Type" option
        'task_type_map' => [
            'Improvement' => 'Improvement',
            'Bug' => 'Bug',
            'New Feature' => 'New Feature',
            'Task' => 'Task',
            'Log error' => 'Log Error',
            'Debuggin' => 'Debuggin',
            'Research' => 'Research',        // form has no "Research" option — change if needed
            'Activity' => 'Activity',
            'Other' => 'Other',
        ],        
    ],
];
