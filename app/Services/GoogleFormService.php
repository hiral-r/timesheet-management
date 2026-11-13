<?php

namespace App\Services;

use App\Models\TimeEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleFormService
{
    /** Form answers for one entry, keyed by the form's question title. */
    public function answersFor(TimeEntry $entry): array
    {
        $cfg = config('timesheet.google_form');
        $q = $cfg['questions'];

        $module = $cfg['module_map'][$entry->module] ?? null;
        $type = $cfg['task_type_map'][$entry->task_type] ?? null;

        if (! $module) {
            throw new RuntimeException("Module \"{$entry->module}\" has no form option (config/timesheet.php → google_form.module_map).");
        }
        if (! $type) {
            throw new RuntimeException("Task type \"{$entry->task_type}\" has no form option (config/timesheet.php → google_form.task_type_map).");
        }

        $minutes = (int) round($entry->totalSeconds() / 60);
        if ($minutes < 1) {
            throw new RuntimeException('Time is 0:00 — nothing to submit.');
        }

        return [
            $q['name'] => $cfg['name'],
            $q['module'] => $module,
            $q['task_id'] => (string) $entry->task_key,
            $q['description'] => $entry->description,
            $q['task_type'] => $type,
            $q['hours'] => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60),
        ];
    }

    /**
     * Fill the form once per entry. Marks successful ones as filled.
     *
     * @param  Collection<int, TimeEntry>  $entries
     * @return array{ok: int, failed: array<int, string>, dry_run: bool}
     */
    public function submit(Collection $entries): array
    {
        $cfg = config('timesheet.google_form');
        $failed = [];
        $jobEntries = [];

        foreach ($entries as $entry) {
            if ($entry->filled_form) {
                $failed[$entry->id] = 'Already filled.';
                continue;
            }
            if ($entry->isRunning()) {
                $failed[$entry->id] = 'Timer still running — stop it first.';
                continue;
            }
            try {
                $jobEntries[] = ['id' => $entry->id, 'answers' => $this->answersFor($entry)];
            } catch (RuntimeException $e) {
                $failed[$entry->id] = $e->getMessage();
            }
        }

        $ok = 0;
        if ($jobEntries) {
            $results = $this->run([
                'formUrl' => $cfg['url'],
                'email' => $cfg['email'],
                'headless' => $cfg['headless'],
                'dryRun' => $cfg['dry_run'],
                'browser' => $cfg['browser'] ?? 'chrome',
                'entries' => $jobEntries,
            ]);

            foreach ($jobEntries as $job) {
                $r = $results[$job['id']] ?? ['ok' => false, 'error' => 'Not processed (stopped after an earlier error).'];
                if ($r['ok']) {
                    TimeEntry::whereKey($job['id'])->update(['filled_form' => true]);
                    $ok++;
                } else {
                    $failed[$job['id']] = $r['error'] ?? 'Unknown error';
                }
            }
        }

        return ['ok' => $ok, 'failed' => $failed, 'dry_run' => (bool) $cfg['dry_run']];
    }

    /** Runs form-filler/fill-form.mjs and returns results keyed by entry id. */
    private function run(array $job): array
    {
        $dir = base_path('form-filler');
        if (! is_dir($dir.'/node_modules/playwright')) {
            throw new RuntimeException('Form filler not installed. Run:  cd ~/timesheet/form-filler && npm install');
        }

        $jobFile = storage_path('app/form-job-'.Str::random(8).'.json');
        file_put_contents($jobFile, json_encode($job));

        @set_time_limit(0);

        try {
            $process = Process::path($dir)
                ->timeout((int) config('timesheet.google_form.timeout', 600))
                ->run([config('timesheet.google_form.node'), 'fill-form.mjs', $jobFile]);
        } catch (\Symfony\Component\Process\Exception\RuntimeException $e) {
            throw new RuntimeException('Could not start Node ('.Str::limit($e->getMessage(), 150).'). Set NODE_BINARY in .env to the output of `which node`.');
        } finally {
            @unlink($jobFile);
        }

        $results = [];
        foreach (preg_split('/\R/', $process->output()) as $line) {
            if (str_starts_with($line, 'RESULT ')) {
                $r = json_decode(substr($line, 7), true);
                if (isset($r['id'])) {
                    $results[$r['id']] = $r;
                }
            }
        }

        if (! $results && ! $process->successful()) {
            $err = trim($process->errorOutput()) ?: trim($process->output());
            if ($process->exitCode() === 127) {
                $err = 'Node not found. Set NODE_BINARY in .env to the output of `which node`.';
            }
            throw new RuntimeException('Form filler failed: '.Str::limit($err, 400));
        }

        return $results;
    }
}
