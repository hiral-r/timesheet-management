<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class JiraService
{
    /** Pull the issue key out of any Jira URL (browse/…, selectedIssue=…, etc.) or a bare key. */
    public function extractKey(string $link): ?string
    {
        if (preg_match('/(?:browse\/|selectedIssue=|issues\/)([A-Z][A-Z0-9_]+-\d+)/i', $link, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match('/\b([A-Z][A-Z0-9_]+-\d+)\b/i', $link, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    public function baseUrl(string $link): ?string
    {
        $configured = config('timesheet.jira.base_url');
        if ($configured) {
            return rtrim($configured, '/');
        }

        $parts = parse_url($link);
        if (! empty($parts['scheme']) && ! empty($parts['host'])) {
            return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        }

        return null;
    }

    /**
     * Returns: key, summary, module, task_type, status, link
     *
     * @throws RuntimeException
     */
    public function fetch(string $link): array
    {
        $key = $this->extractKey($link);
        if (! $key) {
            throw new RuntimeException('Could not find a Jira issue key (like FLOW-198) in that link.');
        }

        $result = [
            'key' => $key,
            'summary' => null,
            'module' => $this->moduleFor($key),
            'task_type' => 'Task',
            'status' => null,
            'link' => $link,
        ];

        $base = $this->baseUrl($link);
        $token = config('timesheet.jira.token');

        if (! $base || ! $token) {
            throw new RuntimeException("Found {$key}, but Jira credentials are not set in .env (JIRA_API_TOKEN / JIRA_EMAIL), so the summary can't be fetched.");
        }

        if (! str_starts_with($link, 'http')) {
            $result['link'] = $base.'/browse/'.$key;
        }

        $request = Http::acceptJson()->timeout(15);
        $request = config('timesheet.jira.auth') === 'bearer'
            ? $request->withToken($token)
            : $request->withBasicAuth((string) config('timesheet.jira.email'), $token);

        $apiVersion = config('timesheet.jira.auth') === 'bearer' ? '2' : '3';
        $response = $request->get("{$base}/rest/api/{$apiVersion}/issue/{$key}", [
            'fields' => 'summary,issuetype,status',
        ]);

        if ($response->status() === 401 || $response->status() === 403) {
            throw new RuntimeException('Jira rejected the credentials (HTTP '.$response->status().'). Check JIRA_EMAIL / JIRA_API_TOKEN.');
        }
        if ($response->status() === 404) {
            throw new RuntimeException("Jira issue {$key} not found (or you don't have access).");
        }
        if ($response->failed()) {
            throw new RuntimeException('Jira request failed (HTTP '.$response->status().').');
        }

        $fields = $response->json('fields', []);
        $issueType = $fields['issuetype']['name'] ?? null;

        $result['summary'] = $fields['summary'] ?? null;
        $result['status'] = $fields['status']['name'] ?? null;
        $result['task_type'] = config('timesheet.issue_type_map.'.$issueType, 'Task');

        return $result;
    }

    public function moduleFor(string $key): ?string
    {
        $project = strtoupper(explode('-', $key)[0]);

        return config('timesheet.project_modules')[$project] ?? null;
    }
}
