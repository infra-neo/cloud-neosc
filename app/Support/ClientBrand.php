<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Setting;

/**
 * Per-customer branding of the client area (name, colour, logo, favicon and
 * custom portal domain).
 *
 * Stored as one JSON document per customer in the existing `settings` table
 * under `client_brand.<client id>`, so no migration is needed and a stock
 * update cannot drop it. Everything read from it is re-validated here: the
 * values end up inside a <style> block, so only a strict #rrggbb colour and a
 * plain hostname are ever accepted.
 */
final class ClientBrand
{
    public const DEFAULT_COLOR = '#1a6fd1';

    public static function key(int $clientId): string
    {
        return 'client_brand.'.$clientId;
    }

    /** @return array{name:string,color:string,logo:string,favicon:string,domain:string,domain_verified_at:string} */
    public static function get(?Client $client): array
    {
        $defaults = ['name' => '', 'color' => '', 'logo' => '', 'favicon' => '', 'domain' => '', 'domain_verified_at' => ''];

        if (! $client) {
            return $defaults;
        }

        $raw = Setting::get(self::key((int) $client->id));
        $data = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($data)) {
            return $defaults;
        }

        $out = array_merge($defaults, array_intersect_key($data, $defaults));
        $out['color'] = self::normalizeColor($out['color']) ?? '';

        return array_map(static fn ($v) => is_string($v) ? $v : '', $out);
    }

    public static function save(Client $client, array $data): void
    {
        $current = self::get($client);
        $merged = array_merge($current, array_intersect_key($data, $current));

        Setting::set(self::key((int) $client->id), json_encode($merged, JSON_UNESCAPED_SLASHES), 'client_brand');
    }

    /** The brand of the account the signed-in user is looking at; never throws. */
    public static function forCurrent(): array
    {
        try {
            $user = auth()->user();
            if (! $user) {
                return self::get(null);
            }

            $selected = session('active_client_id');
            $client = $selected ? $user->clients()->whereKey($selected)->first() : null;
            $client = $client ?: $user->clients()->first();

            return self::get($client);
        } catch (\Throwable $e) {
            return self::get(null);
        }
    }

    public static function normalizeColor(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : null;
    }

    public static function normalizeDomain(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('#^https?://#', '', $value);

        return rtrim((string) strtok((string) $value, '/'), '.');
    }

    public static function isValidDomain(string $domain): bool
    {
        return strlen($domain) <= 253
            && preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) === 1;
    }

    /** True when another customer already claimed the domain. */
    public static function domainTaken(string $domain, int $exceptClientId): bool
    {
        $rows = Setting::where('setting', 'like', 'client_brand.%')->get(['setting', 'value']);

        foreach ($rows as $row) {
            if ($row->setting === self::key($exceptClientId)) {
                continue;
            }
            $data = json_decode((string) $row->value, true);
            if (is_array($data) && strtolower((string) ($data['domain'] ?? '')) === $domain) {
                return true;
            }
        }

        return false;
    }

    /** Where the customer's CNAME must point. */
    public static function cnameTarget(): string
    {
        $target = trim((string) Setting::get('neosc_portal_cname', ''));

        return $target !== '' ? $target : 'portal.neosc.kappa4.com';
    }

    /** @return array{0:string,1:string,2:string} primary, darker, soft tint */
    public static function shades(string $hex): array
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $dark = sprintf('#%02x%02x%02x', (int) ($r * .82), (int) ($g * .82), (int) ($b * .82));
        $soft = sprintf('#%02x%02x%02x', (int) ($r + (255 - $r) * .9), (int) ($g + (255 - $g) * .9), (int) ($b + (255 - $b) * .9));

        return [$hex, $dark, $soft];
    }

    /** CSS custom properties for the layout; empty when the customer set no colour. */
    public static function cssVars(array $brand): string
    {
        $color = self::normalizeColor($brand['color'] ?? '');
        if (! $color) {
            return '';
        }

        [$primary, $dark, $soft] = self::shades($color);

        return ":root{--primary:{$primary};--primary-dark:{$dark};--primary-light:{$soft};--nsc-accent:{$primary}}"
            .":root[data-theme=\"dark\"]{--primary-light:rgba(255,255,255,.08)}";
    }
}
