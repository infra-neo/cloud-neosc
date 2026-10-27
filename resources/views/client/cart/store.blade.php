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
    usort($rows, fn ($a, $b) => array_search($a['kind'], array_keys(StoreOffering::KINDS)) <=> array_search($b['kind'], array_keys(StoreOffering::KINDS)));
    $count = fn (string $field, string $key) => count(array_filter($rows, fn ($r) => $r[$field] === $key));
    $kinds = array_filter(StoreOffering::KINDS, fn ($m, $k) => $count('kind', $k) > 0, ARRAY_FILTER_USE_BOTH);
    $total = count($rows);

    // What the browser needs to show and price each plan.
    $plans = [];
    foreach ($rows as $r) {
        $product = $r['p'];
        $meta = StoreOffering::KINDS[$r['kind']];
        $conf = $configurator[$product->id] ?? ['cycles' => [], 'options' => []];
        $plans[$product->id] = [
            'id' => $product->id, 'name' => $product->name, 'kind' => $r['kind'], 'prov' => $r['prov'],
            'tone' => $meta['tone'], 'icon' => $meta['icon'], 'featured' => (bool) $product->is_featured,
            'free' => $product->pay_type === 'free', 'soldOut' => (bool) $product->outOfStock(),
            'desc' => Str::limit(strip_tags((string) $product->description), 160),
            'specs' => array_map(fn ($s) => ['icon' => $s['icon'], 'text' => $s['text']], StoreOffering::specs($product)),
            'url' => route('client.store.configure', $product),
            'cycles' => $conf['cycles'], 'options' => $conf['options'],
        ];
    }
    $providers = collect(StoreOffering::PROVIDERS)->flatMap(fn ($i) => $i)->map(fn ($m) => $m['name'])->all();
@endphp

<style>
.stx{--bg:#0b1020;--glass:rgba(255,255,255,.05);--line:rgba(255,255,255,.12);--ink:#eef2ff;--dim:#9aa6c7;
    position:relative;isolation:isolate;overflow:hidden;border-radius:24px;padding:36px 32px 28px;color:var(--ink);background:var(--bg)}
