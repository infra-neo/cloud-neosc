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

    /** @return array{departments:list<array<string,mixed>>,members:list<array<string,mixed>>,access:array<string,array{members:list<string>,groups:list<string>}>} */
    public static function get(Client $client): array
    {
        $raw = Setting::get(self::key((int) $client->id));
        $data = is_string($raw) ? json_decode($raw, true) : null;
        $data = is_array($data) ? $data : [];

        return [
            'departments' => array_values(array_filter((array) ($data['departments'] ?? []), 'is_array')),
            'members' => array_values(array_filter((array) ($data['members'] ?? []), 'is_array')),
            // service id => who may open it: people directly and whole groups.
            'access' => array_filter((array) ($data['access'] ?? []), 'is_array'),
        ];
    }

    /** @param array<string,mixed> $org */
    public static function save(Client $client, array $org): void
    {
        Setting::set(self::key((int) $client->id), json_encode([
            'departments' => array_values($org['departments']),
            'members' => array_values($org['members']),
            'access' => (object) ($org['access'] ?? []),
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

    /** Ids of the people who may open a service: direct grants plus members of granted groups. */
    public static function effectiveMemberIds(array $org, string $serviceId): array
    {
        $rule = $org['access'][$serviceId] ?? ['members' => [], 'groups' => []];
        $direct = array_map('strval', (array) ($rule['members'] ?? []));
        $groups = array_map('strval', (array) ($rule['groups'] ?? []));
        $ids = $direct;
        foreach ($org['members'] as $m) {
            if (array_intersect($groups, (array) ($m['departments'] ?? []))) {
                $ids[] = $m['id'];
            }
        }

        return array_values(array_unique(array_intersect($ids, array_column($org['members'], 'id'))));
    }

    /** Service ids a given person may open. */
    public static function serviceIdsFor(array $org, string $memberId): array
    {
        $out = [];
        foreach (array_keys($org['access']) as $serviceId) {
            if (in_array($memberId, self::effectiveMemberIds($org, (string) $serviceId), true)) {
                $out[] = (string) $serviceId;
            }
        }

        return $out;
    }
}
