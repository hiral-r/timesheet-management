<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Timesheet')</title>
    <style>
        :root { --bg:#f4f6f8; --card:#fff; --ink:#1f2933; --muted:#6b7785; --line:#e1e5ea; --head:#0f4c6e; --brand:#1a73e8; --ok:#188038; --warn:#c5221f; --run:#e8f5e9; }
        * { box-sizing: border-box; }
        body { margin:0; font:14px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background:var(--bg); color:var(--ink); }
        header.top { background:var(--head); color:#fff; padding:12px 24px; display:flex; align-items:center; justify-content:space-between; }
        header.top h1 { font-size:18px; margin:0; }
        header.top a { color:#fff; text-decoration:none; }
        main { max-width:1280px; margin:0 auto; padding:20px 24px 60px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:16px 18px; margin-bottom:18px; }
        .card h2 { font-size:15px; margin:0 0 12px; }
        .grid { display:grid; grid-template-columns: repeat(6, 1fr); gap:10px 12px; }
        .grid .span2 { grid-column: span 2; } .grid .span3 { grid-column: span 3; } .grid .span6 { grid-column: 1 / -1; }
        label { display:block; font-size:12px; color:var(--muted); margin-bottom:3px; }
        input[type=text], input[type=date], input[type=url], select, textarea { width:100%; padding:7px 9px; border:1px solid #c9d0d8; border-radius:6px; font:inherit; background:#fff; }
        textarea { min-height:60px; resize:vertical; }
        .row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .btn { display:inline-block; border:1px solid #c9d0d8; background:#fff; color:var(--ink); padding:7px 12px; border-radius:6px; font:inherit; cursor:pointer; text-decoration:none; white-space:nowrap; }
        .btn:hover { background:#f0f2f5; }
        .btn:disabled, .btn:disabled:hover { opacity:.4; cursor:not-allowed; background:#fff; color:var(--ink); border-color:#c9d0d8; }
        .btn.primary { background:var(--brand); border-color:var(--brand); color:#fff; }
        .btn.ok { background:var(--ok); border-color:var(--ok); color:#fff; }
        .btn.danger { color:var(--warn); }
        .btn.sm { padding:3px 8px; font-size:12px; }
        form.inline { display:inline; }
        .flash { background:#e6f4ea; border:1px solid #b7dfc2; padding:8px 12px; border-radius:6px; margin-bottom:14px; }
        .errors { background:#fce8e6; border:1px solid #f4c7c3; padding:8px 12px; border-radius:6px; margin-bottom:14px; }
        .msg { font-size:12px; margin-top:4px; } .msg.err { color:var(--warn); } .msg.good { color:var(--ok); }
        .running { background:var(--run); border-color:#a5d6a7; display:flex; align-items:center; justify-content:space-between; gap:16px; }
        .running .clock { font-size:28px; font-variant-numeric:tabular-nums; font-weight:600; }
        table { width:100%; border-collapse:collapse; background:#fff; }
        th { background:var(--head); color:#fff; font-weight:600; font-size:12px; text-align:left; padding:7px 8px; }
        td { border-bottom:1px solid var(--line); padding:6px 8px; vertical-align:top; }
        tr.is-running td { background:var(--run); }
        td.time { font-variant-numeric:tabular-nums; white-space:nowrap; text-align:right; }
        .day { margin-bottom:22px; }
        .day-head { display:flex; justify-content:space-between; align-items:center; margin:0 0 6px; }
        .day-head h3 { margin:0; font-size:15px; }
        .pill { display:inline-block; padding:1px 8px; border-radius:10px; font-size:12px; background:#eef1f4; }
        .pill.done { background:#e6f4ea; color:var(--ok); }
        .muted { color:var(--muted); }
        a { color:var(--brand); }
        @media (max-width: 800px) { .grid { grid-template-columns: 1fr 1fr; } .grid .span3, .grid .span2 { grid-column: 1 / -1; } }
    </style>
</head>
<body>
<header class="top">
    <h1><a href="{{ route('entries.index') }}">⏱ Timesheet</a></h1>
    <span>{{ now()->format('D, d M Y') }}</span>
</header>
<main>
    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="errors">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    @yield('content')
</main>

<script>
    // Live timers: <span class="live" data-seconds="123" data-running="1" data-format="long">
    (function () {
        const loadedAt = Date.now();
        const pad = n => String(n).padStart(2, '0');
        function fmt(sec, long) {
            const h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
            if (long) return h + ':' + pad(m) + ':' + pad(s);
            const mins = Math.round(sec / 60);
            return Math.floor(mins / 60) + ':' + pad(mins % 60);
        }
        function tick() {
            const elapsed = Math.floor((Date.now() - loadedAt) / 1000);
            document.querySelectorAll('.live[data-running="1"]').forEach(el => {
                el.textContent = fmt(parseInt(el.dataset.seconds, 10) + elapsed, el.dataset.format === 'long');
            });
        }
        tick(); setInterval(tick, 1000);
    })();
</script>
@stack('scripts')
</body>
</html>
