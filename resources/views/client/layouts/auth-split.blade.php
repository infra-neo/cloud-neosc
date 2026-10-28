<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - {{ company_name() }}</title>
    @vite(['resources/css/app.css'])
    <style>
        :root { --bg:#fff; --fg:#14161a; --muted:#6b7280; --line:#d9dce1; --field:#fff; --accent:#1a6fd1; --accent-h:#1559a8; --err:#c43c35; --panel:#111418; }
        [data-theme="dark"] { --bg:#16181c; --fg:#f2f3f5; --muted:#9aa0aa; --line:#2e323a; --field:#1d2026; --accent:#3b8cf0; --accent-h:#2f78d6; --panel:#0c0e11; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); font-family:'Inter',system-ui,sans-serif; font-size:14px; background:var(--bg); color:var(--fg); }
        .as-main { display:flex; flex-direction:column; padding:32px 40px; min-height:100vh; }
        .as-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:8vh; }
        .as-brand { font-size:20px; font-weight:700; letter-spacing:-.02em; color:var(--fg); text-decoration:none; }
        .as-brand b { color:var(--accent); }
        .as-top a.as-site { color:var(--muted); text-decoration:none; font-size:13px; }
        .as-body { width:100%; max-width:400px; margin:0 auto; flex:1; }
        .as-h1 { font-size:34px; line-height:1.1; font-weight:800; letter-spacing:-.03em; margin:0 0 6px; }
        .as-steps { color:var(--muted); font-size:13px; margin-bottom:24px; display:flex; gap:10px; align-items:center; }
        .as-steps .bar { flex:1; height:3px; background:var(--line); border-radius:2px; overflow:hidden; }
        .as-steps .bar i { display:block; height:100%; width:50%; background:var(--accent); transition:width .25s; }
        .as-steps.s2 .bar i { width:100%; }
        .as-back { background:none; border:0; padding:0; color:var(--muted); cursor:pointer; font:inherit; text-decoration:underline; }
        .as-lead { color:var(--muted); margin:0 0 20px; line-height:1.5; }
        .as-group { margin-bottom:14px; }
        .as-label { display:block; font-weight:600; font-size:13px; margin-bottom:6px; }
        .as-label small { font-weight:400; color:var(--muted); }
        .as-input { width:100%; padding:12px 14px; font:inherit; color:var(--fg); background:var(--field); border:1px solid var(--line); border-radius:10px; transition:border-color .15s, box-shadow .15s; }
        .as-input:focus { outline:0; border-color:var(--accent); box-shadow:0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent); }
        .as-input.invalid { border-color:var(--err); }
        .as-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .as-pw { position:relative; }
        .as-pw button { position:absolute; right:8px; top:50%; transform:translateY(-50%); border:0; background:var(--line); color:var(--fg); font:inherit; font-size:12px; padding:4px 10px; border-radius:6px; cursor:pointer; }
        .as-err { color:var(--err); font-size:12px; margin-top:5px; display:block; }
        .as-alert { background:color-mix(in srgb, var(--err) 12%, transparent); border:1px solid color-mix(in srgb, var(--err) 40%, transparent); color:var(--err); border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px; }
        .as-alert ul { margin:0; padding-left:18px; }
        .as-ok { background:color-mix(in srgb, #1f9d55 14%, transparent); border:1px solid color-mix(in srgb, #1f9d55 40%, transparent); color:#1f9d55; border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px; }
        .as-btn { display:flex; width:100%; justify-content:center; align-items:center; padding:13px 16px; border-radius:10px; border:0; font:inherit; font-weight:700; cursor:pointer; background:var(--accent); color:#fff; text-decoration:none; transition:background .15s; }
        .as-btn:hover { background:var(--accent-h); }
        .as-link { display:block; text-align:center; margin-top:16px; color:var(--accent); font-weight:600; text-decoration:none; background:none; border:0; font:inherit; cursor:pointer; width:100%; }
        .as-foot { margin-top:22px; text-align:center; color:var(--muted); }
        .as-foot a { color:var(--accent); font-weight:600; text-decoration:none; }
        details.as-opt { margin:4px 0 14px; }
        details.as-opt summary { cursor:pointer; font-weight:600; font-size:13px; padding:4px 0; }
        .as-tos { display:flex; gap:8px; align-items:flex-start; font-size:13px; margin:6px 0 16px; cursor:pointer; }
        .as-tos input { margin-top:3px; }
        .as-tos a { color:var(--fg); }
        .as-legal { padding-top:24px; color:var(--muted); font-size:12px; display:flex; gap:18px; flex-wrap:wrap; }
        .as-legal a { color:var(--muted); text-decoration:none; }
        .as-side { background:radial-gradient(120% 90% at 100% 0%, color-mix(in srgb, var(--accent) 28%, var(--panel)) 0%, var(--panel) 60%); color:#e8eaee; display:flex; flex-direction:column; justify-content:flex-end; padding:48px; position:relative; overflow:hidden; }
        .as-side::after { content:''; position:absolute; right:-140px; bottom:-140px; width:420px; height:420px; border:1px dashed rgba(255,255,255,.18); border-radius:50%; }
        .as-side h2 { margin:0 0 10px; font-size:26px; letter-spacing:-.02em; position:relative; z-index:1; }
        .as-side ul { list-style:none; margin:0; padding:0; position:relative; z-index:1; display:grid; gap:10px; color:#b9bfca; }
        .as-side li::before { content:'✓'; color:var(--accent); margin-right:10px; font-weight:700; }
        @media (max-width: 860px) { body { grid-template-columns:1fr; } .as-side { display:none; } .as-main { padding:24px 20px; } .as-top { margin-bottom:4vh; } }
    </style>
    @stack('head')
</head>
<body>
<main class="as-main">
    <div class="as-top">
        <a class="as-brand" href="{{ url('/') }}">{{ company_name() }}</a>
        <a class="as-site" href="{{ url('/') }}">&larr; {{ __('client.auth.back_to_site') }}</a>
    </div>
    <div class="as-body">
        @yield('content')
    </div>
    <div class="as-legal">
        <a href="{{ \App\Models\Setting::get('PrivacyUrl', '#') }}" target="_blank" rel="noopener">{{ __('client.auth.privacy_policy') }}</a>
        <a href="{{ \App\Models\Setting::get('TOSUrl', '#') }}" target="_blank" rel="noopener">{{ __('client.auth.terms_of_service') }}</a>
    </div>
</main>
<aside class="as-side" aria-hidden="true">
    <h2>{{ __('client.auth.side_title') }}</h2>
    <ul>
        <li>{{ __('client.auth.side_point_1') }}</li>
        <li>{{ __('client.auth.side_point_2') }}</li>
        <li>{{ __('client.auth.side_point_3') }}</li>
    </ul>
</aside>
@stack('scripts')
</body>
</html>
