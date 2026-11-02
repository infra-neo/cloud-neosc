@extends('client.layouts.app')
@section('title', __('portal.org.title'))

@section('styles')
<style>
.nsc-org-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:24px;align-items:start}
@media(max-width:980px){.nsc-org-grid{grid-template-columns:1fr}}
.nsc-tbl{width:100%;border-collapse:collapse;font-size:13.5px}
.nsc-tbl th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding:10px 14px;border-bottom:1px solid var(--border)}
.nsc-tbl td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:top}
.nsc-chip{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;background:var(--primary-light);color:var(--primary)}
.nsc-chip.ok{background:rgba(16,185,129,.14);color:#10b981}
.nsc-chip.bad{background:rgba(239,68,68,.14);color:#ef4444}
.nsc-chip.mute{background:rgba(148,163,184,.16);color:var(--muted)}
.nsc-dept-row{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 0;border-bottom:1px solid var(--border)}
.nsc-checks{display:flex;flex-wrap:wrap;gap:8px 14px;margin-top:6px}
.nsc-checks label{font-size:13px;display:flex;gap:6px;align-items:center}
.nsc-temp{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.4);border-radius:10px;padding:14px 16px;margin-bottom:20px}
.nsc-temp code{font-size:15px;font-weight:700;user-select:all}
.nsc-row-actions{display:flex;gap:6px;flex-wrap:wrap}
details.nsc-edit summary{cursor:pointer;color:var(--primary);font-size:12.5px}
</style>
@endsection

@section('content')
@php $deptName = collect($org['departments'])->pluck('name', 'id'); @endphp
<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('portal.org.title') }}</h1>
        <p class="pn-page-subtitle">{{ __('portal.org.subtitle') }}</p>
    </div>
</div>

@if(session('nsc_temp_password'))
    <div class="nsc-temp">
        <strong>{{ __('portal.org.temp_title') }}</strong><br>
        {{ session('nsc_temp_password.email') }} &rarr; <code>{{ session('nsc_temp_password.password') }}</code><br>
        <span class="form-hint">{{ __('portal.org.temp_hint') }}</span>
    </div>
@endif
@if($errors->any())
    <div class="pn-alert pn-alert-danger">{{ $errors->first() }}</div>
@endif
@unless($zitadelOn)
    <div class="pn-alert pn-alert-info">{{ __('portal.org.local_only') }}</div>
@endunless

<div class="nsc-org-grid">
    <div>
        <div class="pn-card">
            <div class="pn-card-header"><div class="pn-card-title">{{ __('portal.org.users') }} ({{ count($org['members']) }})</div></div>
            @if(empty($org['members']))
                <div class="pn-card-body"><p class="form-hint">{{ __('portal.org.no_users') }}</p></div>
            @else
            <div style="overflow-x:auto">
            <table class="nsc-tbl">
                <thead><tr><th>{{ __('portal.org.user') }}</th><th>{{ __('portal.org.role') }}</th><th>{{ __('portal.org.departments') }}</th><th>{{ __('portal.org.state') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($org['members'] as $m)
                    <tr>
                        <td><strong>{{ $m['name'] }}</strong><br><span class="form-hint">{{ $m['email'] }}</span></td>
                        <td><span class="nsc-chip">{{ __('portal.org.roles.'.$m['role']) }}</span></td>
                        <td>
                            @forelse((array) ($m['departments'] ?? []) as $did)
                                <span class="nsc-chip mute">{{ $deptName[$did] ?? '—' }}</span>
                            @empty
                                <span class="form-hint">—</span>
                            @endforelse
                        </td>
                        <td>
                            <span class="nsc-chip {{ ($m['status'] ?? 'active') === 'active' ? 'ok' : 'mute' }}">{{ __('portal.org.status.'.($m['status'] ?? 'active')) }}</span>
                            @if($zitadelOn)
                                <span class="nsc-chip {{ ($m['sync'] ?? '') === 'ok' ? 'ok' : 'bad' }}" title="{{ $m['error'] ?? '' }}">{{ __('portal.org.sync.'.($m['sync'] ?? 'local')) }}</span>
                            @endif
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
                                    <select name="role" class="form-control">
                                        @foreach($roles as $r)<option value="{{ $r }}" @selected($m['role'] === $r)>{{ __('portal.org.roles.'.$r) }}</option>@endforeach
                                    </select>
                                    <div class="nsc-checks">
                                        @foreach($org['departments'] as $d)
                                            <label><input type="checkbox" name="departments[]" value="{{ $d['id'] }}" @checked(in_array($d['id'], (array) ($m['departments'] ?? []), true))> {{ $d['name'] }}</label>
                                        @endforeach
                                    </div>
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

    <div>
        @if($canManage)
        <form method="POST" action="{{ route('client.organization.members.store') }}" class="pn-card">
            @csrf
            <div class="pn-card-header"><div class="pn-card-title">{{ __('portal.org.add_user') }}</div></div>
            <div class="pn-card-body">
                <div class="form-group"><label class="form-label">{{ __('portal.org.name') }}</label><input name="name" class="form-control" maxlength="80" required value="{{ old('name') }}"></div>
                <div class="form-group"><label class="form-label">{{ __('portal.org.email') }}</label><input type="email" name="email" class="form-control" required value="{{ old('email') }}"></div>
                <div class="form-group"><label class="form-label">{{ __('portal.org.role') }}</label>
                    <select name="role" class="form-control">
                        @foreach($roles as $r)<option value="{{ $r }}" @selected($r === 'user')>{{ __('portal.org.roles.'.$r) }} — {{ __('portal.org.role_help.'.$r) }}</option>@endforeach
                    </select>
                </div>
                @if(! empty($org['departments']))
                <div class="form-group"><label class="form-label">{{ __('portal.org.departments') }}</label>
                    <div class="nsc-checks">@foreach($org['departments'] as $d)<label><input type="checkbox" name="departments[]" value="{{ $d['id'] }}"> {{ $d['name'] }}</label>@endforeach</div>
                </div>
                @endif
                <button class="btn btn-primary">{{ __('portal.org.add_user') }}</button>
            </div>
        </form>
        @endif

        <div class="pn-card">
            <div class="pn-card-header"><div class="pn-card-title">{{ __('portal.org.depts') }}</div></div>
            <div class="pn-card-body">
                @forelse($org['departments'] as $d)
                    <div class="nsc-dept-row">
                        <span>{{ $d['name'] }}
                            @if($zitadelOn)<span class="nsc-chip {{ ($d['sync'] ?? '') === 'ok' ? 'ok' : 'bad' }}" title="{{ $d['error'] ?? '' }}">{{ __('portal.org.sync.'.($d['sync'] ?? 'local')) }}</span>@endif
                        </span>
                        @if($canManage)
                        <form method="POST" action="{{ route('client.organization.departments.destroy', $d['id']) }}" onsubmit="return confirm('{{ addslashes(__('portal.org.confirm_delete')) }}')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">&times;</button></form>
                        @endif
                    </div>
                @empty
                    <p class="form-hint">{{ __('portal.org.no_depts') }}</p>
                @endforelse
                @if($canManage)
                <form method="POST" action="{{ route('client.organization.departments.store') }}" style="display:flex;gap:8px;margin-top:14px">
                    @csrf
                    <input name="name" class="form-control" maxlength="60" required placeholder="{{ __('portal.org.dept_placeholder') }}">
                    <button class="btn btn-secondary">{{ __('portal.org.add') }}</button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
