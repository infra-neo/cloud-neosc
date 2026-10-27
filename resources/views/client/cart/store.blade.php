@extends("client.layouts.app")
@section("title", __("client.order_a_new_product"))
@section("content")
@php
    use App\Support\StoreOffering;

    // Every product the store shows, sorted into the kinds of offer NeoSC sells.
    $byKind = [];
    foreach ($groups as $group) {
        foreach ($group->products as $product) {
            $byKind[StoreOffering::kind($product)][] = ['product' => $product, 'group' => $group];
        }
    }
    $kinds = array_filter(StoreOffering::KINDS, fn ($meta, $k) => ! empty($byKind[$k]), ARRAY_FILTER_USE_BOTH);
    $total = array_sum(array_map('count', $byKind));
@endphp

<style>
    .st-hero{border-radius:16px;padding:34px 32px;margin-bottom:22px;color:#fff;position:relative;overflow:hidden;
        background:radial-gradient(900px 300px at 85% -20%,rgba(255,255,255,.18),transparent 60%),linear-gradient(135deg,var(--primary-dark),var(--primary) 60%,#2b6cb0)}
    .st-hero h1{font-size:28px;font-weight:800;letter-spacing:-.6px;margin-bottom:6px}
    .st-hero p{font-size:14.5px;opacity:.88;max-width:620px;line-height:1.6}
    .st-hero .st-cart{position:absolute;top:22px;right:24px}
    .st-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:26px}
    .st-chip{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:999px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:13px;font-weight:600;cursor:pointer;transition:all .15s}
    .st-chip:hover{border-color:var(--primary);color:var(--primary)}
    .st-chip.on{background:var(--primary);border-color:var(--primary);color:#fff}
    .st-chip i{font-size:15px}
    .st-chip .n{font-size:11px;opacity:.75;font-weight:700}
    .st-sec{margin-bottom:34px}
    .st-sec[hidden]{display:none}
    .st-sec-head{display:flex;align-items:center;gap:14px;margin-bottom:14px}
    .st-ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex:0 0 auto}
    .st-sec-head h2{font-size:18px;font-weight:800;letter-spacing:-.3px}
    .st-sec-head p{font-size:13px;color:var(--muted);margin-top:2px}
    .st-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:16px}
    .st-card{display:flex;flex-direction:column;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px;position:relative;transition:transform .15s,box-shadow .15s,border-color .15s}
    .st-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);border-color:var(--primary)}
    .st-card.feat{border-color:var(--primary);box-shadow:0 0 0 1px var(--primary) inset}
    .st-badge{position:absolute;top:14px;right:14px;background:var(--accent);color:#fff;font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:999px;text-transform:uppercase;letter-spacing:.3px}
    .st-card h3{font-size:16px;font-weight:700;margin:12px 0 4px;color:var(--text)}
    .st-card .desc{font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:12px}
    .st-specs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;flex:1;align-content:flex-start}
    .st-spec{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:4px 9px;border-radius:8px;background:var(--bg);border:1px solid var(--border);color:var(--text)}
    .st-spec i{color:var(--primary);font-size:14px}
    .st-price{font-size:24px;font-weight:800;letter-spacing:-.5px;margin-bottom:12px}
    .st-price small{font-size:12px;font-weight:500;color:var(--muted)}
    .st-price .from{font-size:12px;font-weight:600;color:var(--muted);margin-right:4px}
    .tone-blue{background:#e0ecff;color:#1d4ed8}.tone-teal{background:#d5f5ee;color:#0f766e}.tone-violet{background:#ece4ff;color:#6d28d9}
    .tone-amber{background:#fdf0d0;color:#b45309}.tone-rose{background:#fde2e8;color:#be123c}.tone-slate{background:#e2e8f0;color:#334155}.tone-gray{background:#eceff3;color:#475569}
    [data-theme=dark] .st-ico{filter:brightness(.9) saturate(.9)}
    @media(max-width:640px){.st-hero{padding:24px 20px}.st-hero .st-cart{position:static;margin-top:14px}}
</style>

<div class="st-hero">
    <h1>{{ __('store.hero_title') }}</h1>
    <p>{{ __('store.hero_subtitle') }}</p>
    @if(!empty($cart) && count($cart) > 0)
    <a href="{{ route("client.cart.index") }}" class="btn btn-outline st-cart" style="background:#fff">
        <i class="ri-shopping-cart-2-line"></i> {{ __('client.cart.view_cart') }}
    </a>
    @endif
</div>

@if(session("error"))
<div class="pn-alert pn-alert-error mb-16">{{ session("error") }}</div>
@endif

@if($total === 0)
<div class="pn-card">
    <div class="pn-empty">
        <div class="pn-empty-icon">&#128722;</div>
        <p>{{ __('client.store.no_products_at_all') }}</p>
    </div>
</div>
@else
@if(count($kinds) > 1)
<div class="st-filters" role="tablist">
    <button type="button" class="st-chip on" data-kind="all"><i class="ri-apps-2-line"></i>{{ __('store.all') }} <span class="n">{{ $total }}</span></button>
    @foreach($kinds as $k => $meta)
    <button type="button" class="st-chip" data-kind="{{ $k }}"><i class="{{ $meta['icon'] }}"></i>{{ __("store.kind.$k.name") }} <span class="n">{{ count($byKind[$k]) }}</span></button>
    @endforeach
</div>
@endif

@foreach($kinds as $k => $meta)
<section class="st-sec" data-section="{{ $k }}">
    <div class="st-sec-head">
        <div class="st-ico tone-{{ $meta['tone'] }}"><i class="{{ $meta['icon'] }}"></i></div>
        <div>
            <h2>{{ __("store.kind.$k.name") }}</h2>
            @if(__("store.kind.$k.blurb") !== '')<p>{{ __("store.kind.$k.blurb") }}</p>@endif
        </div>
    </div>
    <div class="st-grid">
        @foreach($byKind[$k] as $row)
        @php
            $product = $row['product'];
            $pricedCycles = $product->pricedCycles($currency?->id);
            $startingCycle = (string) array_key_first($pricedCycles);
            $startingPrice = $pricedCycles[$startingCycle] ?? null;
            $specs = StoreOffering::specs($product);
        @endphp
        <div class="st-card {{ $product->is_featured ? 'feat' : '' }}">
            @if($product->is_featured)<span class="st-badge">{{ __('client.store.most_popular') }}</span>@endif
            <div class="st-ico tone-{{ $meta['tone'] }}" style="width:38px;height:38px;font-size:19px"><i class="{{ $meta['icon'] }}"></i></div>
            <h3>{{ $product->name }}</h3>
            @if($product->description)
                <div class="desc">{{ Str::limit(strip_tags($product->description), 110) }}</div>
            @endif
            <div class="st-specs">
                @foreach($specs as $s)<span class="st-spec"><i class="{{ $s['icon'] }}"></i>{{ $s['text'] }}</span>@endforeach
            </div>
            @if($startingPrice !== null && $product->pay_type === 'free')
                <div class="st-price">{{ __('common.billing.free') }}</div>
            @elseif($startingPrice !== null)
                <div class="st-price"><span class="from">{{ __('store.from') }}</span>{{ display_money_fmt($startingPrice) }} <small>/{{ $startingCycle }}</small></div>
            @else
                <div class="st-price" style="font-size:16px">{{ __('client.store.contact_us') }}</div>
            @endif
            @if($product->outOfStock())
                <span class="btn btn-outline" style="justify-content:center;opacity:.65;cursor:default">{{ __('client.cart.out_of_stock') }}</span>
            @else
                <a href="{{ route('client.store.configure', $product) }}" class="btn {{ $product->is_featured ? 'btn-accent' : 'btn-primary' }}" style="justify-content:center">{{ __('store.configure') }} &rarr;</a>
            @endif
        </div>
        @endforeach
    </div>
</section>
@endforeach

<script>
(function () {
    var chips = document.querySelectorAll('.st-chip');
    chips.forEach(function (c) {
        c.addEventListener('click', function () {
            var k = c.getAttribute('data-kind');
            chips.forEach(function (o) { o.classList.toggle('on', o === c); });
            document.querySelectorAll('.st-sec').forEach(function (s) {
                s.hidden = !(k === 'all' || s.getAttribute('data-section') === k);
            });
        });
    });
})();
</script>
@endif

@endsection
