<?php

namespace App\Support;

use App\Models\Product;

/**
 * Which kind of offer a product is, for the store page.
 *
 * The operator can say so on the product (config_options "nsc_kind"); when
 * they have not, an OpenNebula product is read as a persistent or a
 * non-persistent desktop from its own persistent flag, and anything else is
 * left in the general section, so a store set up before this existed still
 * shows every product.
 */
final class StoreOffering
{
    /** Kinds in the order the store shows them. */
    public const KINDS = [
        'vdi-persistent' => ['icon' => 'ri-computer-line', 'tone' => 'blue'],
        'vdi-ephemeral' => ['icon' => 'ri-refresh-line', 'tone' => 'teal'],
        'gpu' => ['icon' => 'ri-gamepad-line', 'tone' => 'violet'],
        'app-oneclick' => ['icon' => 'ri-flashlight-line', 'tone' => 'amber'],
        'app-docker' => ['icon' => 'ri-box-3-line', 'tone' => 'rose'],
        'app-browser' => ['icon' => 'ri-global-line', 'tone' => 'slate'],
        'other' => ['icon' => 'ri-server-line', 'tone' => 'gray'],
    ];

    public static function config(Product $product): array
    {
        $raw = $product->config_options;
        $cfg = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($cfg) ? $cfg : [];
    }

    public static function kind(Product $product): string
    {
        $cfg = self::config($product);
        $set = (string) ($cfg['nsc_kind'] ?? '');
        if (isset(self::KINDS[$set])) {
            return $set;
        }
        if (! empty($cfg['one_template'])) {
            return ! empty($cfg['one_persistent']) ? 'vdi-persistent' : 'vdi-ephemeral';
        }

        return 'other';
    }

    /**
     * The headline specs of a product, as icon and text pairs.
     *
     * @return list<array{icon: string, text: string}>
     */
    public static function specs(Product $product): array
    {
        $cfg = self::config($product);
        $specs = [];

        $cores = (int) ($cfg['one_cores'] ?? 0);
        if ($cores > 0) {
            $specs[] = ['icon' => 'ri-cpu-line', 'text' => __('store.spec.cores', ['n' => $cores])];
        }
        $mem = (int) ($cfg['one_memory'] ?? 0);
        if ($mem > 0) {
            $specs[] = ['icon' => 'ri-ram-2-line', 'text' => $mem >= 1024 ? rtrim(rtrim(number_format($mem / 1024, 1, '.', ''), '0'), '.').' GB RAM' : $mem.' MB RAM'];
        }
        $disk = (int) ($cfg['one_disk'] ?? 0);
        if ($disk > 0) {
            // one_disk is in GB, the way the product card takes it.
            $specs[] = ['icon' => 'ri-hard-drive-2-line', 'text' => $disk >= 1024 ? rtrim(rtrim(number_format($disk / 1024, 1, '.', ''), '0'), '.').' TB' : $disk.' GB'];
        }
        $gpu = trim((string) ($cfg['nsc_gpu'] ?? ''));
        if ($gpu !== '') {
            $specs[] = ['icon' => 'ri-gamepad-line', 'text' => mb_substr($gpu, 0, 60)];
        }

        return $specs === [] ? $product->resourceSummary() : $specs;
    }
}
