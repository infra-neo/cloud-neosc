@extends('client.layouts.app')
@section('title', $i18n['title'])

@section('styles')
<style>
    .wz-steps{display:flex;margin:0 0 20px;overflow-x:auto;padding-bottom:4px}
    .wz-steps div{flex:1;min-width:84px;text-align:center;font-size:11.5px;color:var(--muted);position:relative;padding-top:28px;white-space:nowrap}
    .wz-steps div::before{content:attr(data-n);position:absolute;top:0;left:50%;transform:translateX(-50%);width:24px;height:24px;border-radius:50%;border:1px solid var(--border);background:var(--card);display:grid;place-items:center;font-size:11px;font-weight:600;z-index:1}
    .wz-steps div::after{content:"";position:absolute;top:12px;left:-50%;width:100%;height:1px;background:var(--border)}
    .wz-steps div:first-child::after{display:none}
    .wz-steps .done::before{content:"\2713";background:var(--primary);border-color:var(--primary);color:#fff}
    .wz-steps .cur{color:var(--primary);font-weight:600}
    .wz-steps .cur::before{border-color:var(--primary);color:var(--primary);background:var(--primary-light)}
    .wz-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}
    .wz-opt{border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:14px;background:var(--card);cursor:pointer;text-align:left;font:inherit;color:var(--text);display:block;width:100%}
    .wz-opt:hover{border-color:var(--primary)}
    .wz-opt.on{border-color:var(--primary);background:var(--primary-light);box-shadow:inset 0 0 0 1px var(--primary)}
    .wz-opt b{display:block;font-size:14px}
    .wz-opt small{display:block;color:var(--muted);font-size:12px;margin-top:3px;line-height:1.45}
    .wz-opt .price{margin-top:8px;font-weight:700;font-size:15px}
    .wz-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
    .wz-chip{border:1.5px solid var(--border);background:var(--card);color:var(--muted);border-radius:999px;padding:5px 14px;font:inherit;font-size:12.5px;font-weight:600;cursor:pointer}
    .wz-chip.on{border-color:var(--primary);color:var(--primary);background:var(--primary-light)}
    .wz-foot{position:sticky;bottom:0;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:12px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:18px;box-shadow:var(--shadow-md)}
    .wz-total{font-size:20px;font-weight:800}
    .wz-total small{font-size:11px;font-weight:500;color:var(--muted);margin-left:6px}
    .wz-sum{display:grid;grid-template-columns:auto 1fr;gap:6px 16px;font-size:13.5px}
    .wz-sum dt{color:var(--muted)} .wz-sum dd{margin:0;text-align:right;font-weight:600;overflow-wrap:anywhere}
    .wz-err{color:var(--danger);font-size:12.5px;margin-top:8px}
    .wz-res{display:flex;flex-wrap:wrap;gap:4px 12px;margin-top:8px;font-size:12px;color:var(--muted)}
</style>
@endsection

@section('content')
<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ $i18n['title'] }}</h1>
        <p class="pn-page-subtitle">{{ $i18n['subtitle'] }}</p>
    </div>
    <a href="{{ route('client.store') }}" class="btn btn-outline btn-sm">{{ $i18n['classic'] }}</a>
</div>

@if($errors->any())
    <div class="pn-alert pn-alert-error"><div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>
@endif

<div id="wz-empty" class="pn-card" hidden><div class="pn-card-body"><div class="pn-empty"><p>{{ $i18n['no_products'] }}</p></div></div></div>

<div id="wz-root">
    <div class="wz-steps" id="wz-steps"></div>
    <div class="pn-card"><div class="pn-card-body" id="wz-body"></div></div>
    <div class="wz-foot">
        <div class="wz-total" id="wz-total"></div>
        <div class="flex gap-8">
            <button type="button" class="btn btn-default" id="wz-back">{{ $i18n['back'] }}</button>
            <button type="button" class="btn btn-primary" id="wz-next"></button>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('client.cart.add') }}" id="wz-form" hidden>
    @csrf
