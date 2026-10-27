<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('client.auth.login_title') }} - {{ company_name() }}</title>
    @include('partials.seo-meta', ['title' => __('client.auth.login_title').' - '.company_name()])
    @vite(['resources/css/app.css'])
    @if(!empty($customFavicon))
    <link rel="icon" href="{{ $customFavicon }}" type="image/png">
    @endif
    @if(!empty($themeCssVars))
    <style id="theme-vars">{!! $themeCssVars !!}</style>
    @endif
    <style>
        * { box-sizing: border-box; }
        :root { --lp: var(--theme-primary, #1a4d80); --la: var(--theme-accent, #337ab7); }
        body { font-family: 'Inter', sans-serif; font-size: 14px; background: #eef2f7; color: #334155; margin: 0; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 24px; }
        .split { width: 100%; max-width: 920px; display: grid; grid-template-columns: 5fr 7fr; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px -20px rgba(15,23,42,.28); min-height: 520px; }
        .brand { position: relative; color: #fff; padding: 40px 32px; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden;
            background: radial-gradient(420px 260px at 0% 0%, rgba(255,255,255,.18), transparent 60%), radial-gradient(380px 300px at 100% 100%, rgba(255,255,255,.12), transparent 60%), linear-gradient(160deg, var(--lp), color-mix(in srgb, var(--lp) 55%, #000)); }
        .brand::after { content: ""; position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px); background-size: 34px 34px; mask-image: linear-gradient(180deg, transparent, #000 40%, transparent); pointer-events: none; }
        .brand > * { position: relative; z-index: 1; }
        .brand .hi { font-size: 13px; opacity: .85; margin: 0 0 6px; }
        .brand h1 { font-size: 30px; font-weight: 800; letter-spacing: -.8px; line-height: 1.1; margin: 0 0 10px; }
        .brand .sub { font-size: 13px; opacity: .8; margin: 0; }
        .brand img { max-height: 44px; max-width: 180px; margin-bottom: 18px; display: block; }
        .brand .new { border-top: 1px solid rgba(255,255,255,.25); padding-top: 18px; font-size: 13px; }
        .brand .new a { display: inline-flex; align-items: center; gap: 8px; margin-top: 8px; padding: 9px 16px; border-radius: 10px; background: rgba(255,255,255,.16); color: #fff; font-weight: 700; text-decoration: none; border: 1px solid rgba(255,255,255,.3); }
        .brand .new a:hover { background: rgba(255,255,255,.26); }
        .formside { padding: 44px 44px 36px; display: flex; flex-direction: column; justify-content: center; }
        .formside h2 { font-size: 26px; font-weight: 800; letter-spacing: -.5px; color: var(--lp); margin: 0 0 4px; }
        .formside .lead { font-size: 13px; color: #64748b; margin: 0 0 22px; padding-bottom: 14px; border-bottom: 2px solid #e2e8f0; }
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 12.5px; font-weight: 700; color: #475569; margin-bottom: 5px; }
        .form-control { display: block; width: 100%; padding: 11px 13px; font-size: 14px; color: #1e293b; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 10px; transition: border-color .15s, box-shadow .15s, background .15s; }
        .form-control:focus { background: #fff; border-color: var(--la); outline: 0; box-shadow: 0 0 0 4px color-mix(in srgb, var(--la) 18%, transparent); }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; border: 1px solid transparent; text-decoration: none; width: 100%; }
        .btn-primary { background: linear-gradient(180deg, var(--la), color-mix(in srgb, var(--la) 82%, #000)); color: #fff; box-shadow: 0 8px 20px -8px var(--la); }
        .btn-primary:hover { filter: brightness(1.06); }
        .alert { padding: 10px 13px; border-radius: 10px; font-size: 13px; margin-bottom: 14px; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .remember-row { display: flex; align-items: center; justify-content: space-between; margin: 4px 0 16px; }
        .remember-row label { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #475569; cursor: pointer; }
        .remember-row a { font-size: 13px; color: var(--la); text-decoration: none; font-weight: 600; }
        @media (max-width: 760px) { .split { grid-template-columns: 1fr; } .brand { padding: 28px 24px; gap: 22px; } .formside { padding: 28px 24px; } body { padding: 12px; } }
    </style>
    {!! hook_output('ClientAreaHeadOutput', ['user' => auth()->user()]) !!}
    @include('partials.locale-alternates')
</head>
<body>
<div class="split">
    <aside class="brand">
        <div>
            @if(!empty($customLogo))
                <img src="{{ $customLogo }}" alt="{{ company_name() }}">
            @endif
            <p class="hi">{{ __('auth.welcome_to') }}</p>
            <h1>{{ company_name() }}</h1>
            <p class="sub">{{ __('client.auth.client_area') }}</p>
        </div>
        <div class="new">
            {{ __('auth.new_user') }}<br>
            <a href="{{ route('client.register') }}">{{ __('common.actions.register') }} &rarr;</a>
        </div>
    </aside>
    <main class="formside">
        <h2>{{ __('client.auth.sign_in') }}</h2>
        <p class="lead">{{ __('client.auth.login_title') }}</p>
        @if($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('client.login.submit') }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="email">{{ __('common.form.email_address') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus class="form-control" placeholder="you@example.com">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">{{ __('common.form.password') }}</label>
                <input type="password" id="password" name="password" required class="form-control" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
            </div>
            <div class="remember-row">
                <label>
                    <input type="checkbox" name="remember"> {{ __('client.auth.remember_me') }}
                </label>
                <a href="{{ route("client.password.request") }}">{{ __('client.auth.forgot_password') }}</a>
            </div>
            @include('client.partials.recaptcha', ['form' => 'login'])
            <button type="submit" class="btn btn-primary">{{ __('client.auth.sign_in') }}</button>
        </form>
        @include('client.partials.google-button')
    </main>
</div>
    {!! hook_output('ClientAreaFooterOutput', ['user' => auth()->user()]) !!}
</body>
</html>
