{{-- What an OpenNebula product sells. The drop-downs are filled from the
     OpenNebula server itself (templates, clusters, datastores, networks,
     security groups); until that answer arrives each shows the value already
     saved, so saving without it changes nothing. Styles come from the Proxmox
     card (.pve-card), which has the same layout. --}}
@php
    $one = fn (string $key, $default = '') => $cfg["one_{$key}"] ?? $default;
    $oneCores = (int) $one('cores', 0);
    $oneMemory = (int) $one('memory', 0);
    $oneDisk = (int) $one('disk', 0);
    $oneTemplate = (string) ($one('template', '') !== '' ? $one('template') : ($cfg['package_name'] ?? ''));
    $oneCurrency = \App\Models\Currency::getDefault();
    $oneLinked = $oneOrderOptions ?? [];
    $oneLinkedKeys = collect($oneLinked)->pluck('key')->all();
    $oneBase = [
        'cores' => $oneCores > 0 ? $oneCores : 1,
        'memory' => $oneMemory > 0 ? $oneMemory : 1024,
        'disk' => $oneDisk > 0 ? $oneDisk : 20,
    ];
    $oneTiers = [
        'memory' => collect([$oneBase['memory'], $oneBase['memory'] * 2, $oneBase['memory'] * 4])->map(fn ($v, $i) => ['value' => $v, 'price' => [0, 5, 12][$i]])->all(),
        'cores' => collect([$oneBase['cores'], $oneBase['cores'] * 2, $oneBase['cores'] * 4])->map(fn ($v, $i) => ['value' => $v, 'price' => [0, 4, 10][$i]])->all(),
        'disk' => collect([$oneBase['disk'], $oneBase['disk'] * 2, $oneBase['disk'] * 4])->map(fn ($v, $i) => ['value' => $v, 'price' => [0, 2, 5][$i]])->all(),
    ];
    $onePresets = [[1, 1024, 20], [1, 2048, 40], [2, 4096, 80], [4, 8192, 160], [8, 16384, 320]];
    $oneKeys = \Modules\Servers\OpenNebula\OpenNebulaOrderOptions::KEYS;
