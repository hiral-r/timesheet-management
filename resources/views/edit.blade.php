@extends('layout')

@section('title', 'Edit entry')

@section('content')
    <div class="card">
        <h2>Edit entry</h2>
        @if ($entry->isRunning())
            <p class="muted">This timer is running. Saving will store the time shown below; tick "keep running" to continue the timer afterwards.</p>
        @endif
        <form method="POST" action="{{ route('entries.update', $entry) }}">
            @csrf @method('PUT')
            @include('_fields', ['entry' => $entry])
            <div class="row" style="margin-top:12px">
                @if ($entry->isRunning())
                    <label style="display:inline-flex;gap:6px;align-items:center;color:var(--ink);font-size:14px;margin:0">
                        <input type="checkbox" name="keep_running" value="1" checked> keep running
                    </label>
                @endif
                <button class="btn primary">Save</button>
                <a class="btn" href="{{ route('entries.index', ['from' => $entry->work_date->toDateString()]) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
