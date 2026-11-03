@extends('client.layouts.app')
@section('title', __('portal.org.title'))

@section('styles')
<style>
.nsc-orghead{display:flex;align-items:center;gap:16px;margin-bottom:22px}
.nsc-orgav{width:52px;height:52px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px}
.nsc-tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:22px;overflow-x:auto}
.nsc-tab{padding:10px 16px;font-size:14px;font-weight:600;color:var(--muted);background:none;border:0;border-bottom:2px solid transparent;cursor:pointer;white-space:nowrap}
.nsc-tab.on{color:var(--text);border-bottom-color:var(--primary)}
.nsc-pane{display:none}.nsc-pane.on{display:block}
.nsc-bar{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap}
.nsc-bar input[type=search]{max-width:320px}
.nsc-tbl{width:100%;border-collapse:collapse;font-size:13.5px}
.nsc-tbl th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding:10px 14px;border-bottom:1px solid var(--border)}
.nsc-tbl td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:top}
.nsc-chip{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;background:var(--primary-light);color:var(--primary);margin:1px 2px 1px 0}
.nsc-chip.ok{background:rgba(16,185,129,.14);color:#10b981}
.nsc-chip.bad{background:rgba(239,68,68,.14);color:#ef4444}
.nsc-chip.mute{background:rgba(148,163,184,.16);color:var(--muted)}
.nsc-avatar{width:32px;height:32px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;margin-right:10px;vertical-align:middle}
.nsc-panel{display:none;margin-bottom:20px}.nsc-panel.on{display:block}
.nsc-checks{display:flex;flex-wrap:wrap;gap:8px 16px;margin-top:6px}
.nsc-checks label{font-size:13px;display:flex;gap:6px;align-items:center}
.nsc-temp{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.4);border-radius:10px;padding:14px 16px;margin-bottom:20px}
.nsc-temp code{font-size:15px;font-weight:700;user-select:all}
.nsc-row-actions{display:flex;gap:6px;flex-wrap:wrap}
details.nsc-edit summary{cursor:pointer;color:var(--primary);font-size:12.5px;margin-top:6px}
.nsc-gcards{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px}
.nsc-gcard{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px}
.nsc-gcard h4{margin:0 0 4px;font-size:15px}
</style>
@endsection

@section('content')
@php
    $orgName = $client->company_name ?: $client->full_name;
    $initials = mb_strtoupper(mb_substr($orgName, 0, 2));
    $groupName = collect($org['departments'])->pluck('name', 'id');
    $memberName = collect($org['members'])->pluck('name', 'id');
    $failed = collect($org['members'])->where('sync', 'failed')->count() + collect($org['departments'])->where('sync', 'failed')->count();
    $tab = session('nsc_tab', request('tab', 'users'));
@endphp

<div class="nsc-orghead">
    <div class="nsc-orgav">{{ $initials }}</div>
    <div>
        <h1 class="pn-page-title" style="margin:0">{{ $orgName }}</h1>
        <div class="form-hint">
            {{ __('portal.org.subtitle') }}
            @if($zitadelOn)
                <span class="nsc-chip {{ $failed ? 'bad' : 'ok' }}">NeoGuard · {{ $failed ? __('portal.org.sync.failed') : __('portal.org.sync.ok') }}</span>
            @else
                <span class="nsc-chip mute">{{ __('portal.org.sync.local') }}</span>
            @endif
        </div>
    </div>
</div>

@if(session('nsc_temp_password'))
    <div class="nsc-temp">
        <strong>{{ __('portal.org.temp_title') }}</strong><br>
        {{ session('nsc_temp_password.email') }} &rarr; <code>{{ session('nsc_temp_password.password') }}</code><br>
        <span class="form-hint">{{ __('portal.org.temp_hint') }}</span>
    </div>
@endif
@if($errors->any())<div class="pn-alert pn-alert-danger">{{ $errors->first() }}</div>@endif
@unless($zitadelOn)<div class="pn-alert pn-alert-info">{{ __('portal.org.local_only') }}</div>@endunless
@if($zitadelOn && $failed)
    <div class="pn-alert pn-alert-danger">
        {{ __('portal.org.sync_failed_hint') }}
        @foreach(collect($org['members'])->merge($org['departments'])->where('sync', 'failed')->take(2) as $bad)
            <div class="form-hint">{{ $bad['name'] }}: {{ $bad['error'] ?? '' }}</div>
        @endforeach
    </div>
@endif

<div class="nsc-tabs" role="tablist">
    <button type="button" class="nsc-tab" data-tab="users">{{ __('portal.org.users') }} ({{ count($org['members']) }})</button>
    <button type="button" class="nsc-tab" data-tab="groups">{{ __('portal.org.groups') }} ({{ count($org['departments']) }})</button>
    <button type="button" class="nsc-tab" data-tab="access">{{ __('portal.org.access') }}</button>
</div>

{{-- ============ USERS ============ --}}
<div class="nsc-pane" data-pane="users">
    <div class="nsc-bar">
        <input type="search" id="nscFind" class="form-control" placeholder="{{ __('portal.org.search') }}">
        @if($canManage)<button type="button" class="btn btn-primary" onclick="document.getElementById('nscInvite').classList.toggle('on')">+ {{ __('portal.org.add_user') }}</button>@endif
    </div>

    @if($canManage)
    <form method="POST" action="{{ route('client.organization.members.store') }}" class="pn-card nsc-panel {{ $errors->has('email') || $errors->has('name') ? 'on' : '' }}" id="nscInvite">
        @csrf
        <div class="pn-card-body">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">{{ __('portal.org.name') }}</label><input name="name" class="form-control" maxlength="80" required value="{{ old('name') }}"></div>
                <div class="form-group"><label class="form-label">{{ __('portal.org.email') }}</label><input type="email" name="email" class="form-control" required value="{{ old('email') }}"></div>
            </div>
            <div class="form-group"><label class="form-label">{{ __('portal.org.role') }}</label>
                <select name="role" class="form-control">
                    @foreach($roles as $r)<option value="{{ $r }}" @selected($r === 'user')>{{ __('portal.org.roles.'.$r) }} — {{ __('portal.org.role_help.'.$r) }}</option>@endforeach
                </select>
            </div>
            @if(! empty($org['departments']))
            <div class="form-group"><label class="form-label">{{ __('portal.org.groups') }}</label>
                <div class="nsc-checks">@foreach($org['departments'] as $d)<label><input type="checkbox" name="departments[]" value="{{ $d['id'] }}"> {{ $d['name'] }}</label>@endforeach</div>
            </div>
            @endif
            <button class="btn btn-primary">{{ __('portal.org.add_user') }}</button>
        </div>
    </form>
    @endif

    <div class="pn-card">
        @if(empty($org['members']))
            <div class="pn-card-body"><p class="form-hint">{{ __('portal.org.no_users') }}</p></div>
        @else
        <div style="overflow-x:auto">
        <table class="nsc-tbl" id="nscUsers">
            <thead><tr><th>{{ __('portal.org.user') }}</th><th>{{ __('portal.org.role') }}</th><th>{{ __('portal.org.groups') }}</th><th>{{ __('portal.org.access') }}</th><th>{{ __('portal.org.state') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($org['members'] as $m)
                @php $svcIds = \App\Support\ClientOrg::serviceIdsFor($org, $m['id']); @endphp
                <tr data-find="{{ mb_strtolower($m['name'].' '.$m['email']) }}">
                    <td><span class="nsc-avatar">{{ mb_strtoupper(mb_substr($m['name'], 0, 1)) }}</span><strong>{{ $m['name'] }}</strong><br><span class="form-hint" style="margin-left:42px">{{ $m['email'] }}</span></td>
                    <td><span class="nsc-chip">{{ __('portal.org.roles.'.$m['role']) }}</span></td>
                    <td>@forelse((array) ($m['departments'] ?? []) as $did)<span class="nsc-chip mute">{{ $groupName[$did] ?? '—' }}</span>@empty<span class="form-hint">—</span>@endforelse</td>
                    <td>@forelse($svcIds as $sid)<span class="nsc-chip mute">{{ $resources[$sid] ?? '#'.$sid }}</span>@empty<span class="form-hint">—</span>@endforelse</td>
                    <td>
                        <span class="nsc-chip {{ ($m['status'] ?? 'active') === 'active' ? 'ok' : 'mute' }}">{{ __('portal.org.status.'.($m['status'] ?? 'active')) }}</span>
                        @if($zitadelOn)<span class="nsc-chip {{ ($m['sync'] ?? '') === 'ok' ? 'ok' : 'bad' }}" title="{{ $m['error'] ?? '' }}">{{ __('portal.org.sync.'.($m['sync'] ?? 'local')) }}</span>@endif
                    </td>
                    <td>
                    @if($canManage)
                        <div class="nsc-row-actions">
                            @if($zitadelOn && ($m['sync'] ?? '') !== 'ok')
                                <form method="POST" action="{{ route('client.organization.members.resync', $m['id']) }}">@csrf<button class="btn btn-secondary btn-sm">{{ __('portal.org.retry') }}</button></form>
                            @endif
                            <form method="POST" action="{{ route('client.organization.members.toggle', $m['id']) }}">@csrf<button class="btn btn-secondary btn-sm">{{ ($m['status'] ?? 'active') === 'active' ? __('portal.org.suspend') : __('portal.org.activate') }}</button></form>
                            <form method="POST" action="{{ route('client.organization.members.destroy', $m['id']) }}" onsubmit="return confirm('{{ addslashes(__('portal.org.confirm_delete')) }}')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">{{ __('portal.org.delete') }}</button></form>
                        </div>
                        <details class="nsc-edit">
                            <summary>{{ __('portal.org.edit_access') }}</summary>
                            <form method="POST" action="{{ route('client.organization.members.update', $m['id']) }}" style="margin-top:8px">
                                @csrf @method('PUT')
                                <select name="role" class="form-control">@foreach($roles as $r)<option value="{{ $r }}" @selected($m['role'] === $r)>{{ __('portal.org.roles.'.$r) }}</option>@endforeach</select>
                                <div class="nsc-checks">@foreach($org['departments'] as $d)<label><input type="checkbox" name="departments[]" value="{{ $d['id'] }}" @checked(in_array($d['id'], (array) ($m['departments'] ?? []), true))> {{ $d['name'] }}</label>@endforeach</div>
                                <button class="btn btn-primary btn-sm" style="margin-top:8px">{{ __('portal.org.save') }}</button>
                            </form>
                        </details>
                    @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>
</div>

{{-- ============ GROUPS ============ --}}
<div class="nsc-pane" data-pane="groups">
    @if($canManage)
    <form method="POST" action="{{ route('client.organization.departments.store') }}" class="nsc-bar">
        @csrf
        <input name="name" class="form-control" style="max-width:320px" maxlength="60" required placeholder="{{ __('portal.org.dept_placeholder') }}">
        <button class="btn btn-primary">+ {{ __('portal.org.add_group') }}</button>
    </form>
    @endif
    @if(empty($org['departments']))
        <div class="pn-card"><div class="pn-card-body"><p class="form-hint">{{ __('portal.org.no_depts') }}</p></div></div>
    @else
    <div class="nsc-gcards">
        @foreach($org['departments'] as $d)
            @php $people = collect($org['members'])->filter(fn ($m) => in_array($d['id'], (array) ($m['departments'] ?? []), true)); @endphp
            <div class="nsc-gcard">
                <div style="display:flex;justify-content:space-between;gap:8px">
                    <h4>{{ $d['name'] }}</h4>
                    @if($canManage)
                    <form method="POST" action="{{ route('client.organization.departments.destroy', $d['id']) }}" onsubmit="return confirm('{{ addslashes(__('portal.org.confirm_delete')) }}')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">&times;</button></form>
                    @endif
                </div>
                <div class="form-hint">{{ trans_choice('portal.org.n_people', $people->count(), ['n' => $people->count()]) }}
                    @if($zitadelOn)<span class="nsc-chip {{ ($d['sync'] ?? '') === 'ok' ? 'ok' : 'bad' }}" title="{{ $d['error'] ?? '' }}">{{ __('portal.org.sync.'.($d['sync'] ?? 'local')) }}</span>@endif
                </div>
                <div style="margin-top:8px">@foreach($people as $p)<span class="nsc-chip mute">{{ $p['name'] }}</span>@endforeach</div>
            </div>
        @endforeach
    </div>
    @endif
</div>

{{-- ============ ACCESS ============ --}}
<div class="nsc-pane" data-pane="access">
    <p class="form-hint" style="margin-bottom:14px">{{ __('portal.org.access_help') }}</p>
    @if(empty($resources))
        <div class="pn-card"><div class="pn-card-body"><p class="form-hint">{{ __('portal.org.no_resources') }}</p></div></div>
    @else
    <div class="pn-card">
        <table class="nsc-tbl">
            <thead><tr><th>{{ __('portal.org.resource') }}</th><th>{{ __('portal.org.who') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($resources as $sid => $label)
                @php $rule = $org['access'][$sid] ?? ['members' => [], 'groups' => []]; $eff = \App\Support\ClientOrg::effectiveMemberIds($org, (string) $sid); @endphp
                <tr>
                    <td><strong>{{ $label }}</strong></td>
                    <td>
                        @forelse($eff as $mid)<span class="nsc-chip">{{ $memberName[$mid] ?? '—' }}</span>@empty<span class="form-hint">{{ __('portal.org.nobody') }}</span>@endforelse
                    </td>
                    <td style="min-width:230px">
                    @if($canManage)
                        <details class="nsc-edit">
                            <summary>{{ __('portal.org.assign') }}</summary>
                            <form method="POST" action="{{ route('client.organization.access.update', $sid) }}" style="margin-top:8px">
                                @csrf @method('PUT')
                                <div class="form-hint">{{ __('portal.org.groups') }}</div>
                                <div class="nsc-checks">@forelse($org['departments'] as $d)<label><input type="checkbox" name="groups[]" value="{{ $d['id'] }}" @checked(in_array($d['id'], (array) ($rule['groups'] ?? []), true))> {{ $d['name'] }}</label>@empty<span class="form-hint">—</span>@endforelse</div>
                                <div class="form-hint" style="margin-top:8px">{{ __('portal.org.users') }}</div>
                                <div class="nsc-checks">@forelse($org['members'] as $m)<label><input type="checkbox" name="members[]" value="{{ $m['id'] }}" @checked(in_array($m['id'], (array) ($rule['members'] ?? []), true))> {{ $m['name'] }}</label>@empty<span class="form-hint">—</span>@endforelse</div>
                                <button class="btn btn-primary btn-sm" style="margin-top:10px">{{ __('portal.org.save') }}</button>
                            </form>
                        </details>
                    @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<script>
(function () {
    var tabs = document.querySelectorAll('.nsc-tab'), panes = document.querySelectorAll('.nsc-pane');
    function show(name) {
        if (!document.querySelector('.nsc-pane[data-pane="' + name + '"]')) { name = 'users'; }
        tabs.forEach(function (t) { t.classList.toggle('on', t.dataset.tab === name); });
        panes.forEach(function (p) { p.classList.toggle('on', p.dataset.pane === name); });
        try { history.replaceState(null, '', '?tab=' + name); } catch (e) {}
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.tab); }); });
    show('{{ in_array($tab, ['users', 'groups', 'access'], true) ? $tab : 'users' }}');
    var find = document.getElementById('nscFind');
    if (find) { find.addEventListener('input', function () {
        var q = find.value.toLowerCase();
        document.querySelectorAll('#nscUsers tbody tr').forEach(function (tr) { tr.style.display = tr.dataset.find.indexOf(q) === -1 ? 'none' : ''; });
    }); }
})();
</script>
@endsection
