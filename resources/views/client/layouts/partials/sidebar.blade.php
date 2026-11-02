{{-- Signed-in navigation: a left sidebar in three levels (home, services,
     organization). Every link points at a route that already exists; guests
     keep the top navbar of the storefront. --}}
@php
    $nscBrand = $nscBrand ?? \App\Support\ClientBrand::forCurrent();
    $nscName = $nscBrand['name'] !== '' ? $nscBrand['name'] : company_name();
    $nscLogo = $nscBrand['logo'] !== '' ? $nscBrand['logo'] : ($customLogo ?? '');
    $nscUser = auth()->user();
    $nscAccounts = $nscUser->clients()->orderBy('id')->get();
    $nscGroups = [
        ['key' => 'home', 'items' => [
            ['client.home', 'client.home', __('client.nav.dashboard')],
        ]],
        ['key' => 'services', 'items' => [
            ['client.services.index', 'client.services.*', __('client.nav.my_services')],
            ['client.wizard.servers', 'client.wizard.*', __('portal.wizard.nav')],
            ['client.store', 'client.store*', __('client.nav.order_new_service')],
            ['client.domains.index', 'client.domains.*', __('client.nav.my_domains')],
            ['client.ssl.index', 'client.ssl.*', __('client.nav.ssl')],
        ]],
        ['key' => 'billing', 'items' => [
            ['client.invoices.index', 'client.invoices.*', __('client.nav.invoices')],
            ['client.funds.index', 'client.funds.*', __('client.nav.add_funds')],
            ['client.quotes.index', 'client.quotes.*', __('client.nav.quotes')],
            ['client.payment-methods.index', 'client.payment-methods.*', __('client.nav.payment_methods')],
            ['client.emails.index', 'client.emails.*', __('client.nav.email_history')],
        ]],
        ['key' => 'support', 'items' => [
            ['client.tickets.index', 'client.tickets.*', __('client.nav.my_tickets')],
            ['client.kb.index', 'client.kb.*', __('client.nav.knowledge_base')],
            ['client.announcements.index', 'client.announcements.*', __('client.nav.announcements')],
            ['client.downloads.index', 'client.downloads.*', __('client.nav.downloads')],
            ['client.network-status', 'client.network-status', __('client.nav.network_status')],
        ]],
        ['key' => 'organization', 'items' => [
            ['client.account.profile', 'client.account.profile', __('client.nav.edit_profile')],
            ['client.account.contacts', 'client.account.contacts', __('client.nav.contacts')],
            ['client.account.security', 'client.account.security', __('client.nav.security')],
            ['client.branding.edit', 'client.branding.*', __('portal.brand.nav')],
            ['client.affiliates.index', 'client.affiliates.*', __('client.nav.affiliates')],
        ]],
    ];
@endphp
<aside class="nsc-side" id="nscSide" aria-label="{{ __('portal.menu') }}">
    <a href="{{ route('client.home') }}" class="nsc-brand">
        @if($nscLogo !== '')
            <img src="{{ $nscLogo }}" alt="{{ $nscName }}">
        @else
            <span class="nsc-mark">{{ strtoupper(mb_substr($nscName, 0, 1)) }}</span><span>{{ $nscName }}</span>
        @endif
    </a>

    @if($nscAccounts->count() > 1)
    <div class="nsc-acct">
        <label for="nscAcct">{{ __('portal.account_switch') }}</label>
        <select id="nscAcct" onchange="if(this.value){var f=document.getElementById('nscSwitch');f.action=this.value;f.submit();}">
            @foreach($nscAccounts as $acct)
                <option value="{{ route('client.account.switch', $acct) }}" @selected((int) session('active_client_id', $nscAccounts->first()->id) === (int) $acct->id)>{{ $acct->company_name ?: $acct->first_name.' '.$acct->last_name }}</option>
            @endforeach
        </select>
    </div>
    @endif

    <nav class="nsc-nav">
        @foreach($nscGroups as $group)
            <div class="nsc-grp">{{ __('portal.group.'.$group['key']) }}</div>
            @foreach($group['items'] as [$route, $pattern, $label])
                @continue(! \Illuminate\Support\Facades\Route::has($route))
                <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}" @if(request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        @endforeach
    </nav>

    <div class="nsc-user">
        <a href="{{ route('client.account.profile') }}" class="nsc-user-card">
            <span class="nsc-av">{{ strtoupper(mb_substr($nscUser->first_name ?? 'U', 0, 1)) }}</span>
            <span class="nsc-user-name">{{ $nscUser->first_name }} {{ $nscUser->last_name }}<small>{{ $nscUser->email }}</small></span>
        </a>
        <form method="POST" action="{{ route('client.logout') }}">
            @csrf
            <button type="submit" class="nsc-logout">{{ __('client.nav.sign_out') }}</button>
        </form>
    </div>
</aside>
<form id="nscSwitch" method="POST" action="" style="display:none">@csrf</form>
