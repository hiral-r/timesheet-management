@php($e = $entry ?? null)
<div class="grid">
    <div class="span6">
        <label for="task_link">Task link (paste a Jira link or key like FLOW-198 — summary is fetched automatically)</label>
        <div class="row">
            <input type="text" id="task_link" name="task_link" style="flex:1" placeholder="https://yourcompany.atlassian.net/browse/FLOW-198"
                   value="{{ old('task_link', $e?->task_link) }}" autocomplete="off">
            <button type="button" class="btn" id="fetchBtn">Fetch</button>
        </div>
        <div class="msg" id="fetchMsg"></div>
    </div>

    <div>
        <label for="work_date">Date</label>
        <input type="date" id="work_date" name="work_date" value="{{ old('work_date', $e?->work_date?->toDateString() ?? now()->toDateString()) }}" required>
    </div>
    <div>
        <label for="module">Module</label>
        <select id="module" name="module" required>
            @foreach (config('timesheet.modules') as $opt)
                <option @selected(old('module', $e?->module) === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="source">Source</label>
        <select id="source" name="source" required>
            @foreach (config('timesheet.sources') as $opt)
                <option @selected(old('source', $e?->source) === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="task_type">Task type</label>
        <select id="task_type" name="task_type" required>
            @foreach (config('timesheet.task_types') as $opt)
                <option @selected(old('task_type', $e?->task_type) === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="task_key">Task ID / assigned by</label>
        <input type="text" id="task_key" name="task_key" value="{{ old('task_key', $e?->task_key) }}" placeholder="FLOW-198">
    </div>
    <div>
        <label for="time">Time (optional, e.g. 1:30)</label>
        <input type="text" id="time" name="time" value="{{ old('time', $e ? $e->duration : '') }}" placeholder="0:00">
    </div>

    <div class="span6">
        <label for="description">Task description</label>
        <textarea id="description" name="description" required>{{ old('description', $e?->description) }}</textarea>
    </div>

    <div class="span6">
        <label style="display:inline-flex;gap:6px;align-items:center;color:var(--ink);font-size:14px">
            <input type="checkbox" name="filled_form" value="1" @checked(old('filled_form', $e?->filled_form))> Filled form (Done)
        </label>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const link = document.getElementById('task_link');
    const btn = document.getElementById('fetchBtn');
    const msg = document.getElementById('fetchMsg');
    const $ = id => document.getElementById(id);
    let last = '';

    function setSelect(id, value) {
        if (!value) return;
        const sel = $(id);
        if ([...sel.options].some(o => o.value === value)) sel.value = value;
    }

    async function fetchJira(force) {
        const value = link.value.trim();
        if (!value || (!force && value === last)) return;
        last = value;
        msg.className = 'msg'; msg.textContent = 'Fetching from Jira…';
        try {
            const res = await fetch(@json(route('jira.fetch')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ link: value })
            });
            const data = await res.json();
            if (data.key) $('task_key').value = data.key;
            setSelect('module', data.module);
            if (data.ok) {
                setSelect('source', 'Jira');
                setSelect('task_type', data.task_type);
                if (data.summary) $('description').value = data.summary;
                if (data.link) link.value = data.link;
                msg.className = 'msg good';
                msg.textContent = '✓ ' + data.key + (data.status ? ' · ' + data.status : '') + ' — summary loaded.';
            } else {
                if (data.key) setSelect('source', 'Jira');
                msg.className = 'msg err';
                msg.textContent = data.message || 'Could not fetch.';
            }
        } catch (e) {
            msg.className = 'msg err'; msg.textContent = 'Request failed: ' + e.message;
        }
    }

    btn.addEventListener('click', () => fetchJira(true));
    link.addEventListener('paste', () => setTimeout(() => fetchJira(false), 50));
    link.addEventListener('change', () => fetchJira(false));
    link.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); fetchJira(true); } });
})();
</script>
@endpush
