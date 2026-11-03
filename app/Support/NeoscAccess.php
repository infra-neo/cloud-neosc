<?php

namespace App\Support;

use App\Models\Client;
use App\Models\User;

/**
 * Where a signed-in person stands in the organization of the account they are
 * working in: owner, or the role the administrator gave them (admin, manager,
 * user), or none when the account has no organization record of them.
 */
final class NeoscAccess
{
    /** Pages a plain "user" never opens: buying, billing, domains and managing the organization. */
    public const USER_DENIED = '/^client\.(wizard|store|cart|domains|ssl|invoices|funds|quotes|payment-methods|emails|organization|branding|affiliates|account\.contacts|orders|checkout)/';

    /** @return array{role:string,client:?Client,org:?array,member:?array} */
    public static function context(?User $user): array
    {
        $none = ['role' => 'none', 'client' => null, 'org' => null, 'member' => null];
        if (! $user) {
            return $none;
        }

        $clients = $user->clients()->orderBy('clients.id')->get();
        $client = $clients->firstWhere('id', (int) session('active_client_id')) ?: $clients->first();
        if (! $client instanceof Client) {
            return $none;
        }
        if ($client->pivot?->owner) {
            return ['role' => 'owner', 'client' => $client, 'org' => null, 'member' => null];
        }

        $org = ClientOrg::get($client);
        foreach ($org['members'] as $m) {
            if (mb_strtolower((string) ($m['email'] ?? '')) === mb_strtolower($user->email)) {
                return ['role' => ClientOrg::normalizeRole($m['role'] ?? 'user'), 'client' => $client, 'org' => $org, 'member' => $m];
            }
        }

        return ['role' => 'none', 'client' => $client, 'org' => $org, 'member' => null];
    }

    public static function roleFor(?User $user): string
    {
        return self::context($user)['role'];
    }
}
