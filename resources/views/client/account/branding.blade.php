@extends('client.layouts.app')
@section('title', __('portal.brand.title'))

@section('content')
@php
    $shownName = $brand['name'] !== '' ? $brand['name'] : ($client->company_name ?: company_name());
    $shownColor = $brand['color'] !== '' ? $brand['color'] : \App\Support\ClientBrand::DEFAULT_COLOR;
    $verified = $brand['domain'] !== '' && $brand['domain_verified_at'] !== '';
    $jsFallback = $client->company_name ?: company_name();
    $jsHint = __('portal.brand.domain_hint');
@endphp
<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('portal.brand.title') }}</h1>
        <p class="pn-page-subtitle">{{ __('portal.brand.subtitle') }}</p>
    </div>
</div>

<div class="nsc-brand-grid">
    <form method="POST" action="{{ route('client.branding.update') }}" enctype="multipart/form-data" class="pn-card" id="brandForm">
        @csrf
        @method('PUT')
        <div class="pn-card-body">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="bName">{{ __('portal.brand.name') }}</label>
                    <input type="text" id="bName" name="name" class="form-control" maxlength="60" value="{{ old('name', $brand['name']) }}" placeholder="{{ $client->company_name ?: company_name() }}">
                    <div class="form-hint">{{ __('portal.brand.name_hint') }}</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="bColor">{{ __('portal.brand.color') }}</label>
                    <input type="color" id="bColor" name="color" class="form-control" style="height:40px;padding:4px" value="{{ old('color', $shownColor) }}">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="bLogo">{{ __('portal.brand.logo') }}</label>
                    <input type="file" id="bLogo" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <div class="form-hint">{{ __('portal.brand.logo_hint') }}</div>
                    @if($brand['logo'] !== '')
                        <label class="form-hint"><input type="checkbox" name="remove_logo" value="1"> {{ __('portal.brand.remove') }}</label>
                    @endif
                </div>
                <div class="form-group">
                    <label class="form-label" for="bFav">{{ __('portal.brand.favicon') }}</label>
                    <input type="file" id="bFav" name="favicon" class="form-control" accept="image/png,image/x-icon">
                    <div class="form-hint">{{ __('portal.brand.favicon_hint') }}</div>
                    @if($brand['favicon'] !== '')
                        <label class="form-hint"><input type="checkbox" name="remove_favicon" value="1"> {{ __('portal.brand.remove') }}</label>
                    @endif
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="bDomain">{{ __('portal.brand.domain') }}
                    @if($brand['domain'] !== '')
                        <span class="badge {{ $verified ? 'badge-active' : 'badge-pending' }}">{{ $verified ? __('portal.brand.verified') : __('portal.brand.pending') }}</span>
                    @endif
                </label>
                <input type="text" id="bDomain" name="domain" class="form-control" value="{{ old('domain', $brand['domain']) }}" placeholder="{{ __('portal.brand.domain_hint') }}" autocomplete="off" autocapitalize="off" spellcheck="false">
                <div class="form-hint">{{ __('portal.brand.domain_note') }}</div>
            </div>

            <div class="form-group">
                <div class="pn-section-title">{{ __('portal.brand.cname_title') }}</div>
                <div class="pn-code" id="bCname" style="display:block;overflow-wrap:anywhere">CNAME {{ old('domain', $brand['domain']) ?: __('portal.brand.domain_hint') }} &rarr; {{ $cnameTarget }}</div>
            </div>

            <div class="flex gap-12" style="flex-wrap:wrap">
                <button type="submit" class="btn btn-primary">{{ __('portal.brand.save') }}</button>
                @if($brand['domain'] !== '')
                    <button type="submit" form="verifyForm" class="btn btn-outline">{{ __('portal.brand.verify') }}</button>
                @endif
            </div>
        </div>
    </form>

    <div>
        <div class="pn-section-title">{{ __('portal.brand.preview') }}</div>
        <div class="pn-card" style="overflow:hidden">
            <div id="pvHead" style="background:#0e1522;padding:14px 16px;display:flex;align-items:center;gap:10px;color:#fff;font-weight:700">
                @if($brand['logo'] !== '')
                    <img id="pvLogo" src="{{ $brand['logo'] }}" alt="" style="max-height:30px;max-width:160px">
                @else
                    <span id="pvMark" style="width:28px;height:28px;border-radius:8px;background:{{ $shownColor }};display:grid;place-items:center">{{ strtoupper(mb_substr($shownName, 0, 1)) }}</span>
                    <span id="pvName">{{ $shownName }}</span>
                @endif
            </div>
            <div class="pn-card-body" style="display:grid;gap:8px">
                <div class="btn btn-primary" id="pvBtn" style="background:{{ $shownColor }};justify-content:flex-start">{{ __('portal.brand.preview_apps') }}</div>
                <div class="btn btn-default" style="justify-content:flex-start">{{ __('portal.brand.preview_desktop') }}</div>
                <div class="btn btn-default" style="justify-content:flex-start">{{ __('portal.brand.preview_sessions') }}</div>
            </div>
        </div>
    </div>
</div>

<form id="verifyForm" method="POST" action="{{ route('client.branding.verify') }}" style="display:none">@csrf</form>
@endsection

@section('styles')
<style>
    .nsc-brand-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:24px;align-items:start}
    @media(max-width:900px){.nsc-brand-grid{grid-template-columns:minmax(0,1fr)}}
</style>
@endsection

@section('scripts')
<script>
(function () {
    var color = document.getElementById('bColor'), name = document.getElementById('bName'), dom = document.getElementById('bDomain');
    var target = @json($cnameTarget), fallback = @json($jsFallback), hint = @json($jsHint);
    function paint() {
        var c = color.value, n = (name.value || fallback), m = document.getElementById('pvMark'), nm = document.getElementById('pvName');
        document.getElementById('pvBtn').style.background = c;
        if (m) { m.style.background = c; m.textContent = n.charAt(0).toUpperCase(); }
        if (nm) { nm.textContent = n; }
        document.getElementById('bCname').textContent = 'CNAME ' + (dom.value.trim() || hint) + ' → ' + target;
    }
    [color, name, dom].forEach(function (e) { e.addEventListener('input', paint); });
})();
</script>
@endsection