@endphp
<div class="card pve-card one-card" data-module-card="opennebula" style="margin-bottom:15px;display:none;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div><strong>{{ __('opennebula.product.title') }}</strong> <span style="font-size:11px;color:#888;">&mdash; {{ __('opennebula.product.subtitle') }}</span></div>
        <div style="display:flex;gap:6px;align-items:center;">
            <label for="one-server" style="font-size:12px;color:#666;margin:0;">{{ __('opennebula.product.read_from') }}</label>
            <select id="one-server" class="form-control" style="font-size:12px;width:auto;">
                @forelse($opennebulaServers as $os)
                <option value="{{ $os->id }}">{{ $os->name }}{{ $os->active ? '' : ' ('.__('common.status.disabled').')' }}</option>
                @empty
                <option value="">{{ __('admin.products.no_server_for_module') }}</option>
                @endforelse
            </select>
            <button type="button" class="btn btn-default btn-xs" id="one-refresh">{{ __('opennebula.product.refresh') }}</button>
        </div>
    </div>
    <div class="card-body" style="background:#fafbfc;">
        <input type="hidden" name="one_section" value="1">
        <div id="one-note" style="font-size:12px;color:#666;margin-bottom:12px;">{{ __('opennebula.product.loading') }}</div>

        <div class="pve-sum" id="one-summary" aria-live="polite"><b>{{ __('opennebula.product.summary_label') }}</b></div>

        {{-- 1. Resources --}}
        <div class="pve-sec">
            <div class="pve-sec-h"><span class="pve-num">1</span><strong>{{ __('opennebula.product.sec_resources') }}</strong><small>{{ __('opennebula.product.sec_resources_hint') }}</small></div>
            <div class="pve-presets">
                <span>{{ __('opennebula.product.presets') }}</span>
                @foreach($onePresets as [$pc, $pm, $pd])
                <button type="button" class="btn btn-default btn-xs" data-one-preset="{{ $pc }},{{ $pm }},{{ $pd }}">{{ __('opennebula.product.preset_value', ['cores' => $pc, 'memory' => $pm / 1024, 'disk' => $pd]) }}</button>
                @endforeach
            </div>
            <div class="pve-grid">
                <div class="form-group">
                    <label class="form-label" for="one-cores">{{ __('opennebula.product.cores') }}</label>
                    <input id="one-cores" type="number" min="0" max="512" name="one_cores" value="{{ $oneCores }}" class="form-control" data-one-sum>
                </div>
                <div class="form-group">
                    <label class="form-label" for="one-cpu">{{ __('opennebula.product.cpu') }}</label>
                    <input id="one-cpu" type="number" min="0" step="0.1" name="one_cpu" value="{{ $one('cpu', 0) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="one-memory">{{ __('opennebula.product.memory') }}</label>
                    <input id="one-memory" type="number" min="0" step="64" name="one_memory" value="{{ $oneMemory }}" class="form-control" data-one-sum>
                </div>
                <div class="form-group">
                    <label class="form-label" for="one-disk">{{ __('opennebula.product.disk') }}</label>
                    <input id="one-disk" type="number" min="0" name="one_disk" value="{{ $oneDisk }}" class="form-control" data-one-sum>
                </div>
            </div>
        </div>

        {{-- 2. Template and placement --}}
        <div class="pve-sec">
            <div class="pve-sec-h"><span class="pve-num">2</span><strong>{{ __('opennebula.product.sec_image') }}</strong><small>{{ __('opennebula.product.sec_image_hint') }}</small></div>
            <div class="pve-grid">
                <div class="form-group pve-span2">
                    <label class="form-label" for="one-template">{{ __('opennebula.product.template') }}</label>
                    <select id="one-template" name="one_template" data-one-list="templates" data-current="{{ $oneTemplate }}" class="form-control" data-one-sum>
                        <option value="">{{ __('opennebula.product.template_none') }}</option>
                        @if($oneTemplate !== '')<option value="{{ $oneTemplate }}" selected>#{{ $oneTemplate }}</option>@endif
                    </select>
                    <small style="color:#888;">{{ __('opennebula.product.template_hint') }}</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="one-cluster">{{ __('opennebula.product.cluster') }}</label>
                    <select id="one-cluster" name="one_cluster" data-one-list="clusters" data-current="{{ $one('cluster', '') }}" class="form-control">
                        <option value="">{{ __('opennebula.product.cluster_auto') }}</option>
                        @if($one('cluster', '') !== '')<option value="{{ $one('cluster', '') }}" selected>#{{ $one('cluster', '') }}</option>@endif
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="one-datastore">{{ __('opennebula.product.datastore') }}</label>
                    <select id="one-datastore" name="one_datastore" data-one-list="datastores" data-current="{{ $one('datastore', '') }}" class="form-control">
                        <option value="">{{ __('opennebula.product.datastore_auto') }}</option>
                        @if($one('datastore', '') !== '')<option value="{{ $one('datastore', '') }}" selected>#{{ $one('datastore', '') }}</option>@endif
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="one-ciuser">{{ __('opennebula.product.ciuser') }}</label>
                    <input id="one-ciuser" type="text" name="one_ciuser" value="{{ $one('ciuser', '') }}" class="form-control" placeholder="root">
                    <small style="color:#888;">{{ __('opennebula.product.ciuser_hint') }}</small>
                </div>
            </div>
            <div class="pve-checks">
                <label><input type="checkbox" name="one_persistent" value="1" @checked((bool) $one('persistent', 0))> {{ __('opennebula.product.persistent') }}</label>
            </div>
            <div style="font-size:12px;color:#777;margin-bottom:12px;">{{ __('opennebula.product.persistent_hint') }}</div>
        </div>

        {{-- 3. Network --}}
        <div class="pve-sec">
            <div class="pve-sec-h"><span class="pve-num">3</span><strong>{{ __('opennebula.product.sec_network') }}</strong><small>{{ __('opennebula.product.sec_network_hint') }}</small></div>
            <div class="pve-grid">
                <div class="form-group">
                    <label class="form-label" for="one-network">{{ __('opennebula.product.network') }}</label>
                    <select id="one-network" name="one_network" data-one-list="networks" data-current="{{ $one('network', '') }}" class="form-control" data-one-sum>
                        <option value="">{{ __('opennebula.product.network_template') }}</option>
                        @if($one('network', '') !== '')<option value="{{ $one('network', '') }}" selected>#{{ $one('network', '') }}</option>@endif
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="one-secgroup">{{ __('opennebula.product.secgroup') }}</label>
                    <select id="one-secgroup" name="one_secgroup" data-one-list="secgroups" data-current="{{ $one('secgroup', '') }}" class="form-control">
                        <option value="">{{ __('opennebula.product.secgroup_none') }}</option>
                        @if($one('secgroup', '') !== '')<option value="{{ $one('secgroup', '') }}" selected>#{{ $one('secgroup', '') }}</option>@endif
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="one-rate">{{ __('opennebula.product.rate') }}</label>
                    <input id="one-rate" type="number" min="0" step="0.1" name="one_rate" value="{{ $one('rate', 0) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="one-nameserver">{{ __('opennebula.product.nameserver') }}</label>
                    <input id="one-nameserver" type="text" name="one_nameserver" value="{{ $one('nameserver', '') }}" class="form-control" placeholder="1.1.1.1 8.8.8.8">
                </div>
            </div>
        </div>

        {{-- 4. What the customer may do --}}
        <div class="pve-sec">
            <div class="pve-sec-h"><span class="pve-num">4</span><strong>{{ __('opennebula.product.sec_customer') }}</strong><small>{{ __('opennebula.product.sec_customer_hint') }}</small></div>
            <div class="pve-grid">
                <div class="form-group pve-span2">
                    <label class="form-label" for="one-snapshots">{{ __('opennebula.product.snapshots') }}</label>
                    <input id="one-snapshots" type="number" min="0" max="50" name="one_snapshots" value="{{ $one('snapshots', 0) }}" class="form-control" data-one-sum>
                </div>
            </div>
            <div class="pve-grid" style="margin-top:6px;">
                <div class="form-group pve-span2">
                    <label class="form-label" for="one-connect">{{ __('opennebula.product.connect_url') }}</label>
                    <input id="one-connect" type="url" name="one_connect_url" value="{{ $one('connect_url', '') }}" class="form-control" placeholder="https://portal.example.com">
                    <small style="color:#888;">{{ __('opennebula.product.connect_hint') }}</small>
                </div>
            </div>
            <div class="pve-grid" style="margin-top:6px;">
                <div class="form-group">
                    <label class="form-label" for="nsc-kind">{{ __('store.admin.kind') }}</label>
                    <select id="nsc-kind" name="nsc_kind" class="form-control">
                        <option value="">{{ __('store.admin.auto') }}</option>
                        @foreach(array_keys(\App\Support\StoreOffering::KINDS) as $k)
                        <option value="{{ $k }}" @selected($one('nsc_kind', '') === $k)>{{ __("store.kind.$k.name") }}</option>
                        @endforeach
                    </select>
                    <small style="color:#888;">{{ __('store.admin.kind_hint') }}</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="nsc-provider">{{ __('store.admin.provider') }}</label>
                    <select id="nsc-provider" name="nsc_provider" class="form-control">
                        <option value="">{{ __('store.admin.auto_provider') }}</option>
                        @foreach(\App\Support\StoreOffering::PROVIDERS as $items)
                        @foreach($items as $pk => $pm)
                        <option value="{{ $pk }}" @selected($one('nsc_provider', '') === $pk)>{{ $pm['name'] }}</option>
                        @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="nsc-gpu">{{ __('store.admin.gpu') }}</label>
                    <input id="nsc-gpu" type="text" maxlength="60" name="nsc_gpu" value="{{ $one('nsc_gpu', '') }}" class="form-control" placeholder="NVIDIA L4 24 GB">
                </div>
            </div>
            <div class="pve-checks">
                <label><input type="checkbox" name="one_password" value="1" @checked((bool) $one('password', 0))> {{ __('opennebula.product.password') }}</label>
            </div>
            <div style="font-size:12px;color:#777;margin-bottom:12px;">{{ __('opennebula.product.no_backups') }}</div>
        </div>

        {{-- 5. Order options --}}
        <div class="pve-sec" id="one-options">
            <div class="pve-sec-h"><span class="pve-num">5</span><strong>{{ __('opennebula.product.sec_options') }}</strong><small>{{ __('opennebula.product.sec_options_hint') }}</small></div>
            <div id="one-linked" @if(empty($oneLinked)) style="display:none" @endif>
                <div style="font-weight:700;font-size:12.5px;margin-bottom:6px;">{{ __('opennebula.product.opt_existing') }}</div>
                <table class="pve-linked"><thead><tr><th>{{ __('opennebula.product.opt_title') }}</th><th>{{ __('opennebula.product.opt_choices') }}</th></tr></thead><tbody id="one-linked-rows">
                    @foreach($oneLinked as $opt)
                    <tr><td><strong>{{ $opt['title'] }}</strong><br><span style="color:#888;font-size:11.5px;">{{ $opt['group'] }}</span></td>
                        <td>@foreach($opt['choices'] as $c)<span class="pve-chip" style="display:inline-block;margin:0 4px 4px 0;">{{ $c['label'] }} <span>{{ $c['monthly'] > 0 ? '+'.number_format($c['monthly'], 2) : __('opennebula.product.opt_included') }}</span></span>@endforeach</td></tr>
                    @endforeach
                </tbody></table>
                <a href="{{ route('admin.config.config-options') }}" class="btn btn-default btn-xs" style="margin-bottom:12px;">{{ __('opennebula.product.opt_edit') }}</a>
            </div>
            <div id="one-builder" @if(count($oneLinkedKeys) >= count($oneKeys)) style="display:none" @endif>
                @foreach($oneKeys as $key)
                <div class="pve-opt" data-opt="{{ $key }}" @if(in_array($key, $oneLinkedKeys, true)) style="display:none" data-linked="1" @endif>
                    <div class="pve-opt-h">
                        <label><input type="checkbox" data-opt-on> {{ __('opennebula.product.opt_'.$key) }}</label>
                        <input type="text" data-opt-title hidden value="{{ __('opennebula.product.opt_'.$key) }}" class="form-control" style="max-width:220px;font-size:12.5px;padding:4px 8px;height:auto;" aria-label="{{ __('opennebula.product.opt_title') }}">
                    </div>
                    <div class="pve-opt-body" hidden>
                        <table>
                            <thead><tr>
                                <th>{{ $key === 'os' ? __('opennebula.product.opt_os_template') : __('opennebula.product.opt_'.$key) }}</th>
                                <th>{{ __('opennebula.product.opt_label') }}</th>
                                <th>{{ __('opennebula.product.opt_price', ['currency' => $oneCurrency?->code ?? '']) }}</th>
                                <th></th>
                            </tr></thead>
                            <tbody data-opt-rows>
                                @if($key !== 'os')
                                @foreach($oneTiers[$key] as $tier)
                                <tr>
                                    <td><input type="number" min="1" data-v value="{{ $tier['value'] }}" class="form-control"></td>
                                    <td><input type="text" data-l value="{{ \Modules\Servers\OpenNebula\OpenNebulaOrderOptions::defaultLabel($key, (string) $tier['value']) }}" class="form-control"></td>
                                    <td><input type="number" min="0" step="0.01" data-p value="{{ number_format($tier['price'], 2, '.', '') }}" class="form-control"></td>
                                    <td><button type="button" class="btn btn-default btn-xs" data-opt-remove>{{ __('opennebula.product.opt_remove') }}</button></td>
                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>
                        @if($key === 'os')
                        <div style="display:flex;gap:6px;align-items:center;margin-top:6px;flex-wrap:wrap;">
                            <select id="one-os-add" class="form-control" style="font-size:12.5px;width:auto;max-width:320px;"></select>
                            <button type="button" class="btn btn-default btn-xs" id="one-os-add-btn">{{ __('opennebula.product.opt_add_template') }}</button>
                        </div>
                        <div style="font-size:12px;color:#777;margin-top:4px;">{{ __('opennebula.product.opt_os_hint') }}</div>
                        @else
                        <button type="button" class="btn btn-default btn-xs" data-opt-add style="margin-top:6px;">{{ __('opennebula.product.opt_add') }}</button>
                        @endif
                    </div>
                </div>
                @endforeach
                <div style="font-size:12px;color:#666;margin:4px 0 10px;">{{ __('opennebula.product.opt_note', ['currency' => $oneCurrency?->code ?? '']) }}</div>
                <div id="one-opt-msg" style="display:none;font-size:12.5px;font-weight:600;margin-bottom:10px;padding:8px 10px;border-radius:6px;"></div>
                <button type="button" class="btn btn-primary btn-sm" id="one-opt-create" data-url="{{ $product->exists ? route('admin.products.opennebula-options', $product) : '' }}">{{ __('opennebula.product.opt_create') }}</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var card = document.querySelector('[data-module-card="opennebula"]');
    if (!card) { return; }
    var serverSel = document.getElementById('one-server');
    var note = document.getElementById('one-note');
    var catalog = null;
    var S = @json(__('opennebula.product'));
    var lang = document.documentElement.lang || 'en';

    function esc(t) { return String(t).replace(/[&<>"']/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; }); }
    function val(name) { var f = card.querySelector('[name="' + name + '"]'); return f ? f.value : ''; }
    function gb(mb) { try { return new Intl.NumberFormat(lang, { style: 'unit', unit: mb >= 1024 ? 'gigabyte' : 'megabyte', maximumFractionDigits: 1 }).format(mb >= 1024 ? mb / 1024 : mb); } catch (e) { return mb + ' MB'; } }
    function gig(n) { try { return new Intl.NumberFormat(lang, { style: 'unit', unit: 'gigabyte' }).format(n); } catch (e) { return n + ' GB'; } }

    // Fill a drop-down from the server, keeping the first (empty) option and
    // the value already saved, which is flagged when the server no longer lists it.
    function fill(select, items) {
        var current = select.getAttribute('data-current') || '';
        var first = select.options[0] && select.options[0].value === '' ? select.options[0].cloneNode(true) : null;
        select.innerHTML = '';
        if (first) { select.appendChild(first); }
        var seen = false;
        (items || []).forEach(function (it) {
            var o = document.createElement('option');
            o.value = it.id; o.textContent = it.name + ' (#' + it.id + ')';
            if (it.id === current) { o.selected = true; seen = true; }
            select.appendChild(o);
        });
        if (current && !seen) {
            var o = document.createElement('option');
            o.value = current; o.selected = true;
            o.textContent = '#' + current + ' - ' + S.not_on_server;
            select.appendChild(o);
        }
    }

    function templateInfo(id) {
        return catalog ? (catalog.templates || []).filter(function (t) { return t.id === String(id); })[0] : null;
    }

    function chip(label, value) { return '<span class="pve-chip"><span>' + esc(label) + '</span>' + esc(value) + '</span>'; }
    function summary() {
        var box = document.getElementById('one-summary'); if (!box) { return; }
        var tsel = card.querySelector('[name="one_template"]');
        var t = tsel && tsel.value ? templateInfo(tsel.value) : null;
        var cores = parseInt(val('one_cores') || '0', 10), mem = parseInt(val('one_memory') || '0', 10), disk = parseInt(val('one_disk') || '0', 10);
        var html = '<b>' + esc(S.summary_label) + '</b>';
        if (tsel && tsel.value) { html += chip(S.chip_template, tsel.options[tsel.selectedIndex].text.replace(/\s*\(#\d+\)$/, '')); }
        html += chip(S.chip_cores, cores > 0 ? cores : (t && t.vcpu ? t.vcpu : S.chip_from_template));
        html += chip(S.chip_memory, mem > 0 ? gb(mem) : (t && t.memory ? gb(t.memory) : S.chip_from_template));
        html += chip(S.chip_disk, disk > 0 ? gig(disk) : (t && t.disk ? gig(t.disk) : S.chip_from_template));
        var net = card.querySelector('[name="one_network"]');
        html += chip(S.chip_network, net && net.value ? net.options[net.selectedIndex].text.replace(/\s*\(#\d+\)$/, '') : S.chip_from_template);
        html += chip(S.chip_snapshots, val('one_snapshots') || '0');
        box.innerHTML = html;
    }

    function osAddList() {
        var sel = document.getElementById('one-os-add'); if (!sel) { return; }
        sel.innerHTML = '';
        ((catalog && catalog.templates) || []).forEach(function (t) {
            var o = document.createElement('option'); o.value = t.id; o.textContent = t.name + ' (#' + t.id + ')'; sel.appendChild(o);
        });
    }

    function load() {
        if (!serverSel.value) { note.textContent = @json(__('admin.products.no_server_for_module')); return; }
        note.textContent = S.loading; note.style.color = '';
        fetch(@json(route('admin.products.opennebula-catalog')) + '?server_id=' + encodeURIComponent(serverSel.value), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) { note.textContent = S.load_failed + ' ' + (data.error || ''); note.style.color = '#b91c1c'; return; }
                catalog = data;
                card.querySelectorAll('[data-one-list]').forEach(function (sel) {
                    var kind = sel.getAttribute('data-one-list');
                    var items = data[kind] || [];
                    // A placement is a system datastore (type 1), not an image one.
                    if (kind === 'datastores') { items = items.filter(function (d) { return d.type === 1; }); }
                    fill(sel, items);
                });
                note.style.color = '#15803d';
                note.textContent = S.loaded.replace(':templates', (data.templates || []).length).replace(':networks', (data.networks || []).length)
                    .replace(':clusters', (data.clusters || []).length).replace(':datastores', (data.datastores || []).filter(function (d) { return d.type === 1; }).length);
                osAddList(); summary(); osRows();
            })
            .catch(function () { note.textContent = S.load_failed; note.style.color = '#b91c1c'; });
    }

    document.getElementById('one-refresh').addEventListener('click', load);
    serverSel.addEventListener('change', load);
    card.querySelectorAll('[data-one-list]').forEach(function (sel) {
        sel.addEventListener('change', function () { sel.setAttribute('data-current', sel.value); });
    });
    card.addEventListener('input', function (e) { if (e.target.closest('[data-one-sum]')) { summary(); } });
    card.addEventListener('change', function (e) { if (e.target.closest('[data-one-sum]')) { summary(); osRows(); } });
    card.querySelectorAll('[data-one-preset]').forEach(function (b) {
        b.addEventListener('click', function () {
            var v = b.getAttribute('data-one-preset').split(',');
            card.querySelector('[name="one_cores"]').value = v[0];
            card.querySelector('[name="one_memory"]').value = v[1];
            card.querySelector('[name="one_disk"]').value = v[2];
            summary();
        });
    });

    // ---- order options builder ----
    var builder = document.getElementById('one-builder');
    // The OS rows: the product's own template first, then the ones added by hand.
    function osRows() {
        var wrap = builder && builder.querySelector('[data-opt="os"] [data-opt-rows]'); if (!wrap) { return; }
        var kept = {};
        wrap.querySelectorAll('tr[data-os]').forEach(function (tr) {
            kept[tr.getAttribute('data-os')] = { l: tr.querySelector('[data-l]').value, p: tr.querySelector('[data-p]').value, extra: tr.hasAttribute('data-extra') };
        });
        var tsel = card.querySelector('[name="one_template"]');
        var rows = [];
        if (tsel && tsel.value) {
            var t = templateInfo(tsel.value);
            rows.push([tsel.value, kept[tsel.value] ? kept[tsel.value].l : (t ? t.name : 'Template #' + tsel.value), kept[tsel.value] ? kept[tsel.value].p : '0.00', false]);
        }
        Object.keys(kept).forEach(function (id) { if (kept[id].extra && (!tsel || id !== tsel.value)) { rows.push([id, kept[id].l, kept[id].p, true]); } });
        wrap.innerHTML = rows.length ? rows.map(function (r) {
            return '<tr data-os="' + esc(r[0]) + '"' + (r[3] ? ' data-extra="1"' : '') + '><td><code>' + esc(r[0]) + '</code><input type="hidden" data-v value="' + esc(r[0]) + '"></td>'
                + '<td><input type="text" data-l class="form-control" value="' + esc(r[1]) + '"></td>'
                + '<td><input type="number" min="0" step="0.01" data-p class="form-control" value="' + esc(r[2]) + '"></td>'
                + '<td>' + (r[3] ? '<button type="button" class="btn btn-default btn-xs" data-opt-remove>' + esc(S.opt_remove) + '</button>' : '') + '</td></tr>';
        }).join('') : '';
    }
    var addBtn = document.getElementById('one-os-add-btn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            var sel = document.getElementById('one-os-add'); var wrap = builder.querySelector('[data-opt="os"] [data-opt-rows]');
            if (!sel || !sel.value || wrap.querySelector('tr[data-os="' + CSS.escape(sel.value) + '"]')) { return; }
            var t = templateInfo(sel.value);
            var tr = document.createElement('tr'); tr.setAttribute('data-os', sel.value); tr.setAttribute('data-extra', '1');
            tr.innerHTML = '<td><code>' + esc(sel.value) + '</code><input type="hidden" data-v value="' + esc(sel.value) + '"></td>'
                + '<td><input type="text" data-l class="form-control" value="' + esc(t ? t.name : 'Template #' + sel.value) + '"></td>'
                + '<td><input type="number" min="0" step="0.01" data-p class="form-control" value="0.00"></td>'
                + '<td><button type="button" class="btn btn-default btn-xs" data-opt-remove>' + esc(S.opt_remove) + '</button></td>';
            wrap.appendChild(tr);
        });
    }
    if (builder) {
        builder.querySelectorAll('[data-opt]').forEach(function (box) {
            var on = box.querySelector('[data-opt-on]'), body = box.querySelector('.pve-opt-body');
            on.addEventListener('change', function () { body.hidden = !on.checked; box.querySelector('[data-opt-title]').hidden = !on.checked; if (box.getAttribute('data-opt') === 'os') { osRows(); } });
            var add = box.querySelector('[data-opt-add]');
            if (add) {
                add.addEventListener('click', function () {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td><input type="number" min="1" data-v class="form-control"></td><td><input type="text" data-l class="form-control" placeholder="' + esc(S.opt_label_auto) + '"></td>'
                        + '<td><input type="number" min="0" step="0.01" data-p class="form-control" value="0.00"></td><td><button type="button" class="btn btn-default btn-xs" data-opt-remove>' + esc(S.opt_remove) + '</button></td>';
                    box.querySelector('[data-opt-rows]').appendChild(tr);
                });
            }
            box.addEventListener('click', function (e) { var r = e.target.closest('[data-opt-remove]'); if (r) { r.closest('tr').remove(); } });
        });
        var say = function (text, ok) { var m = document.getElementById('one-opt-msg'); m.style.display = 'block'; m.textContent = text; m.style.background = ok ? '#ecfdf5' : '#fef2f2'; m.style.color = ok ? '#047857' : '#b91c1c'; };
        document.getElementById('one-opt-create').addEventListener('click', function () {
            var btn = this, url = btn.getAttribute('data-url');
            if (!url) { say(S.opt_save_first, false); return; }
            var spec = {};
            builder.querySelectorAll('[data-opt]').forEach(function (box) {
                if (!box.querySelector('[data-opt-on]').checked || box.getAttribute('data-linked')) { return; }
                var choices = [];
                box.querySelectorAll('[data-opt-rows] tr').forEach(function (tr) {
                    var v = tr.querySelector('[data-v]'); if (!v || !v.value) { return; }
                    choices.push({ value: v.value, label: tr.querySelector('[data-l]').value, price: tr.querySelector('[data-p]').value || 0 });
                });
                spec[box.getAttribute('data-opt')] = { title: box.querySelector('[data-opt-title]').value, choices: choices };
            });
            if (!Object.keys(spec).length) { say(S.opt_none_chosen, false); return; }
            btn.disabled = true;
            fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify({ options: spec }) })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    var j = res.j || {};
                    if (!res.ok || !j.success) { say(j.message || (j.errors ? Object.values(j.errors).flat().join(' ') : S.load_failed), false); return; }
                    say(j.message, true);
                    var rows = document.getElementById('one-linked-rows'); rows.innerHTML = '';
                    var linkedKeys = {};
                    (j.linked || []).forEach(function (o) {
                        linkedKeys[o.key] = 1;
                        rows.insertAdjacentHTML('beforeend', '<tr><td><strong>' + esc(o.title) + '</strong><br><span style="color:#888;font-size:11.5px;">' + esc(o.group) + '</span></td><td>'
                            + o.choices.map(function (c) { return '<span class="pve-chip" style="display:inline-block;margin:0 4px 4px 0;">' + esc(c.label) + ' <span>' + (c.monthly > 0 ? '+' + Number(c.monthly).toFixed(2) : esc(S.opt_included)) + '</span></span>'; }).join('') + '</td></tr>');
                    });
                    document.getElementById('one-linked').style.display = '';
                    builder.querySelectorAll('[data-opt]').forEach(function (box) {
                        if (linkedKeys[box.getAttribute('data-opt')]) { box.style.display = 'none'; box.setAttribute('data-linked', '1'); box.querySelector('[data-opt-on]').checked = false; }
                    });
                })
                .catch(function () { say(S.load_failed, false); })
                .finally(function () { btn.disabled = false; });
        });
    }

    window.pnlcsOpenNebulaCard = { show: function (on) {
        card.style.display = on ? '' : 'none';
        card.querySelectorAll('input,select').forEach(function (f) { f.disabled = !on; });
        if (on) { summary(); if (!catalog) { load(); } }
    } };
})();
</script>
@endpush
