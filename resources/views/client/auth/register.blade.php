@extends('client.layouts.auth-split')
@section('title', __('client.auth.register_title'))

@php
    // Land the visitor on the step that holds the first error.
    $step2Fields = ['country', 'address1', 'address2', 'city', 'postcode', 'company_name', 'phone_number', 'tos'];
    $startStep = $errors->any() && collect($step2Fields)->contains(fn ($f) => $errors->has($f))
        && ! collect(['first_name', 'last_name', 'email', 'password'])->contains(fn ($f) => $errors->has($f)) ? 2 : 1;
    $countries = \App\Support\Countries::ALL;
    $selectedCountry = old('country', \App\Models\Setting::get('DefaultCountry', 'MX'));
@endphp

@section('content')
<form method="POST" action="{{ route('client.register.submit') }}" id="wizard" novalidate data-start="{{ $startStep }}">
    @csrf
    <input type="hidden" name="wizard" value="1">

    <h1 class="as-h1">{{ __('client.auth.create_your_account') }}</h1>
    <div class="as-steps" id="steps" aria-live="polite">
        <button type="button" class="as-back" id="back" hidden>&larr; {{ __('client.auth.back') }}</button>
        <span id="step-label">1 / 2</span>
        <span class="bar"><i></i></span>
    </div>

    @if($errors->any())
        <div class="as-alert" role="alert">
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="as-alert" id="client-error" role="alert" hidden></div>

    {{-- Step 1: who you are --}}
    <section data-step="1">
        <div class="as-row">
            <div class="as-group">
                <label class="as-label" for="first_name">{{ __('common.form.first_name') }}</label>
                <input class="as-input" type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required maxlength="255" autocomplete="given-name">
            </div>
            <div class="as-group">
                <label class="as-label" for="last_name">{{ __('common.form.last_name') }}</label>
                <input class="as-input" type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required maxlength="255" autocomplete="family-name">
            </div>
        </div>
        <div class="as-group">
            <label class="as-label" for="email">{{ __('common.form.email_address') }}</label>
            <input class="as-input" type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
        </div>
        <div class="as-group">
            <label class="as-label" for="password">{{ __('common.form.password') }} <small>{{ __('client.auth.password_hint') }}</small></label>
            <div class="as-pw">
                <input class="as-input" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                <button type="button" data-toggle="password">{{ __('client.auth.show') }}</button>
            </div>
        </div>
        <div class="as-group">
            <label class="as-label" for="password_confirmation">{{ __('common.form.confirm_password') }}</label>
            <div class="as-pw">
                <input class="as-input" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
                <button type="button" data-toggle="password_confirmation">{{ __('client.auth.show') }}</button>
            </div>
        </div>
        <button type="button" class="as-btn" id="next">{{ __('client.auth.continue') }}</button>
        <p class="as-foot">{{ __('client.auth.already_have_account') }} <a href="{{ route('client.login') }}">{{ __('client.auth.sign_in') }}</a></p>
    </section>

    {{-- Step 2: billing address --}}
    <section data-step="2" hidden>
        <p class="as-lead"><strong>{{ __('client.auth.billing_address') }}</strong><br>{{ __('client.auth.billing_address_hint') }}</p>
        <div class="as-group">
            <label class="as-label" for="country">{{ __('client.auth.country') }}</label>
            <select class="as-input" id="country" name="country" required autocomplete="country">
                @foreach($countries as $code => $name)
                    <option value="{{ $code }}" @selected($selectedCountry === $code)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="as-group">
            <label class="as-label" for="address1">{{ __('client.auth.address') }}</label>
            <input class="as-input" type="text" id="address1" name="address1" value="{{ old('address1') }}" required maxlength="255" autocomplete="address-line1">
        </div>
        <div class="as-row">
            <div class="as-group">
                <label class="as-label" for="city">{{ __('client.auth.city') }}</label>
                <input class="as-input" type="text" id="city" name="city" value="{{ old('city') }}" required maxlength="255" autocomplete="address-level2">
            </div>
            <div class="as-group">
                <label class="as-label" for="postcode">{{ __('client.auth.postal_code') }}</label>
                <input class="as-input" type="text" id="postcode" name="postcode" value="{{ old('postcode') }}" required maxlength="20" autocomplete="postal-code">
            </div>
        </div>
        <details class="as-opt" @if(old('company_name') || old('phone_number') || old('address2')) open @endif>
            <summary>{{ __('client.form.optional') }}</summary>
            <div class="as-group" style="margin-top:10px;">
                <label class="as-label" for="company_name">{{ __('client.form.company') }}</label>
                <input class="as-input" type="text" id="company_name" name="company_name" value="{{ old('company_name') }}" maxlength="255" autocomplete="organization">
            </div>
            <div class="as-group">
                <label class="as-label" for="address2">{{ __('client.auth.address_line_2') }}</label>
                <input class="as-input" type="text" id="address2" name="address2" value="{{ old('address2') }}" maxlength="255" autocomplete="address-line2">
            </div>
            <div class="as-group">
                <label class="as-label" for="phone_number">{{ __('client.form.phone') }}</label>
                <input class="as-input" type="tel" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" maxlength="30" autocomplete="tel">
            </div>
        </details>
        <label class="as-tos">
            <input type="checkbox" name="tos" value="1" required @checked(old('tos'))>
            <span>{{ __('client.auth.i_agree_to') }}
                <a href="{{ \App\Models\Setting::get('TOSUrl', '#') }}" target="_blank" rel="noopener">{{ __('client.auth.terms_of_service') }}</a>
                {{ __('client.auth.and') }}
                <a href="{{ \App\Models\Setting::get('PrivacyUrl', '#') }}" target="_blank" rel="noopener">{{ __('client.auth.privacy_policy') }}</a>.</span>
        </label>
        <button type="submit" class="as-btn" id="submit">{{ __('client.auth.create_account') }}</button>
    </section>
