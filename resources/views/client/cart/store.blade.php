@extends("client.layouts.app")
@section("title", __("client.order_a_new_product"))
@section("content")
@php
    use App\Support\StoreOffering;

    // Every product the store shows, with the kind of offer and the platform it runs on.
    $rows = [];
    foreach ($groups as $group) {
        foreach ($group->products as $product) {
            $rows[] = ['p' => $product, 'kind' => StoreOffering::kind($product), 'prov' => StoreOffering::provider($product)];
        }
    }
    $count = fn (string $field, string $key) => count(array_filter($rows, fn ($r) => $r[$field] === $key));
    $kinds = array_filter(StoreOffering::KINDS, fn ($m, $k) => $count('kind', $k) > 0, ARRAY_FILTER_USE_BOTH);
    $total = count($rows);
@endphp

<style>
.stx{--bg:#0b1020;--glass:rgba(255,255,255,.05);--line:rgba(255,255,255,.12);--ink:#eef2ff;--dim:#9aa6c7;
    position:relative;isolation:isolate;overflow:hidden;border-radius:24px;padding:40px 36px 48px;color:var(--ink);background:var(--bg)}
.stx *{box-sizing:border-box}
.stx::before,.stx::after{content:"";position:absolute;z-index:-1;border-radius:50%;filter:blur(90px);opacity:.55;animation:stxfloat 16s ease-in-out infinite alternate}
.stx::before{width:520px;height:520px;left:-120px;top:-140px;background:#3b82f6}
.stx::after{width:460px;height:460px;right:-100px;top:180px;background:#a855f7;animation-delay:-6s}
@keyframes stxfloat{to{transform:translate3d(60px,40px,0) scale(1.1)}}
@media(prefers-reduced-motion:reduce){.stx::before,.stx::after{animation:none}}
.stx-hero{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;margin-bottom:36px}
.stx-hero h1{font-size:clamp(28px,4.4vw,46px);font-weight:800;letter-spacing:-1.4px;line-height:1.05;max-width:720px;
    background:linear-gradient(100deg,#fff 20%,#93c5fd 55%,#d8b4fe);-webkit-background-clip:text;background-clip:text;color:transparent}
.stx-hero p{margin-top:12px;color:var(--dim);font-size:15px;line-height:1.65;max-width:560px}
.stx-pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}
.stx-pill{font-size:12px;font-weight:600;padding:6px 12px;border-radius:999px;border:1px solid var(--line);background:var(--glass);backdrop-filter:blur(8px)}
.stx-cart{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:12px;background:#fff;color:#0b1020;font-weight:700;text-decoration:none;font-size:13px}
.stx h2.lbl{font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--dim);margin:0 0 4px}
.stx .sub{font-size:13px;color:var(--dim);margin-bottom:16px}
.stx-block{margin-bottom:34px}

/* platform tiles */
.stx-pg{margin-bottom:14px}
.stx-pg>span{display:block;font-size:11px;font-weight:700;color:var(--dim);margin:0 0 8px 2px}
.stx-tiles{display:flex;flex-wrap:wrap;gap:10px}
.stx-tile{--c:#60a5fa;display:flex;align-items:center;gap:10px;padding:10px 16px 10px 10px;border-radius:16px;border:1px solid var(--line);background:var(--glass);
    color:var(--ink);cursor:pointer;font:inherit;font-weight:600;font-size:13.5px;transition:transform .18s,border-color .18s,background .18s,box-shadow .18s;backdrop-filter:blur(8px)}
.stx-tile:hover:not([disabled]){transform:translateY(-2px);border-color:var(--c)}
.stx-tile.on{border-color:var(--c);background:color-mix(in srgb,var(--c) 22%,transparent);box-shadow:0 0 0 1px var(--c),0 10px 30px -10px var(--c)}
.stx-tile[disabled]{opacity:.45;cursor:not-allowed}
.stx-mark{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;background:var(--c);letter-spacing:-.3px}
.stx-tile small{display:block;font-size:10.5px;font-weight:600;color:var(--dim);margin-top:1px}
.stx-tile .dot{width:7px;height:7px;border-radius:50%;background:#34d399;display:inline-block;margin-right:4px}
.stx-tile[disabled] .dot{background:#64748b}

/* kind bento */
.stx-bento{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}
.stx-k{--c:#60a5fa;position:relative;overflow:hidden;text-align:left;padding:18px;border-radius:20px;border:1px solid var(--line);color:var(--ink);cursor:pointer;font:inherit;min-height:132px;
    background:linear-gradient(150deg,color-mix(in srgb,var(--c) 30%,transparent),rgba(255,255,255,.03) 62%);transition:transform .18s,box-shadow .18s,border-color .18s}
.stx-k:hover{transform:translateY(-3px)}
.stx-k.on{border-color:var(--c);box-shadow:0 0 0 1px var(--c),0 16px 40px -14px var(--c)}
.stx-k i{font-size:30px;color:var(--c);display:block;margin-bottom:12px}
.stx-k b{display:block;font-size:16px;letter-spacing:-.2px}
.stx-k span{display:block;font-size:12.5px;color:var(--dim);margin-top:4px;line-height:1.5}
.stx-k em{position:absolute;right:14px;top:12px;font-style:normal;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:rgba(255,255,255,.1)}
.stx-k::after{content:"";position:absolute;right:-30px;bottom:-30px;width:110px;height:110px;border-radius:50%;background:var(--c);opacity:.18;filter:blur(8px)}
.stx .c-blue{--c:#60a5fa}.stx .c-teal{--c:#2dd4bf}.stx .c-violet{--c:#a78bfa}.stx .c-amber{--c:#fbbf24}.stx .c-rose{--c:#fb7185}.stx .c-slate{--c:#94a3b8}.stx .c-gray{--c:#a1a1aa}

/* offers */
.stx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;grid-auto-flow:dense}
.stx-o{--c:#60a5fa;position:relative;display:flex;flex-direction:column;padding:22px;border-radius:22px;background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.02));
    border:1px solid var(--line);transition:transform .2s,box-shadow .2s,border-color .2s;overflow:hidden}
.stx-o::before{content:"";position:absolute;inset:0 0 auto 0;height:3px;background:linear-gradient(90deg,var(--c),transparent)}
.stx-o:hover{transform:translateY(-4px);border-color:var(--c);box-shadow:0 22px 50px -20px var(--c)}
.stx-o.feat{grid-column:span 2;background:linear-gradient(135deg,color-mix(in srgb,var(--c) 26%,transparent),rgba(255,255,255,.03))}
@media(max-width:640px){.stx-o.feat{grid-column:auto}.stx{padding:26px 18px 32px}.stx-bento{grid-template-columns:1fr 1fr}}
.stx-o .top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
.stx-o .ico{width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:21px;color:var(--c);background:color-mix(in srgb,var(--c) 18%,transparent)}
.stx-o .where{font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;border:1px solid var(--line);color:var(--dim)}
.stx-o .star{font-size:10.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;padding:4px 10px;border-radius:999px;background:linear-gradient(90deg,#f59e0b,#ec4899);color:#fff}
.stx-o .kindlbl{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--c);margin-bottom:4px}
.stx-o h3{font-size:19px;font-weight:800;letter-spacing:-.4px;color:var(--ink)}
.stx-o .desc{font-size:13px;color:var(--dim);line-height:1.6;margin:6px 0 14px}
.stx-specs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:18px;flex:1;align-content:flex-start}
.stx-spec{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:5px 10px;border-radius:10px;background:rgba(255,255,255,.07);color:var(--ink)}
.stx-spec i{color:var(--c);font-size:14px}
.stx-foot{display:flex;align-items:flex-end;justify-content:space-between;gap:12px}
.stx-price{font-size:28px;font-weight:800;letter-spacing:-.8px;line-height:1}
.stx-price small{font-size:12px;font-weight:600;color:var(--dim);letter-spacing:0}
.stx-price .from{display:block;font-size:11px;font-weight:600;color:var(--dim);margin-bottom:3px;letter-spacing:0}
.stx-go{display:inline-flex;align-items:center;gap:6px;padding:11px 18px;border-radius:12px;font-weight:700;font-size:13.5px;text-decoration:none;color:#0b1020;background:var(--c);transition:transform .15s,filter .15s}
.stx-go:hover{transform:translateX(2px);filter:brightness(1.1)}
.stx-go.off{background:rgba(255,255,255,.1);color:var(--dim);cursor:default}
.stx-sec{margin-bottom:30px}
.stx-sec[hidden],.stx-o[hidden]{display:none}
.stx-sec-h{display:flex;align-items:center;gap:10px;margin:0 0 14px;font-size:17px;font-weight:800;letter-spacing:-.3px}
.stx-sec-h i{color:var(--c);font-size:20px}
.stx-empty{text-align:center;padding:40px 16px;border:1px dashed var(--line);border-radius:18px;color:var(--dim)}
.stx-empty button{margin-top:12px;padding:9px 16px;border-radius:10px;border:0;background:#fff;color:#0b1020;font-weight:700;cursor:pointer}
</style>

<div class="stx">
    <div class="stx-hero">
        <div>
            <h1>{{ __('store.hero_title') }}</h1>
            <p>{{ __('store.hero_subtitle') }}</p>
            <div class="stx-pills">
                @foreach(__('store.pills') as $pill)<span class="stx-pill">{{ $pill }}</span>@endforeach
            </div>
        </div>
        @if(!empty($cart) && count($cart) > 0)
        <a href="{{ route('client.cart.index') }}" class="stx-cart"><i class="ri-shopping-cart-2-line"></i>{{ __('client.cart.view_cart') }}</a>
        @endif
    </div>

    @if(session('error'))<div class="pn-alert pn-alert-error mb-16">{{ session('error') }}</div>@endif

    @if($total === 0)
        <div class="stx-empty">{{ __('client.store.no_products_at_all') }}</div>
    @else
    {{-- Where it runs --}}
    <div class="stx-block">
        <h2 class="lbl">{{ __('store.where') }}</h2>
        <p class="sub">{{ __('store.where_sub') }}</p>
        @foreach(StoreOffering::PROVIDERS as $group => $items)
        <div class="stx-pg">
            <span>{{ __("store.provider_group.$group") }}</span>
            <div class="stx-tiles">
                @foreach($items as $key => $meta)
                @php $n = $count('prov', $key); @endphp
                <button type="button" class="stx-tile" style="--c:{{ $meta['color'] }}" data-prov="{{ $key }}" @disabled($n === 0)>
                    <span class="stx-mark">{{ $meta['mark'] }}</span>
                    <span>{{ $meta['name'] }}<small><i class="dot"></i>{{ $n > 0 ? $n.' · '.__('store.live') : __('store.soon') }}</small></span>
                </button>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- What you need --}}
    <div class="stx-block">
        <h2 class="lbl">{{ __('store.what') }}</h2>
        <div class="stx-bento" style="margin-top:12px">
            @foreach($kinds as $k => $meta)
            <button type="button" class="stx-k c-{{ $meta['tone'] }}" data-kind="{{ $k }}">
                <em>{{ $count('kind', $k) }}</em>
                <i class="{{ $meta['icon'] }}"></i>
                <b>{{ __("store.kind.$k.name") }}</b>
                @if(__("store.kind.$k.blurb") !== '')<span>{{ __("store.kind.$k.blurb") }}</span>@endif
            </button>
            @endforeach
        </div>
    </div>

    {{-- Offers --}}
    <div id="stx-offers">
    <div class="stx-grid">
    @foreach($kinds as $k => $meta)
        @foreach(array_filter($rows, fn ($r) => $r['kind'] === $k) as $r)
            @php
                $product = $r['p'];
                $pricedCycles = $product->pricedCycles($currency?->id);
                $cycle = (string) array_key_first($pricedCycles);
                $price = $pricedCycles[$cycle] ?? null;
                $provMeta = collect(StoreOffering::PROVIDERS)->flatMap(fn ($i) => $i)->get($r['prov']);
            @endphp
            <article class="stx-o c-{{ $meta['tone'] }} {{ $product->is_featured ? 'feat' : '' }}" data-kind="{{ $k }}" data-prov="{{ $r['prov'] }}">
                <div class="top">
                    <div class="ico"><i class="{{ $meta['icon'] }}"></i></div>
                    @if($product->is_featured)<span class="star">{{ __('client.store.most_popular') }}</span>
                    @elseif($provMeta)<span class="where">{{ $provMeta['name'] }}</span>@endif
                </div>
                <div class="kindlbl">{{ __("store.kind.$k.name") }}</div>
                <h3>{{ $product->name }}</h3>
                @if($product->description)<div class="desc">{{ Str::limit(strip_tags($product->description), 120) }}</div>@endif
                <div class="stx-specs">
                    @foreach(StoreOffering::specs($product) as $s)<span class="stx-spec"><i class="{{ $s['icon'] }}"></i>{{ $s['text'] }}</span>@endforeach
                </div>
                <div class="stx-foot">
                    @if($price !== null && $product->pay_type === 'free')
                        <div class="stx-price">{{ __('common.billing.free') }}</div>
                    @elseif($price !== null)
                        <div class="stx-price"><span class="from">{{ __('store.from') }}</span>{{ display_money_fmt($price) }} <small>/{{ $cycle }}</small></div>
                    @else
                        <div class="stx-price" style="font-size:16px">{{ __('client.store.contact_us') }}</div>
                    @endif
                    @if($product->outOfStock())
                        <span class="stx-go off">{{ __('client.cart.out_of_stock') }}</span>
                    @else
                        <a class="stx-go" href="{{ route('client.store.configure', $product) }}">{{ __('store.configure') }} <i class="ri-arrow-right-line"></i></a>
                    @endif
                </div>
            </article>
        @endforeach
    @endforeach
    </div>
    <div class="stx-empty" id="stx-none" hidden>
        {{ __('store.no_match') }}<br><button type="button" id="stx-reset">{{ __('store.reset') }}</button>
    </div>
    @endif
</div>

<script>
(function () {
    var prov = null, kind = null;
    var tiles = document.querySelectorAll('.stx-tile'), kinds = document.querySelectorAll('.stx-k');
    function apply() {
        var shown = 0;
        document.querySelectorAll('.stx-o').forEach(function (o) {
            var ok = (!prov || o.dataset.prov === prov) && (!kind || o.dataset.kind === kind);
            o.hidden = !ok; if (ok) shown++;
        });
        tiles.forEach(function (t) { t.classList.toggle('on', t.dataset.prov === prov); });
        kinds.forEach(function (k) { k.classList.toggle('on', k.dataset.kind === kind); });
        var none = document.getElementById('stx-none'); if (none) none.hidden = shown > 0;
    }
    tiles.forEach(function (t) { t.addEventListener('click', function () { prov = prov === t.dataset.prov ? null : t.dataset.prov; apply(); }); });
    kinds.forEach(function (k) { k.addEventListener('click', function () { kind = kind === k.dataset.kind ? null : k.dataset.kind; apply(); }); });
    var r = document.getElementById('stx-reset'); if (r) r.addEventListener('click', function () { prov = kind = null; apply(); });
})();
</script>

@endsection