.stx *{box-sizing:border-box}
.stx::before,.stx::after{content:"";position:absolute;z-index:-1;border-radius:50%;filter:blur(90px);opacity:.5;animation:stxfloat 16s ease-in-out infinite alternate}
.stx::before{width:520px;height:520px;left:-120px;top:-140px;background:#3b82f6}
.stx::after{width:460px;height:460px;right:-100px;top:340px;background:#a855f7;animation-delay:-6s}
@keyframes stxfloat{to{transform:translate3d(60px,40px,0) scale(1.1)}}
@media(prefers-reduced-motion:reduce){.stx::before,.stx::after{animation:none}}
.stx-hero{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;margin-bottom:28px}
.stx-hero h1{font-size:clamp(26px,4vw,40px);font-weight:800;letter-spacing:-1.2px;line-height:1.08;max-width:700px;background:linear-gradient(100deg,#fff 20%,#93c5fd 55%,#d8b4fe);-webkit-background-clip:text;background-clip:text;color:transparent}
.stx-hero p{margin-top:10px;color:var(--dim);font-size:14.5px;line-height:1.6;max-width:540px}
.stx-cart{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:12px;background:#fff;color:#0b1020;font-weight:700;text-decoration:none;font-size:13px}
.stx-step{display:flex;align-items:center;gap:12px;margin:0 0 14px}
.stx-step b{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;background:linear-gradient(135deg,#3b82f6,#a855f7);color:#fff;flex:0 0 auto}
.stx-step h2{font-size:17px;font-weight:800;letter-spacing:-.3px}
.stx-block{margin-bottom:30px}

.stx-pills{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.stx-pill{--c:#60a5fa;display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:999px;border:1px solid var(--line);background:var(--glass);color:var(--ink);font:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s}
.stx-pill i{color:var(--c);font-size:16px}
.stx-pill:hover{border-color:var(--c)}
.stx-pill.on{background:color-mix(in srgb,var(--c) 24%,transparent);border-color:var(--c);box-shadow:0 0 0 1px var(--c)}
.stx-tiles{display:flex;flex-wrap:wrap;gap:8px}
.stx-tile{--c:#60a5fa;display:flex;align-items:center;gap:9px;padding:7px 14px 7px 8px;border-radius:14px;border:1px solid var(--line);background:var(--glass);color:var(--ink);cursor:pointer;font:inherit;font-weight:600;font-size:13px;transition:all .15s}
.stx-tile:hover:not([disabled]){border-color:var(--c);transform:translateY(-1px)}
.stx-tile.on{border-color:var(--c);background:color-mix(in srgb,var(--c) 22%,transparent);box-shadow:0 0 0 1px var(--c)}
.stx-tile[disabled]{opacity:.4;cursor:not-allowed}
.stx-mark{width:28px;height:28px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;background:var(--c)}
.stx-tile small{display:block;font-size:10px;font-weight:600;color:var(--dim)}
.stx-pg{margin-top:12px}.stx-pg>span{display:block;font-size:10.5px;font-weight:700;color:var(--dim);margin:0 0 6px 2px;letter-spacing:.06em;text-transform:uppercase}

/* step 2: plan cards */
.stx-plans{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}
.stx-plan{--c:#60a5fa;position:relative;text-align:left;font:inherit;color:var(--ink);padding:16px;border-radius:18px;border:1px solid var(--line);cursor:pointer;
    background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.02));transition:transform .18s,box-shadow .18s,border-color .18s}
.stx-plan:hover{transform:translateY(-3px);border-color:var(--c)}
.stx-plan.on{background:linear-gradient(150deg,var(--c),color-mix(in srgb,var(--c) 45%,#0b1020));border-color:var(--c);box-shadow:0 18px 40px -16px var(--c);color:#06101f}
.stx-plan[hidden]{display:none}
.stx-plan .k{display:flex;align-items:center;gap:6px;font-size:10.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--c)}
.stx-plan.on .k,.stx-plan.on .pr small,.stx-plan.on .mini{color:rgba(6,16,31,.78)}
.stx-plan h3{font-size:16px;font-weight:800;letter-spacing:-.3px;margin:8px 0 6px}
.stx-plan .pr{font-size:20px;font-weight:800;letter-spacing:-.5px}
.stx-plan .pr small{font-size:11px;font-weight:600;color:var(--dim);letter-spacing:0}
.stx-plan .mini{font-size:11.5px;color:var(--dim);margin-top:8px;line-height:1.5}
.stx-plan .star{position:absolute;top:-9px;right:12px;font-size:9.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;padding:3px 9px;border-radius:999px;background:linear-gradient(90deg,#f59e0b,#ec4899);color:#fff}
.stx-plan .chk{position:absolute;top:12px;right:12px;width:20px;height:20px;border-radius:50%;background:#06101f;color:#fff;display:none;align-items:center;justify-content:center;font-size:12px}
.stx-plan.on .chk{display:flex}

/* step 3: configurator */
.stx-conf{display:grid;grid-template-columns:1.1fr 1fr 1fr;gap:18px;align-items:stretch;border:1px solid var(--line);border-radius:22px;padding:22px;background:rgba(255,255,255,.035);--c:#60a5fa}
@media(max-width:980px){.stx-conf{grid-template-columns:1fr}}
.stx-ctl{margin-bottom:18px}
.stx-ctl .row{display:flex;justify-content:space-between;align-items:baseline;gap:8px;font-size:13px;margin-bottom:8px}
.stx-ctl .row b{font-weight:700}
.stx-ctl .row span{color:var(--c);font-weight:800}
.stx-ctl .row small{color:var(--dim);font-weight:600;margin-left:6px}
.stx-ctl input[type=range]{width:100%;accent-color:var(--c);height:6px}
.stx-ctl input[type=number]{width:100%;padding:9px 12px;border-radius:10px;border:1px solid var(--line);background:rgba(255,255,255,.06);color:var(--ink);font:inherit}
.stx-ctl .sw{display:flex;align-items:center;gap:10px;font-size:13px;cursor:pointer}
.stx-ctl .sw input{accent-color:var(--c);width:18px;height:18px}
.stx-gauge{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:8px}
.stx-gauge svg{width:100%;max-width:230px}
.stx-gauge .nm{font-size:20px;font-weight:800;letter-spacing:-.4px}
.stx-gauge .ds{font-size:12.5px;color:var(--dim);line-height:1.55;max-width:260px}
.stx-inc{border-radius:18px;padding:18px;background:linear-gradient(160deg,color-mix(in srgb,var(--c) 30%,#0b1020),#0b1530);border:1px solid var(--line)}
.stx-inc h4{font-size:15px;font-weight:800;margin-bottom:10px}
.stx-inc .li{display:flex;justify-content:space-between;gap:10px;font-size:12.5px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.1)}
.stx-inc .li span:first-child{color:var(--dim)}
.stx-inc .li span:last-child{font-weight:700;text-align:right}
.stx-inc .big{margin-top:14px;text-align:right}
.stx-inc .big small{display:block;font-size:11px;color:var(--dim);font-weight:600}
.stx-inc .big b{font-size:30px;font-weight:800;letter-spacing:-.8px}

/* sticky order bar */
.stx-bar{position:sticky;bottom:14px;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-top:22px;padding:14px 18px;border-radius:18px;
    background:rgba(11,16,32,.86);backdrop-filter:blur(14px);border:1px solid var(--line);box-shadow:0 20px 50px -20px #000}
.stx-bar .t small{display:block;font-size:11px;color:var(--dim);font-weight:600}
.stx-bar .t b{font-size:22px;font-weight:800;letter-spacing:-.5px;color:#7dd3fc}
.stx-bar .t em{font-style:normal;font-size:12px;color:var(--dim);margin-left:4px}
.stx-bar select{padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:rgba(255,255,255,.07);color:var(--ink);font:inherit;font-size:13px}
.stx-bar select option{color:#111}
.stx-go{display:inline-flex;align-items:center;gap:8px;padding:13px 30px;border-radius:12px;font-weight:800;font-size:14px;text-decoration:none;color:#04210f;background:linear-gradient(90deg,#34d399,#2dd4bf);transition:transform .15s,filter .15s}
.stx-go:hover{transform:translateX(2px);filter:brightness(1.08)}
.stx-go.off{background:rgba(255,255,255,.1);color:var(--dim);pointer-events:none}
.stx-empty{text-align:center;padding:36px 16px;border:1px dashed var(--line);border-radius:18px;color:var(--dim)}
.stx-empty button{margin-top:12px;padding:9px 16px;border-radius:10px;border:0;background:#fff;color:#0b1020;font-weight:700;cursor:pointer}
.stx [hidden]{display:none!important}
.stx .c-blue{--c:#60a5fa}.stx .c-teal{--c:#2dd4bf}.stx .c-violet{--c:#a78bfa}.stx .c-amber{--c:#fbbf24}.stx .c-rose{--c:#fb7185}.stx .c-slate{--c:#94a3b8}.stx .c-gray{--c:#a1a1aa}
@media(max-width:640px){.stx{padding:24px 16px 20px}}
</style>

<div class="stx">
    <div class="stx-hero">
        <div>
            <h1>{{ __('store.hero_title') }}</h1>
            <p>{{ __('store.hero_subtitle') }}</p>
        </div>
        @if(!empty($cart) && count($cart) > 0)
        <a href="{{ route('client.cart.index') }}" class="stx-cart"><i class="ri-shopping-cart-2-line"></i>{{ __('client.cart.view_cart') }}</a>
        @endif
    </div>

    @if(session('error'))<div class="pn-alert pn-alert-error mb-16">{{ session('error') }}</div>@endif

    @if($total === 0)
        <div class="stx-empty">{{ __('client.store.no_products_at_all') }}</div>
    @else

    {{-- 1. Where and what --}}
    <div class="stx-block">
        <div class="stx-step"><b>1</b><h2>{{ __('store.wiz.s1') }}</h2></div>
        <div class="stx-pills">
            @foreach($kinds as $k => $meta)
            <button type="button" class="stx-pill c-{{ $meta['tone'] }}" data-kind="{{ $k }}"><i class="{{ $meta['icon'] }}"></i>{{ __("store.kind.$k.name") }}</button>
            @endforeach
        </div>
        @foreach(StoreOffering::PROVIDERS as $group => $items)
        <div class="stx-pg">
            <span>{{ __("store.provider_group.$group") }}</span>
            <div class="stx-tiles">
                @foreach($items as $key => $meta)
                @php $n = $count('prov', $key); @endphp
                <button type="button" class="stx-tile" style="--c:{{ $meta['color'] }}" data-prov="{{ $key }}" @disabled($n === 0)>
                    <span class="stx-mark">{{ $meta['mark'] }}</span>
                    <span>{{ $meta['name'] }}<small>{{ $n > 0 ? $n.' · '.__('store.live') : __('store.soon') }}</small></span>
                </button>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- 2. Select a product --}}
    <div class="stx-block">
        <div class="stx-step"><b>2</b><h2>{{ __('store.wiz.s2') }}</h2></div>
        <div class="stx-plans" id="stx-plans"></div>
        <div class="stx-empty" id="stx-none" hidden>{{ __('store.no_match') }}<br><button type="button" id="stx-reset">{{ __('store.reset') }}</button></div>
    </div>

    {{-- 3. Customize --}}
    <div class="stx-block" id="stx-step3">
        <div class="stx-step"><b>3</b><h2>{{ __('store.wiz.s3') }}</h2></div>
        <div class="stx-conf" id="stx-conf">
            <div id="stx-controls"></div>
            <div class="stx-gauge">
                <svg viewBox="0 0 220 220" aria-hidden="true">
                    <circle cx="110" cy="110" r="92" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="12"/>
                    <circle id="stx-arc" cx="110" cy="110" r="92" fill="none" stroke="currentColor" stroke-width="12" stroke-linecap="round" transform="rotate(-90 110 110)" stroke-dasharray="578" stroke-dashoffset="578" style="color:var(--c);transition:stroke-dashoffset .35s"/>
                    <foreignObject x="45" y="55" width="130" height="110"><div xmlns="http://www.w3.org/1999/xhtml" style="height:110px;display:flex;align-items:center;justify-content:center;font-size:54px;color:var(--c)"><i id="stx-gicon"></i></div></foreignObject>
                </svg>
                <div class="nm" id="stx-gname"></div>
                <div class="ds" id="stx-gdesc"></div>
            </div>
            <div class="stx-inc">
                <h4>{{ __('store.wiz.inc') }}</h4>
                <div id="stx-inc"></div>
                <div class="big"><small>{{ __('store.wiz.total') }}</small><b id="stx-incTotal"></b></div>
            </div>
        </div>
    </div>

    <div class="stx-bar" id="stx-bar">
        <div class="t"><small>{{ __('store.wiz.total') }}</small><b id="stx-total">—</b><em id="stx-per"></em></div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <select id="stx-cycle" aria-label="{{ __('store.wiz.cycle') }}"></select>
            <a id="stx-order" class="stx-go" href="#">{{ __('store.wiz.order') }} <i class="ri-arrow-right-line"></i></a>
        </div>
    </div>
    @endif
</div>

@if($total > 0)
<script>
(function () {
    var PLANS = @json($plans), PROV = @json($providers), CUR = @json(display_currency_affixes());
    var T = {from: @json(__('store.from')), free: @json(__('store.wiz.free')), none: @json(__('store.wiz.none_opt')), star: @json(__('client.store.most_popular')), out: @json(__('client.cart.out_of_stock')), contact: @json(__('client.store.contact_us')), kinds: @json(collect(StoreOffering::KINDS)->keys()->mapWithKeys(fn ($k) => [$k => __("store.kind.$k.name")]))};
    var order = Object.keys(PLANS).map(Number);
    var prov = null, kind = null, sel = null, cycle = null, vals = {};
    var $ = function (id) { return document.getElementById(id); };
    function money(n) { var p = Number(n).toFixed(2).split('.'); return CUR.prefix + p[0].replace(/\B(?=(\d{3})+(?!\d))/g, CUR.thousands) + CUR.decimal + p[1] + CUR.suffix; }
    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    function firstCycle(p) { return Object.keys(p.cycles)[0] || null; }
    function visible(p) { return (!prov || p.prov === prov) && (!kind || p.kind === kind); }

    function priceOf(prices, c) { return Number((prices || {})[c]) || 0; }
    function sel_(opt) { return opt.type === 'choice' ? opt.subs[Math.min(vals[opt.id] || 0, opt.subs.length - 1)] : null; }

    function total(p) {
        var t = Number(p.cycles[cycle]) || 0;
        p.options.forEach(function (o) {
            if (o.type === 'choice') t += priceOf(sel_(o).prices, cycle);
            else if (o.type === 'quantity') t += priceOf(o.prices, cycle) * (vals[o.id] || 0);
            else if (vals[o.id]) t += priceOf(o.prices, cycle);
        });
        return t;
    }

    function drawPlans() {
        var box = $('stx-plans'); box.textContent = '';
        var any = false;
        order.forEach(function (id) {
            var p = PLANS[id]; if (!visible(p)) return; any = true;
            var b = el('button', 'stx-plan c-' + p.tone + (id === sel ? ' on' : '')); b.type = 'button';
            if (p.featured) b.appendChild(el('span', 'star', T.star));
            var chk = el('span', 'chk'); chk.appendChild(el('i', 'ri-check-line')); b.appendChild(chk);
            var k = el('div', 'k'); k.appendChild(el('i', p.icon)); k.appendChild(document.createTextNode(T.kinds[p.kind])); b.appendChild(k);
            b.appendChild(el('h3', null, p.name));
            var c = firstCycle(p), pr = el('div', 'pr');
            if (p.free) pr.textContent = T.free;
            else if (c) { pr.appendChild(el('small', null, T.from + ' ')); pr.appendChild(document.createTextNode(money(p.cycles[c]))); pr.appendChild(el('small', null, ' /' + c)); }
            else pr.textContent = T.contact;
            b.appendChild(pr);
            if (p.specs.length) b.appendChild(el('div', 'mini', p.specs.slice(0, 3).map(function (s) { return s.text; }).join(' · ')));
            b.addEventListener('click', function () { choose(id); });
            box.appendChild(b);
        });
        $('stx-none').hidden = any; $('stx-step3').hidden = !any; $('stx-bar').hidden = !any;
        return any;
    }

    function choose(id) {
        var p = PLANS[id]; sel = id; cycle = firstCycle(p); vals = {};
        p.options.forEach(function (o) { vals[o.id] = o.type === 'quantity' ? o.min : 0; });
        drawPlans(); drawConf();
    }

    function drawConf() {
        var p = PLANS[sel]; if (!p) return;
        var conf = $('stx-conf'); conf.className = 'stx-conf c-' + p.tone;
        var ctl = $('stx-controls'); ctl.textContent = '';
        var prog = [];
        p.options.forEach(function (o) {
            var box = el('div', 'stx-ctl'), row = el('div', 'row'), name = el('b', null, o.name), val = el('span');
            row.appendChild(name); row.appendChild(val); box.appendChild(row);
            function refresh() {
                if (o.type === 'choice') { var s = sel_(o); val.textContent = s.label; var d = priceOf(s.prices, cycle); if (d > 0) val.appendChild(el('small', null, '+' + money(d))); }
                else if (o.type === 'quantity') { val.textContent = String(vals[o.id]); var u = priceOf(o.prices, cycle); if (u > 0) val.appendChild(el('small', null, money(u) + ' c/u')); }
                else { val.textContent = vals[o.id] ? '✓' : ''; }
            }
            var input;
            if (o.type === 'choice' && o.subs.length > 1) {
                input = el('input'); input.type = 'range'; input.min = 0; input.max = o.subs.length - 1; input.step = 1; input.value = vals[o.id];
                prog.push(function () { return vals[o.id] / (o.subs.length - 1); });
            } else if (o.type === 'choice') {
                box.appendChild(el('div', 'ds')); refresh(); ctl.appendChild(box); return;
            } else if (o.type === 'quantity') {
                var span = o.max > o.min && o.max - o.min <= 4096;
                input = el('input'); input.type = span ? 'range' : 'number'; input.min = o.min; if (o.max) input.max = o.max; input.step = 1; input.value = vals[o.id];
                if (span) prog.push(function () { return (vals[o.id] - o.min) / (o.max - o.min); });
            } else {
                var lab = el('label', 'sw'); input = el('input'); input.type = 'checkbox'; input.checked = !!vals[o.id];
                lab.appendChild(input); lab.appendChild(document.createTextNode(o.label || o.name)); box.appendChild(lab);
                input.addEventListener('change', function () { vals[o.id] = input.checked ? 1 : 0; update(); }); refresh(); ctl.appendChild(box); return;
            }
            input.addEventListener('input', function () { var v = parseInt(input.value || '0', 10); vals[o.id] = isNaN(v) ? 0 : Math.max(o.min || 0, v); update(); });
            box.appendChild(input); refresh(); ctl.appendChild(box);
        });
        if (!p.options.length) ctl.appendChild(el('div', 'ds', T.none));
        ctl._prog = prog;
        $('stx-gicon').className = p.icon; $('stx-gname').textContent = p.name; $('stx-gdesc').textContent = p.desc || '';
        var sc = $('stx-cycle'); sc.textContent = '';
        Object.keys(p.cycles).forEach(function (c) { var o = el('option', null, c); o.value = c; sc.appendChild(o); });
        sc.value = cycle; sc.hidden = Object.keys(p.cycles).length < 2;
        update();
    }

    function update() {
        var p = PLANS[sel]; if (!p) return;
        // labels that carry a price follow the cycle
        document.querySelectorAll('#stx-controls .stx-ctl').forEach(function (box, i) {
            var o = p.options[i]; if (!o) return; var val = box.querySelector('.row span'); if (!val) return;
            if (o.type === 'choice') { var s = sel_(o); val.textContent = s.label; var d = priceOf(s.prices, cycle); if (d > 0) val.appendChild(el('small', null, '+' + money(d))); }
            else if (o.type === 'quantity') { val.textContent = String(vals[o.id]); var u = priceOf(o.prices, cycle); if (u > 0) val.appendChild(el('small', null, money(u) + ' c/u')); }
            else val.textContent = vals[o.id] ? '✓' : '';
        });
        var prog = $('stx-controls')._prog || [], avg = prog.length ? prog.reduce(function (a, f) { return a + f(); }, 0) / prog.length : 0.5;
        $('stx-arc').style.strokeDashoffset = 578 * (1 - Math.max(.08, Math.min(1, avg)));
        var inc = $('stx-inc'); inc.textContent = '';
        p.specs.forEach(function (s) { var r = el('div', 'li'); r.appendChild(el('span', null, '')); r.firstChild.appendChild(el('i', s.icon)); r.appendChild(el('span', null, s.text)); inc.appendChild(r); });
        p.options.forEach(function (o) {
            var label = '', price = 0;
            if (o.type === 'choice') { var s = sel_(o); label = s.label; price = priceOf(s.prices, cycle); }
            else if (o.type === 'quantity') { if (!vals[o.id]) return; label = 'x' + vals[o.id]; price = priceOf(o.prices, cycle) * vals[o.id]; }
            else { if (!vals[o.id]) return; label = '✓'; price = priceOf(o.prices, cycle); }
            var r = el('div', 'li'); r.appendChild(el('span', null, o.name)); r.appendChild(el('span', null, label + (price > 0 ? '  +' + money(price) : ''))); inc.appendChild(r);
        });
        var t = total(p), txt = p.free ? T.free : (Object.keys(p.cycles).length ? money(t) : T.contact);
        $('stx-total').textContent = txt; $('stx-incTotal').textContent = txt; $('stx-per').textContent = (!p.free && cycle) ? '/' + cycle : '';
        var q = [];
        if (cycle) q.push('cycle=' + encodeURIComponent(cycle));
        p.options.forEach(function (o) {
            if (o.type === 'choice') q.push('opt%5B' + o.id + '%5D=' + sel_(o).id);
            else if (o.type === 'quantity') { if (vals[o.id]) q.push('opt%5B' + o.id + '%5D=' + vals[o.id]); }
            else if (vals[o.id]) q.push('opt%5B' + o.id + '%5D=1');
        });
        var a = $('stx-order'); a.href = p.url + (q.length ? '?' + q.join('&') : '');
        a.classList.toggle('off', p.soldOut); if (p.soldOut) a.textContent = T.out;
    }

    function applyFilters() {
        document.querySelectorAll('.stx-tile').forEach(function (t) { t.classList.toggle('on', t.dataset.prov === prov); });
        document.querySelectorAll('.stx-pill').forEach(function (t) { t.classList.toggle('on', t.dataset.kind === kind); });
        if (sel === null || !visible(PLANS[sel])) {
            var next = order.filter(function (id) { return visible(PLANS[id]); })[0];
            if (next === undefined) { sel = null; drawPlans(); return; }
            choose(next); return;
        }
        drawPlans();
    }

    document.querySelectorAll('.stx-tile').forEach(function (t) { t.addEventListener('click', function () { prov = prov === t.dataset.prov ? null : t.dataset.prov; applyFilters(); }); });
    document.querySelectorAll('.stx-pill').forEach(function (t) { t.addEventListener('click', function () { kind = kind === t.dataset.kind ? null : t.dataset.kind; applyFilters(); }); });
    $('stx-reset').addEventListener('click', function () { prov = kind = null; applyFilters(); });
    $('stx-cycle').addEventListener('change', function () { cycle = this.value; update(); });

    var feat = order.filter(function (id) { return PLANS[id].featured; })[0];
    choose(feat !== undefined ? feat : order[0]);
})();
</script>
@endif

@endsection
