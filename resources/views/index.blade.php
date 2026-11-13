@extends('layout')

@section('content')
    @php($fmt = fn ($s) => \App\Models\TimeEntry::formatSeconds($s))

    @if ($running)
        <div class="card running">
            <div>
                <div class="muted" style="font-size:12px">NOW WORKING ON
                    @if ($running->task_key)
                        · @if ($running->task_link)<a href="{{ $running->task_link }}" target="_blank">{{ $running->task_key }}</a>@else{{ $running->task_key }}@endif
                    @endif
                    · {{ $running->module }} · {{ $running->task_type }}
                </div>
                <div style="font-size:16px;font-weight:600">{{ $running->description }}</div>
            </div>
            <div class="row">
                <span class="clock live" data-seconds="{{ $running->totalSeconds() }}" data-running="1" data-format="long"></span>
                <form class="inline" method="POST" action="{{ route('entries.pause', $running) }}">
                    @csrf <button class="btn ok">■ Stop</button>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <h2>Start a task</h2>
        <form method="POST" action="{{ route('entries.store') }}">
            @csrf
            <input type="hidden" name="from" value="{{ request('from') }}">
            <input type="hidden" name="to" value="{{ request('to') }}">
            @include('_fields')
            <div class="row" style="margin-top:12px">
                <button class="btn primary" name="action" value="start">▶ Start timer</button>
                <button class="btn" name="action" value="add">+ Add entry (manual time)</button>
            </div>
        </form>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('entries.index') }}" class="row">
            <div><label>From</label><input type="date" name="from" value="{{ $from->toDateString() }}"></div>
            <div><label>To</label><input type="date" name="to" value="{{ $to->toDateString() }}"></div>
            <div style="align-self:flex-end" class="row">
                <button class="btn">Show</button>
                <a class="btn" href="{{ route('entries.index') }}">Today</a>
                <a class="btn" href="{{ route('entries.index', ['from' => now()->subDay()->toDateString()]) }}">Yesterday</a>
                <a class="btn" href="{{ route('entries.index', ['from' => now()->startOfWeek()->toDateString(), 'to' => now()->toDateString()]) }}">This week</a>
                <a class="btn" href="{{ route('entries.index', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]) }}">This month</a>
                <a class="btn" href="{{ route('entries.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">⬇ Export CSV</a>
            </div>
            <div style="margin-left:auto;align-self:flex-end">Total: <strong>{{ $fmt($grandTotal) }}</strong></div>
        </form>
    </div>

    @forelse ($days as $date => $entries)
        @php($dayTotal = $entries->sum(fn ($e) => $e->totalSeconds()))
        @php($tsv = $entries->map->sheetRow()->implode("\n"))
        <div class="day">
            <div class="day-head">
                <h3>{{ \Illuminate\Support\Carbon::parse($date)->format('d-m-Y (D)') }}
                    <span class="pill">{{ $fmt($dayTotal) }}</span></h3>
                @php($pending = $entries->where('filled_form', false)->count())
                <div class="row" style="gap:6px">
                    @if ($pending)
                        <form class="inline fill-form" method="POST" action="{{ route('entries.fillFormDay') }}"
                              data-confirm="Fill the Google Form for {{ $pending }} entr{{ $pending === 1 ? 'y' : 'ies' }} of {{ \Illuminate\Support\Carbon::parse($date)->format('d-m-Y') }}?">
                            @csrf <input type="hidden" name="date" value="{{ $date }}">
                            <button class="btn sm primary">📝 Fill form for day ({{ $pending }})</button>
                        </form>
                    @endif
                    <button type="button" class="btn sm copy-btn" data-tsv="{{ $tsv }}"
                            title="Copies Module → Filled form columns. Paste into column B of your sheet.">⧉ Copy rows for Sheet</button>
                </div>
            </div>
            <table>
                <thead>
                <tr>
                    <th style="width:80px">Module</th><th style="width:85px">Source</th><th style="width:100px">Task type</th>
                    <th style="width:100px">Task ID</th><th style="width:70px;text-align:right">Time</th>
                    <th>Task Description</th><th style="width:85px">Filled form</th><th style="width:190px"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($entries as $e)
                    <tr class="{{ $e->isRunning() ? 'is-running' : '' }}">
                        <td>{{ $e->module }}</td>
                        <td>{{ $e->source }}</td>
                        <td>{{ $e->task_type }}</td>
                        <td>@if ($e->task_link)<a href="{{ $e->task_link }}" target="_blank">{{ $e->task_key ?: 'link' }}</a>@else{{ $e->task_key }}@endif</td>
                        <td class="time"><span class="live" data-seconds="{{ $e->totalSeconds() }}" data-running="{{ $e->isRunning() ? 1 : 0 }}">{{ $e->duration }}</span></td>
                        <td>{{ $e->description }}</td>
                        <td>
                            <form class="inline" method="POST" action="{{ route('entries.filled', $e) }}"
                                  @if ($e->filled_form) onsubmit="return confirm('Mark as NOT filled? This unlocks Resume / Edit / Delete.')" @endif>@csrf
                                <button class="btn sm" title="Toggle">{!! $e->filled_form ? '<span class="pill done">Done</span>' : '<span class="muted">—</span>' !!}</button>
                            </form>
                        </td>
                        <td class="row" style="gap:4px">
                            @if ($e->isRunning())
                                <form class="inline" method="POST" action="{{ route('entries.pause', $e) }}">@csrf<button class="btn sm ok">■ Stop</button></form>
                            @elseif ($e->filled_form && $e->work_date->isToday())
                                <button class="btn sm" disabled title="Form already filled">▶ Resume</button>
                            @else
                                <form class="inline" method="POST" action="{{ route('entries.start', $e) }}">@csrf
                                    <button class="btn sm" title="{{ $e->work_date->isToday() ? 'Resume timer' : 'Copy to today and start' }}">▶ {{ $e->work_date->isToday() ? 'Resume' : 'Again' }}</button>
                                </form>
                            @endif
                            @if (! $e->filled_form && ! $e->isRunning())
                                <form class="inline fill-form" method="POST" action="{{ route('entries.fillForm', $e) }}">@csrf
                                    <button class="btn sm" title="Fill Google Form for this entry">📝 Fill form</button>
                                </form>
                            @endif
                            @if ($e->filled_form)
                                <button class="btn sm" disabled title="Form already filled">Edit</button>
                                <button class="btn sm danger" disabled title="Form already filled">✕</button>
                            @else
                                <a class="btn sm" href="{{ route('entries.edit', $e) }}">Edit</a>
                                <form class="inline" method="POST" action="{{ route('entries.destroy', $e) }}" onsubmit="return confirm('Delete this entry?')">
                                    @csrf @method('DELETE')<button class="btn sm danger">✕</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p class="muted">No entries for this period yet. Paste a task link above and hit Start.</p>
    @endforelse
@endsection

@push('scripts')
<script>
document.querySelectorAll('.copy-btn').forEach(btn => btn.addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(btn.dataset.tsv);
        const t = btn.textContent; btn.textContent = '✓ Copied'; setTimeout(() => btn.textContent = t, 1500);
    } catch (e) { alert('Copy failed: ' + e.message); }
}));

// Google Form fill can take a few seconds per entry — show a busy overlay.
document.querySelectorAll('form.fill-form').forEach(f => f.addEventListener('submit', ev => {
    if (f.dataset.confirm && !confirm(f.dataset.confirm)) { ev.preventDefault(); return; }
    document.getElementById('busy').style.display = 'flex';
}));
</script>
<div id="busy" style="display:none;position:fixed;inset:0;background:rgba(15,76,110,.55);color:#fff;z-index:99;align-items:center;justify-content:center;flex-direction:column;font-size:18px">
    <div>📝 Filling Google Form…</div>
    <div style="font-size:13px;margin-top:6px;opacity:.85">Chrome will open and submit each entry. Don't close this tab.</div>
</div>
<script>
</script>
@endpush
