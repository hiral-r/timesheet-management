<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Services\GoogleFormService;
use App\Services\JiraService;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeEntryController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);

        $entries = TimeEntry::query()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('work_date')
            ->orderBy('id')
            ->get();

        $days = $entries->groupBy(fn (TimeEntry $e) => $e->work_date->toDateString());

        return view('index', [
            'days' => $days,
            'running' => TimeEntry::whereNotNull('running_since')->first(),
            'from' => $from,
            'to' => $to,
            'grandTotal' => $entries->sum(fn (TimeEntry $e) => $e->totalSeconds()),
        ]);
    }

    public function fetchJira(Request $request, JiraService $jira): JsonResponse
    {
        $request->validate(['link' => 'required|string|max:500']);
        $link = trim($request->input('link'));

        try {
            return response()->json(['ok' => true] + $jira->fetch($link));
        } catch (RuntimeException $e) {
            // Still return what we could work out (key/module) so the form can be pre-filled.
            $key = $jira->extractKey($link);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'key' => $key,
                'module' => $key ? $jira->moduleFor($key) : null,
            ], 200);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $entry = TimeEntry::create($data);

        if ($request->input('action') === 'start') {
            $entry->start();

            return redirect()->route('entries.index')->with('status', 'Timer started: '.$entry->description);
        }

        return redirect()->route('entries.index', $this->keepRange($request))->with('status', 'Entry added.');
    }

    public function edit(TimeEntry $entry): View|RedirectResponse
    {
        if ($locked = $this->lockedResponse($entry)) {
            return $locked;
        }

        return view('edit', ['entry' => $entry]);
    }

    public function update(Request $request, TimeEntry $entry): RedirectResponse
    {
        if ($locked = $this->lockedResponse($entry)) {
            return $locked;
        }

        $wasRunning = $entry->isRunning();
        $entry->pause();

        $entry->update($this->validated($request));

        if ($wasRunning && $request->boolean('keep_running')) {
            $entry->start();
        }

        return redirect()->route('entries.index', ['from' => $entry->work_date->toDateString(), 'to' => $entry->work_date->toDateString()])
            ->with('status', 'Entry updated.');
    }

    /** Start / resume. A task from an earlier day is copied to today and started there. */
    public function start(TimeEntry $entry): RedirectResponse
    {
        if ($entry->work_date->isToday() && ($locked = $this->lockedResponse($entry))) {
            return $locked;
        }

        if (! $entry->work_date->isToday()) {
            $entry = $entry->replicate(['seconds', 'running_since', 'filled_form']);
            $entry->work_date = Carbon::today();
            $entry->seconds = 0;
            $entry->filled_form = false;
            $entry->save();
        }

        $entry->start();

        return redirect()->route('entries.index')->with('status', 'Timer running: '.$entry->description);
    }

    public function pause(Request $request, TimeEntry $entry): RedirectResponse
    {
        $entry->pause();

        return back()->with('status', 'Timer stopped at '.$entry->duration.'.');
    }

    public function toggleFilled(TimeEntry $entry): RedirectResponse
    {
        $entry->update(['filled_form' => ! $entry->filled_form]);

        return back();
    }

    /** Fill the Google Form for one entry. */
    public function fillForm(TimeEntry $entry, GoogleFormService $form): RedirectResponse
    {
        return $this->runFormFill(collect([$entry]), $form);
    }

    /** Fill the Google Form for every not-yet-filled entry of a day. */
    public function fillFormDay(Request $request, GoogleFormService $form): RedirectResponse
    {
        $request->validate(['date' => 'required|date']);

        $entries = TimeEntry::query()
            ->whereDate('work_date', $request->input('date'))
            ->where('filled_form', false)
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            return back()->with('status', 'Nothing to fill — all entries for that day are already Done.');
        }

        return $this->runFormFill($entries, $form);
    }

    private function runFormFill($entries, GoogleFormService $form): RedirectResponse
    {
        try {
            $result = $form->submit($entries);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()]);
        }

        $names = $entries->keyBy('id');
        $errors = collect($result['failed'])
            ->map(fn ($msg, $id) => '“'.Str::limit($names[$id]->description ?? '#'.$id, 50).'”: '.$msg)
            ->values()
            ->all();

        $status = $result['dry_run']
            ? 'Dry run finished (GOOGLE_FORM_DRY_RUN=true) — nothing was submitted.'
            : "Google Form submitted for {$result['ok']} of {$entries->count()} entr".($entries->count() === 1 ? 'y' : 'ies').'.';

        $redirect = back()->with('status', $status);

        return ($errors && ! $result['dry_run']) ? $redirect->withErrors($errors) : $redirect;
    }

    public function destroy(TimeEntry $entry): RedirectResponse
    {
        if ($locked = $this->lockedResponse($entry)) {
            return $locked;
        }

        $entry->delete();

        return back()->with('status', 'Entry deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $entries = TimeEntry::query()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')->orderBy('id')->get();

        $name = 'timesheet_'.$from->format('Y-m-d').'_to_'.$to->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Module', 'Source', 'Task type', 'Task ID', 'Total Time', 'Task Description', 'Filled form', 'Link']);
            foreach ($entries as $e) {
                fputcsv($out, [
                    $e->work_date->format('d-m-Y'), $e->module, $e->source, $e->task_type, $e->task_key,
                    $e->duration, $e->description, $e->filled_form ? 'Done' : '', $e->task_link,
                ]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** Entries already submitted to the Google Form can't be resumed, edited or deleted. */
    private function lockedResponse(TimeEntry $entry): ?RedirectResponse
    {
        if (! $entry->filled_form) {
            return null;
        }

        return redirect()->route('entries.index', ['from' => $entry->work_date->toDateString()])
            ->withErrors(['locked' => 'This entry is already filled in the form, so it is locked. Click its "Done" to unlock.']);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'work_date' => 'required|date',
            'module' => 'required|string|max:50',
            'source' => 'required|string|max:50',
            'task_type' => 'required|string|max:50',
            'task_key' => 'nullable|string|max:100',
            'task_link' => 'nullable|string|max:500',
            'description' => 'required|string|max:5000',
            'time' => ['nullable', 'string', 'regex:/^(\d+:\d{1,2}|\d+(\.\d+)?|\d+\s*m)$/i'],
            'filled_form' => 'nullable|boolean',
        ], [
            'time.regex' => 'Time must look like 1:30, 1.5 or 90m.',
        ]);

        $v['seconds'] = TimeEntry::parseDuration($v['time'] ?? null);
        $v['filled_form'] = $request->boolean('filled_form');
        unset($v['time']);

        return $v;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : Carbon::today();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : $from->copy();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }

    private function keepRange(Request $request): array
    {
        return array_filter($request->only('from', 'to'));
    }
}