</form>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('wizard');
    var steps = form.querySelectorAll('[data-step]');
    var stepsBar = document.getElementById('steps');
    var label = document.getElementById('step-label');
    var back = document.getElementById('back');
    var errBox = document.getElementById('client-error');
    var msgs = {
        required: @json(__('client.auth.err_required')),
        email: @json(__('client.auth.err_email')),
        min: @json(__('client.auth.err_password_min')),
        match: @json(__('client.auth.err_password_match')),
        tos: @json(__('client.auth.err_tos'))
    };

    function show(n) {
        steps.forEach(function (s) { s.hidden = String(s.dataset.step) !== String(n); });
        stepsBar.classList.toggle('s2', n === 2);
        label.textContent = n + ' / 2';
        back.hidden = n === 1;
        form.dataset.current = n;
        var first = form.querySelector('[data-step="' + n + '"] input, [data-step="' + n + '"] select');
        if (first) { first.focus({ preventScroll: true }); }
    }

    function fail(fields, text) {
        fields.forEach(function (f) { f.classList.add('invalid'); });
        errBox.textContent = text; errBox.hidden = false;
        fields[0].focus();
    }

    function validate(n) {
        errBox.hidden = true;
        var scope = form.querySelector('[data-step="' + n + '"]');
        var inputs = scope.querySelectorAll('input[required], select[required]');
        inputs.forEach(function (i) { i.classList.remove('invalid'); });
        for (var i = 0; i < inputs.length; i++) {
            var f = inputs[i];
            if (f.type === 'checkbox' ? !f.checked : !f.value.trim()) {
                fail([f], f.type === 'checkbox' ? msgs.tos : msgs.required); return false;
            }
            if (f.type === 'email' && !f.checkValidity()) { fail([f], msgs.email); return false; }
        }
        if (n === 1) {
            var pw = form.password, pc = form.password_confirmation;
            if (pw.value.length < 8) { fail([pw], msgs.min); return false; }
            if (pw.value !== pc.value) { fail([pw, pc], msgs.match); return false; }
        }
        return true;
    }

    document.getElementById('next').addEventListener('click', function () { if (validate(1)) { show(2); } });
    back.addEventListener('click', function () { errBox.hidden = true; show(1); });
    form.addEventListener('submit', function (e) {
        if (!validate(1)) { e.preventDefault(); show(1); return; }
        if (!validate(2)) { e.preventDefault(); show(2); return; }
        document.getElementById('submit').disabled = true;
    });
    // Enter on step 1 advances instead of submitting a half-filled form.
    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && form.dataset.current === '1' && e.target.tagName !== 'BUTTON') {
            e.preventDefault(); document.getElementById('next').click();
        }
    });
    form.querySelectorAll('[data-toggle]').forEach(function (b) {
        b.addEventListener('click', function () {
            var input = document.getElementById(b.dataset.toggle);
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            b.textContent = reveal ? @json(__('client.auth.hide')) : @json(__('client.auth.show'));
        });
    });
    show(parseInt(form.dataset.start, 10) || 1);
})();
</script>
@endpush