</form>

<script type="application/json" id="wz-data">{!! json_encode(['catalog' => $catalog, 'i18n' => $i18n, 'preselect' => $preselect], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@section('scripts')
<script>
(function () {
    var D = JSON.parse(document.getElementById('wz-data').textContent), T = D.i18n, C = D.catalog, P = C.products;
    if (!P.length) { document.getElementById('wz-root').hidden = true; document.getElementById('wz-empty').hidden = false; return; }

    var CYC = { monthly: T.c_monthly, quarterly: T.c_quarterly, semiannually: T.c_semiannually, annually: T.c_annually, biennially: T.c_biennially, triennially: T.c_triennially };
    var STEPS = ['name', 'plan', 'image', 'apps', 'disk', 'net', 'review'];
    var S = { i: 0, sub: '', host: '', pid: null, loc: '', cycle: null, opts: {}, addons: [], conn: true, net: 'vpc', err: '' };

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function money(n) { return C.currency.prefix + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + C.currency.suffix; }
    function prod() { return P.filter(function (p) { return p.id === S.pid; })[0] || null; }
    function cat(name) {
        if (/imagen|image|sistema|system|\bos\b|windows|linux|ubuntu|debian/i.test(name)) return 'image';
        if (/disco|disk|storage|almacen|backup|respaldo/i.test(name)) return 'disk';
        if (/\bred\b|network|vpc|\bip\b/i.test(name)) return 'net';
        return 'other';
    }
    function optionsOf(p, c) {
        var out = [];
        (p ? p.groups : []).forEach(function (g) { g.options.forEach(function (o) { if (cat(o.name) === c) out.push(o); }); });
        return out;
    }
    function initOpts(p) {
        S.opts = {};
        p.groups.forEach(function (g) { g.options.forEach(function (o) {
            S.opts[o.id] = o.type === 'select' ? o.subs[0].id : (o.type === 'qty' ? o.min : false);
        }); });
        S.addons = [];
        S.cycle = Object.keys(p.cycles)[0];
    }
    function total() {
        var p = prod(); if (!p || !S.cycle) return null;
        var t = p.cycles[S.cycle] || 0;
        p.groups.forEach(function (g) { g.options.forEach(function (o) {
            var v = S.opts[o.id];
            if (o.type === 'select') { o.subs.forEach(function (s) { if (s.id === v) t += (s.prices[S.cycle] || 0); }); }
            else if (o.type === 'check' && v) { t += (o.subs[0].prices[S.cycle] || 0); }
            else if (o.type === 'qty' && v) { t += (o.subs[0].prices[S.cycle] || 0) * Number(v); }
        }); });
        p.addons.forEach(function (a) { if (S.addons.indexOf(a.id) > -1) t += (a.prices[S.cycle] || 0); });
        return t;
    }

    function optField(o) {
        var h = '<label class="form-label" for="o' + o.id + '">' + esc(o.name) + '</label>';
        if (o.type === 'select') {
            return '<div class="wz-grid" style="margin-bottom:16px">' + h.replace('<label', '<label style="grid-column:1/-1"') + o.subs.map(function (s) {
                return '<button type="button" class="wz-opt' + (S.opts[o.id] === s.id ? ' on' : '') + '" data-sel="' + o.id + ':' + s.id + '"><b>' + esc(s.name) + '</b>' +
                    ((s.prices[S.cycle] || 0) > 0 ? '<div class="price">+' + money(s.prices[S.cycle]) + '</div>' : '') + '</button>';
            }).join('') + '</div>';
        }
        if (o.type === 'check') {
            var s0 = o.subs[0];
            return '<label style="display:flex;gap:8px;align-items:center;margin-bottom:12px;cursor:pointer"><input type="checkbox" data-chk="' + o.id + '"' + (S.opts[o.id] ? ' checked' : '') + '> <span><b>' + esc(s0.name || o.name) + '</b>' +
                ((s0.prices[S.cycle] || 0) > 0 ? ' <span class="text-muted">(+' + money(s0.prices[S.cycle]) + ')</span>' : '') + '</span></label>';
        }
        return '<div class="form-group">' + h + '<input type="number" class="form-control" id="o' + o.id + '" data-qty="' + o.id + '" min="' + o.min + '"' + (o.max ? ' max="' + o.max + '"' : '') + ' value="' + S.opts[o.id] + '" style="max-width:160px"></div>';
    }

    function view() {
        var p = prod(), k = STEPS[S.i], h = '';
        if (k === 'name') {
            h = '<h2>' + esc(T.s_name) + '</h2><p class="pn-page-subtitle" style="margin-bottom:16px">' + esc(T.s_name_hint) + '</p>' +
                '<div class="form-grid-2"><div class="form-group"><label class="form-label" for="wsub">' + esc(T.sub_name) + '</label><input class="form-control" id="wsub" maxlength="80" value="' + esc(S.sub) + '"></div>' +
                '<div class="form-group"><label class="form-label" for="whost">' + esc(T.host_name) + '</label><input class="form-control" id="whost" maxlength="63" value="' + esc(S.host) + '" autocapitalize="off" spellcheck="false"><div class="form-hint">' + esc(T.host_hint) + '</div></div></div>';
        } else if (k === 'plan') {
            var locs = []; P.forEach(function (x) { var l = x.location || x.provider; if (l && locs.indexOf(l) < 0) locs.push(l); });
            h = '<h2>' + esc(T.s_plan) + '</h2>';
            if (locs.length > 1) h += '<div class="wz-chips"><button type="button" class="wz-chip' + (S.loc === '' ? ' on' : '') + '" data-loc="">' + esc(T.all_locations) + '</button>' + locs.map(function (l) { return '<button type="button" class="wz-chip' + (S.loc === l ? ' on' : '') + '" data-loc="' + esc(l) + '">' + esc(l) + '</button>'; }).join('') + '</div>';
            h += '<div class="wz-grid">' + P.filter(function (x) { return !S.loc || (x.location || x.provider) === S.loc; }).map(function (x) {
                var first = Object.keys(x.cycles)[0];
                return '<button type="button" class="wz-opt' + (S.pid === x.id ? ' on' : '') + '" data-pid="' + x.id + '"><b>' + esc(x.name) + '</b><small>' + esc(x.location || x.provider) + '</small>' +
                    (x.resources.length ? '<div class="wz-res">' + x.resources.map(function (r) { return '<span>' + esc(r) + '</span>'; }).join('') + '</div>' : '') +
                    (x.description ? '<small>' + esc(x.description.slice(0, 110)) + '</small>' : '') +
                    '<div class="price">' + money(x.cycles[first]) + ' <small style="display:inline">/ ' + esc(CYC[first] || first) + '</small></div></button>';
            }).join('') + '</div>';
        } else if (k === 'image') {
            var im = optionsOf(p, 'image');
            h = '<h2>' + esc(T.s_image) + '</h2>' + (im.length ? im.map(optField).join('') : '<p class="text-muted">' + esc(T.none_image) + '</p>');
        } else if (k === 'apps') {
            h = '<h2>' + esc(T.s_apps) + ' <span class="text-muted" style="font-size:12px;font-weight:500">' + esc(T.optional) + '</span></h2>';
            h += p.addons.length ? '<div class="wz-grid">' + p.addons.map(function (a) {
                return '<button type="button" class="wz-opt' + (S.addons.indexOf(a.id) > -1 ? ' on' : '') + '" data-add="' + a.id + '"><b>' + esc(a.name) + '</b>' + (a.description ? '<small>' + esc(a.description.slice(0, 100)) + '</small>' : '') +
                    ((a.prices[S.cycle] || 0) > 0 ? '<div class="price">+' + money(a.prices[S.cycle]) + '</div>' : '') + '</button>';
            }).join('') + '</div>' : '<p class="text-muted">' + esc(T.none_apps) + '</p>';
        } else if (k === 'disk') {
            var dk = optionsOf(p, 'disk');
            h = '<h2>' + esc(T.s_disk) + '</h2>' + (dk.length ? dk.map(optField).join('') : '<p class="text-muted">' + esc(T.none_disk) + '</p>');
        } else if (k === 'net') {
            var nt = optionsOf(p, 'net');
            h = '<h2>' + esc(T.s_net) + '</h2>' + nt.map(optField).join('') +
                '<div class="wz-grid" style="margin-bottom:16px"><button type="button" class="wz-opt' + (S.net === 'vpc' ? ' on' : '') + '" data-net="vpc"><b>' + esc(T.net_private) + '</b><small>' + esc(T.net_private_d) + '</small></button>' +
                '<button type="button" class="wz-opt' + (S.net === 'pub' ? ' on' : '') + '" data-net="pub"><b>' + esc(T.net_public) + '</b><small>' + esc(T.net_public_d) + '</small></button></div>' +
                '<label style="display:flex;gap:8px;align-items:center;cursor:pointer"><input type="checkbox" id="wconn"' + (S.conn ? ' checked' : '') + '> <span><b>' + esc(T.connect) + '</b> <span class="text-muted">' + esc(T.connect_d) + '</span></span></label>';
        } else {
            var other = optionsOf(p, 'other');
            h = '<h2>' + esc(T.s_review) + '</h2>' + (other.length ? '<div style="margin-bottom:12px">' + other.map(optField).join('') + '</div>' : '') +
                '<div class="pn-section-title">' + esc(T.cycle) + '</div><div class="wz-grid" style="margin-bottom:18px">' + Object.keys(p.cycles).map(function (c) {
                    return '<button type="button" class="wz-opt' + (S.cycle === c ? ' on' : '') + '" data-cyc="' + c + '"><b>' + esc(CYC[c] || c) + '</b><div class="price">' + money(p.cycles[c]) + '</div></button>'; }).join('') + '</div>' +
                '<dl class="wz-sum"><dt>' + esc(T.sub_name) + '</dt><dd>' + esc(S.sub) + '</dd><dt>' + esc(T.host_name) + '</dt><dd>' + esc(S.host) + '</dd><dt>' + esc(T.s_plan) + '</dt><dd>' + esc(p.name) + ' · ' + esc(p.location || p.provider) + '</dd>' +
                '<dt>' + esc(T.s_net) + '</dt><dd>' + esc(S.net === 'vpc' ? T.net_private : T.net_public) + '</dd><dt>' + esc(T.connect) + '</dt><dd>' + esc(S.conn ? T.yes : T.no) + '</dd><dt>' + esc(T.s_apps) + '</dt><dd>' + S.addons.length + '</dd></dl>';
        }
        if (S.err) h += '<div class="wz-err">' + esc(S.err) + '</div>';
        document.getElementById('wz-body').innerHTML = h;

        var labels = [T.st_name, T.st_plan, T.st_image, T.st_apps, T.st_disk, T.st_net, T.st_review];
        document.getElementById('wz-steps').innerHTML = labels.map(function (l, i) { return '<div data-n="' + (i + 1) + '" class="' + (i < S.i ? 'done' : i === S.i ? 'cur' : '') + '">' + esc(l) + '</div>'; }).join('');
        var t = total();
        document.getElementById('wz-total').innerHTML = t === null ? '' : money(t) + '<small>/ ' + esc(CYC[S.cycle] || S.cycle) + '</small>';
        document.getElementById('wz-back').disabled = S.i === 0;
        document.getElementById('wz-next').textContent = S.i === STEPS.length - 1 ? T.add_to_cart : T.next;
    }

    function validate() {
        var k = STEPS[S.i];
        if (k === 'name') {
            if (!S.sub.trim()) return T.err_sub;
            if (!/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/.test(S.host)) return T.err_host;
        }
        if (k === 'plan' && !S.pid) return T.err_plan;
        return '';
    }

    function submit() {
        var p = prod(), f = document.getElementById('wz-form');
        Array.prototype.slice.call(f.querySelectorAll('input[data-gen]')).forEach(function (n) { n.remove(); });
        function add(n, v) { var i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; i.setAttribute('data-gen', '1'); f.appendChild(i); }
        add('product_id', p.id); add('billing_cycle', S.cycle); add('domain', S.host); add('domain_option', 'own');
        add('notes', ['[NeoSC] ' + T.sub_name + ': ' + S.sub, T.s_net + ': ' + (S.net === 'vpc' ? T.net_private : T.net_public), T.connect + ': ' + (S.conn ? T.yes : T.no)].join('\n'));
        p.groups.forEach(function (g) { g.options.forEach(function (o) {
            var v = S.opts[o.id];
            if (o.type === 'check') { if (v) add('config_options[' + o.id + ']', '1'); }
            else if (o.type === 'qty') { add('config_options[' + o.id + ']', v); }
            else { add('config_options[' + o.id + ']', v); }
        }); });
        S.addons.forEach(function (id) { add('addons[]', id); });
        f.submit();
    }

    document.getElementById('wz-next').onclick = function () {
        S.err = validate(); if (S.err) return view();
        if (S.i === STEPS.length - 1) return submit();
        S.i++; view(); window.scrollTo(0, 0);
    };
    document.getElementById('wz-back').onclick = function () { if (S.i > 0) { S.i--; S.err = ''; view(); window.scrollTo(0, 0); } };

    document.getElementById('wz-body').addEventListener('click', function (e) {
        var b = e.target.closest('[data-loc],[data-pid],[data-sel],[data-add],[data-net],[data-cyc]'); if (!b) return;
        if (b.hasAttribute('data-loc')) S.loc = b.getAttribute('data-loc');
        else if (b.hasAttribute('data-pid')) { var id = +b.getAttribute('data-pid'); if (S.pid !== id) { S.pid = id; initOpts(prod()); } S.err = ''; }
        else if (b.hasAttribute('data-sel')) { var a = b.getAttribute('data-sel').split(':'); S.opts[a[0]] = +a[1]; }
        else if (b.hasAttribute('data-add')) { var ad = +b.getAttribute('data-add'), ix = S.addons.indexOf(ad); if (ix < 0) S.addons.push(ad); else S.addons.splice(ix, 1); }
        else if (b.hasAttribute('data-net')) S.net = b.getAttribute('data-net');
        else if (b.hasAttribute('data-cyc')) S.cycle = b.getAttribute('data-cyc');
        view();
    });
    document.getElementById('wz-body').addEventListener('input', function (e) {
        var t = e.target;
        if (t.id === 'wsub') S.sub = t.value;
        if (t.id === 'whost') { S.host = t.value.toLowerCase().replace(/[^a-z0-9-]/g, ''); if (t.value !== S.host) t.value = S.host; }
        if (t.hasAttribute('data-qty')) { S.opts[t.getAttribute('data-qty')] = t.value; document.getElementById('wz-total').innerHTML = money(total() || 0) + '<small>/ ' + esc(CYC[S.cycle] || S.cycle) + '</small>'; }
    });
    document.getElementById('wz-body').addEventListener('change', function (e) {
        var t = e.target;
        if (t.hasAttribute('data-chk')) { S.opts[t.getAttribute('data-chk')] = t.checked; view(); }
        if (t.id === 'wconn') S.conn = t.checked;
    });

    if (D.preselect) { var m = P.filter(function (x) { return x.slug === D.preselect; })[0]; if (m) { S.pid = m.id; initOpts(m); } }
    view();
})();
</script>
@endsection
