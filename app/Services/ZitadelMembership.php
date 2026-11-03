<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use App\Models\ZitadelProvisioning;
use App\Support\ClientOrg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * What a NeoGuard (Zitadel) sign-in says about a person's company, applied to
 * the portal: they belong to the customer whose Zitadel organization they
 * signed in from (never a second, empty customer), and their role, groups and
 * status in that organization follow the project roles in the token
 * (role_*, grp_<group id>), which the customer's administrator sets in
 * Organización.
 */
class ZitadelMembership
{
    /**
     * A person who exists in a customer's Zitadel organization but not here yet
     * (created in the console, or by the administrator): make the user, without
     * a new customer. Null when the organization is not one of ours.
     */
    public function userFor(array $who, Request $request): ?User
    {
        if ($this->clientFor($who) === null || ($who['email'] ?? '') === '') {
            return null;
        }

        return User::create([
            'first_name' => ($who['given_name'] ?? '') ?: (strstr($who['email'], '@', true) ?: $who['email']),
            'last_name' => ($who['family_name'] ?? '') ?: '-',
            'email' => $who['email'],
            'password' => Hash::make(Str::random(40)),
        ]);
    }

    /** Attach the person to their organization's customer and copy roles and groups. */
    public function sync(User $user, array $who): ?Client
    {
        $client = $this->clientFor($who);
        if ($client === null) {
            return null;
        }

        if (! $user->clients()->where('clients.id', $client->id)->exists()) {
            $user->clients()->attach($client->id, ['owner' => false]);
        }

        $org = ClientOrg::get($client);
        $roles = array_map('strval', (array) ($who['roles'] ?? []));
        $role = 'user';
        foreach (['admin', 'manager'] as $candidate) {
            if (in_array('role_'.$candidate, $roles, true)) {
                $role = $candidate;
                break;
            }
        }
        $groups = [];
        foreach ($roles as $r) {
            if (str_starts_with($r, 'grp_')) {
                $groups[] = substr($r, 4);
            }
        }
        $groups = ClientOrg::cleanDepartments($org, $groups);

        $i = null;
        foreach ($org['members'] as $k => $m) {
            if (mb_strtolower((string) ($m['email'] ?? '')) === mb_strtolower($user->email)) {
                $i = $k;
                break;
            }
        }
        if ($i === null) {
            $org['members'][] = [
                'id' => ClientOrg::newId(),
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => mb_strtolower($user->email),
                'role' => $role, 'departments' => $groups, 'grants' => [], 'access_grant' => '',
                'zitadel_user_id' => (string) ($who['sub'] ?? ''), 'status' => 'active',
                'sync' => 'ok', 'error' => '', 'created_at' => now()->toDateTimeString(),
            ];
        } elseif ($roles !== []) {
            // The token is the truth about roles once it carries any.
            $org['members'][$i]['role'] = $role;
            $org['members'][$i]['departments'] = $groups;
            $org['members'][$i]['zitadel_user_id'] = (string) ($who['sub'] ?? ($org['members'][$i]['zitadel_user_id'] ?? ''));
        } else {
            $org['members'][$i]['zitadel_user_id'] = $org['members'][$i]['zitadel_user_id'] ?: (string) ($who['sub'] ?? '');
        }
        ClientOrg::save($client, $org);

        session(['active_client_id' => $client->id]);

        return $client;
    }

    private function clientFor(array $who): ?Client
    {
        $orgId = (string) ($who['org_id'] ?? '');
        if ($orgId === '') {
            return null;
        }
        $clientId = ZitadelProvisioning::where('zitadel_organization_id', $orgId)->value('client_id');

        return $clientId ? Client::find($clientId) : null;
    }
}
