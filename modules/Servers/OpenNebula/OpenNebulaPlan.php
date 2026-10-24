<?php

namespace Modules\Servers\OpenNebula;

use App\Models\Service;

/**
 * What an OpenNebula product sells, read from the product and from the options
 * the customer chose at checkout.
 *
 * Product settings live under one_* keys, set by the product page's OpenNebula
 * card. The template the product installs is the "package" every module reads
 * (package_name), so a product set up before the card existed keeps working.
 * Whatever the card leaves empty is left to the OpenNebula template itself.
 *
 * A configurable option overrides the product when its name starts with one of
 * OPTION_KEYS, the way the Proxmox module does it: an option "memory|RAM" whose
 * choices are "2048|2 GB" and "4096|4 GB" sells the memory the customer picks,
 * and one named "os|System" whose choices are template ids sells the template.
 */
final class OpenNebulaPlan
{
    /** Configurable option keys this module understands. */
    public const OPTION_KEYS = ['cores', 'memory', 'disk', 'os', 'rate', 'snapshots'];

    public function __construct(public readonly array $values) {}

    public static function forService(Service $service, ?array $productConfig = null): self
    {
        $cfg = $productConfig ?? self::productConfig($service);
        $pick = fn (string $key, array $legacy, mixed $default) => self::first($cfg, ["one_{$key}", ...$legacy], $default);

        $values = [
            'template' => (string) $pick('template', ['package_name', 'panelica_plan_id', 'cpanel_package'], ''),
            'cores' => max(0, (int) $pick('cores', [], 0)),
            'cpu' => max(0, (float) $pick('cpu', [], 0)),
            'memory' => max(0, (int) $pick('memory', [], 0)),
            'disk' => max(0, (int) $pick('disk', [], 0)),
            'cluster' => self::id($pick('cluster', [], '')),
            'datastore' => self::id($pick('datastore', [], '')),
            'network' => self::id($pick('network', [], '')),
            'rate' => max(0, (float) $pick('rate', [], 0)),
            'secgroup' => self::id($pick('secgroup', [], '')),
            'nameserver' => trim((string) $pick('nameserver', [], '')),
            'ciuser' => trim((string) $pick('ciuser', [], '')),
            'snapshots' => max(0, (int) $pick('snapshots', [], 0)),
            'password' => (bool) $pick('password', [], false),
        ];

        foreach (self::chosenOptions($service) as $key => $value) {
            $values = self::applyOption($values, $key, $value);
        }

        return new self($values);
    }

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    /** The OpenNebula template id this plan installs, or 0 when none is set. */
    public function templateId(): int
    {
        return ctype_digit($this->values['template']) ? (int) $this->values['template'] : 0;
    }

    public static function productConfig(Service $service): array
    {
        $raw = $service->product?->config_options;
        $cfg = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($cfg) ? $cfg : [];
    }

    /** An OpenNebula object id kept as a string: digits only, anything else is "not set". */
    public static function id(mixed $value): string
    {
        $value = trim((string) $value);

        return ctype_digit($value) ? $value : '';
    }

    private static function first(array $cfg, array $keys, mixed $default): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $cfg) && $cfg[$key] !== '' && $cfg[$key] !== null) {
                return $cfg[$key];
            }
        }

        return $default;
    }

    /**
     * The customer's configurable option choices, keyed by the module key the
     * option name starts with.
     *
     * @return array<string, string|int>
     */
    private static function chosenOptions(Service $service): array
    {
        if (! $service->exists) {
            return [];
        }

        $chosen = [];
        foreach ($service->configOptions()->with('option', 'sub')->get() as $row) {
            $key = strtolower(trim(explode('|', (string) $row->option?->option_name, 2)[0]));
            if (! in_array($key, self::OPTION_KEYS, true)) {
                continue;
            }

            if ($row->option?->isQuantity()) {
                $chosen[$key] = (int) $row->qty;
            } elseif ($row->sub) {
                $chosen[$key] = trim(explode('|', (string) $row->sub->option_name, 2)[0]);
            }
        }

        return $chosen;
    }

    private static function applyOption(array $values, string $key, string|int $value): array
    {
        if ($key === 'os') {
            $value = (string) $value;
            if (ctype_digit($value)) {
                $values['template'] = $value;
            }

            return $values;
        }

        if (is_numeric($value) && (float) $value > 0) {
            $values[$key] = $key === 'rate' ? (float) $value : (int) $value;
        }

        return $values;
    }
}
