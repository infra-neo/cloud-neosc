<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * A customer's organization inside NeoSC: departments and the people in them.
 *
 * One JSON document per customer in the existing `settings` table under
 * `client_org.<client id>` (no migration, survives stock updates). The local
 * copy is the source of truth for the portal; NeoGuard (Zitadel) is kept in
 * step best-effort and every record carries its own `sync` state.
 */
final class ClientOrg
{
    public const ROLES = ['admin', 'manager', 'user'];

    public static function key(int $clientId): string
    {
        return 'client_org.'.$clientId;
    }

    /** @return array{departments:list<array<string,mixed>>,members:list<array<string,mixed>>} */
    public static function get(Client $client): array
    {
        $raw = Setting::get(self::key((int) $client->id));
        $data = is_string($raw) ? json_decode($raw, true) : null;
        $data = is_array($data) ? $data : [];

        return [
            'departments' => array_values(array_filter((array) ($data['departments'] ?? []), 'is_array')),
            'members' => array_values(array_filter((array) ($data['members'] ?? []), 'is_array')),
        ];
    }

    /** @param array{departments:list<array<string,mixed>>,members:list<array<string,mixed>>} $org */
    public static function save(Client $client, array $org): void
    {
        Setting::set(self::key((int) $client->id), json_encode([
            'departments' => array_values($org['departments']),
            'members' => array_values($org['members']),
        ], JSON_UNESCAPED_UNICODE), 'client_org');
    }

    public static function newId(): string
    {
        return Str::lower(Str::random(10));
    }

    public static function normalizeRole(?string $role): string
    {
        return in_array($role, self::ROLES, true) ? $role : 'user';
    }

    /** Only existing department ids survive. */
    public static function cleanDepartments(array $org, mixed $ids): array
    {
        $valid = array_column($org['departments'], 'id');

        return array_values(array_intersect($valid, array_map('strval', (array) $ids)));
    }

    public static function findMember(array $org, string $id): ?int
    {
        foreach ($org['members'] as $i => $m) {
            if (($m['id'] ?? '') === $id) {
                return $i;
            }
        }

        return null;
    }
}
