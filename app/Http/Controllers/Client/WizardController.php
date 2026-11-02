<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Setting;
use App\Services\AddonService;
use App\Services\ConfigOptionService;

/**
 * Step-by-step order wizard for servers.
 *
 * It only presents what the catalogue already holds (products that have a
 * provisioning module, their configurable options and addons) and finishes by
 * posting the same fields the stock configure page posts to client.cart.add.
 * Cart, checkout, orders and provisioning (OpenNebula, LXD, Proxmox...) are
 * therefore untouched.
 */
class WizardController extends Controller
{
    public function servers(ConfigOptionService $options, AddonService $addons)
    {
        $currency = Currency::getDefault();

        $products = Product::active()
            ->whereIn('server_type', $this->serverTypes())
            ->with(['pricing', 'serverGroup'])
            ->orderBy('sort_order')
            ->get()
            ->reject(fn (Product $p) => $p->outOfStock())
            ->filter(fn (Product $p) => $p->pricedCycles($currency?->id) !== []);

        $catalog = [
            'currency' => ['prefix' => (string) $currency?->prefix, 'suffix' => (string) $currency?->suffix],
            'products' => $products->map(fn (Product $p) => $this->describe($p, $currency, $options, $addons))->values()->all(),
        ];

        return view('client.wizard.servers', [
            'catalog' => $catalog,
            'i18n' => trans('portal.wizard'),
            'preselect' => request('product'),
        ]);
    }

    /**
     * Provisioning modules that sell machines, as opposed to hosting panels.
     * Overridable from the `neosc_wizard_server_types` setting (comma list).
     *
     * @return array<int,string>
     */
    private function serverTypes(): array
    {
        $raw = (string) Setting::get('neosc_wizard_server_types', 'opennebula,lxd,proxmox,vultr,gcp,oracle,huawei,aws,azure,kvm');

        return array_values(array_filter(array_map(fn ($t) => strtolower(trim($t)), explode(',', $raw))));
    }

    private function describe(Product $p, ?Currency $currency, ConfigOptionService $options, AddonService $addons): array
    {
        $cfg = is_string($p->config_options) ? (json_decode($p->config_options, true) ?: []) : (array) $p->config_options;
        $cycles = $p->pricedCycles($currency?->id);

        $groups = [];
        foreach ($options->groupsFor($p) as $group) {
            $opts = [];
            foreach ($group->options as $o) {
                if ($o->subs->isEmpty()) {
                    continue;
                }
                $opts[] = [
                    'id' => $o->id,
                    'name' => $o->option_name,
                    'type' => $o->isQuantity() ? 'qty' : ($o->isCheckbox() ? 'check' : 'select'),
                    'min' => (int) ($o->qty_minimum ?? 0),
                    'max' => (int) ($o->qty_maximum ?? 0),
                    'subs' => $o->subs->map(fn ($s) => [
                        'id' => $s->id,
                        'name' => $s->option_name,
                        'prices' => $this->pricesFor($s, array_keys($cycles)),
                    ])->values()->all(),
                ];
            }
            if ($opts !== []) {
                $groups[] = ['name' => $group->name ?? '', 'options' => $opts];
            }
        }

        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'name' => $p->name,
            'description' => strip_tags((string) $p->description),
            'location' => (string) ($cfg['nsc_location'] ?? $p->serverGroup?->name ?? ''),
            'provider' => (string) ($cfg['nsc_provider'] ?? $p->server_type),
            'resources' => array_map(fn ($r) => $r['text'], $p->resourceSummary()),
            'cycles' => $cycles,
            'groups' => $groups,
            'addons' => $addons->availableFor($p)->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'description' => strip_tags((string) $a->description),
                'prices' => $this->pricesFor($a, array_keys($cycles)),
            ])->values()->all(),
        ];
    }

    /** @param  array<int,string>  $cycles */
    private function pricesFor($priced, array $cycles): array
    {
        $out = [];
        foreach ($cycles as $cycle) {
            $out[$cycle] = (float) $priced->priceFor($cycle);
        }

        return $out;
    }
}
