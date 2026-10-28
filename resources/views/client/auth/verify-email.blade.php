@extends('client.layouts.auth-split')
@section('title', __('client.auth.verify_title'))

@section('content')
<h1 class="as-h1">{{ __('client.auth.verify_heading') }}</h1>
<p class="as-lead">
    {{ __('client.auth.verify_sent_to') }}<br>
    <strong>{{ $user->email }}</strong>
</p>
<p class="as-lead" style="font-size:13px;">{{ __('client.auth.verify_valid_24h') }}</p>

@if(session('success'))<div class="as-ok" role="status">{{ session('success') }}</div>@endif
@if($errors->any())
    <div class="as-alert" role="alert"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('client.verification.resend') }}">
    @csrf
    <button type="submit" class="as-btn">{{ __('client.auth.verify_resend') }}</button>
</form>

<details class="as-opt" style="margin-top:18px;" @if($errors->has('email')) open @endif>
    <summary>{{ __('client.auth.verify_wrong_address') }}</summary>
    <form method="POST" action="{{ route('client.verification.change') }}" style="margin-top:10px;">
        @csrf
        <div class="as-group">
            <label class="as-label" for="email">{{ __('common.form.email_address') }}</label>
            <input class="as-input" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255">
        </div>
        <button type="submit" class="as-btn">{{ __('client.auth.verify_change_email') }}</button>
    </form>
</details>

<a class="as-link" href="{{ route('client.home') }}">{{ __('client.auth.verify_continue') }} &rarr;</a>

<form method="POST" action="{{ route('client.logout') }}" style="text-align:center;margin-top:8px;">
    @csrf
    <button type="submit" class="as-back">{{ __('client.auth.logout') }}</button>
</form>
@endsection
