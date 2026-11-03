<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ZitadelProvisioning;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Departments, users and roles of a customer's organization in NeoGuard
 * (Zitadel). Department = project (with the roles admin/manager/user),
 * person = human user of the customer's organization, access = user grant.
 *
 * Never throws: every method answers ['ok'=>bool, 'error'=>?string, ...] so the
 * portal keeps the local copy and shows the sync state. Uses the same service
 * account as ZitadelProvisioningService.
 */
class ZitadelOrgService
{
    public const ROLE_NAMES = ['admin' => 'Administrador', 'manager' => 'Responsable', 'user' => 'Usuario'];

    public function enabled(): bool
    {
        return (bool) config('services.zitadel.enabled');
    }

    public function organizationId(Client $client): ?string
    {
        $id = ZitadelProvisioning::where('client_id', $client->id)->value('zitadel_organization_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** @return array{ok:bool,error:?string,id?:string} */
    public function createDepartment(Client $client, string $name): array
    {
        return $this->guard($client, function (string $org) use ($name) {
            $project = $this->call('POST', '/management/v1/projects', ['name' => $name], $org);
            $id = $project['id'] ?? null;
            if (! is_string($id) || $id === '') {
                return ['ok' => false, 'error' => 'No project id returned'];
            }
            foreach (self::ROLE_NAMES as $key => $display) {
                $this->call('POST', "/management/v1/projects/{$id}/roles", [
                    'roleKey' => $key, 'displayName' => $display, 'group' => 'neosc',
                ], $org, true);
            }

            return ['ok' => true, 'error' => null, 'id' => $id];
        });
    }

    /** @return array{ok:bool,error:?string} */
    public function deleteDepartment(Client $client, string $projectId): array
    {
        return $this->guard($client, function (string $org) use ($projectId) {
            $this->call('DELETE', "/management/v1/projects/{$projectId}", [], $org, true);

            return ['ok' => true, 'error' => null];
        });
    }

    /**
     * Make Zitadel match one member: user exists, has exactly the grants for
     * the given departments with the member's role. Returns the ids to store.
     *
     * @param array<string,mixed> $member   local member record
     * @param array<string,string> $projects department id => zitadel project id
     * @return array{ok:bool,error:?string,user_id?:string,grants?:array<string,string>}
     */
    public function syncMember(Client $client, array $member, array $projects, ?string $tempPassword = null): array
    {
        return $this->guard($client, function (string $org) use ($member, $projects, $tempPassword) {
            $userId = (string) ($member['zitadel_user_id'] ?? '');
            if ($userId === '') {
                [$first, $last] = array_pad(explode(' ', trim((string) $member['name']), 2), 2, '');
                $body = [
                    'username' => $member['email'],
                    'organization' => ['orgId' => $org],
                    'profile' => ['givenName' => $first ?: $member['email'], 'familyName' => $last ?: '-', 'displayName' => $member['name']],
                    'email' => ['email' => $member['email'], 'isVerified' => true],
                ];
                if ($tempPassword) {
                    $body['password'] = ['password' => $tempPassword, 'changeRequired' => true];
                }
                $created = $this->call('POST', '/v2/users/human', $body, $org);
                $userId = (string) ($created['userId'] ?? '');
                if ($userId === '') {
                    return ['ok' => false, 'error' => 'No user id returned'];
                }
            }

            $grants = (array) ($member['grants'] ?? []);
            $roleKeys = [\App\Support\ClientOrg::normalizeRole((string) $member['role'])];

            foreach ($projects as $deptId => $projectId) {
                if (! in_array($deptId, (array) ($member['departments'] ?? []), true)) {
                    continue;
                }
                if (! empty($grants[$deptId])) {
                    $this->call('PUT', "/management/v1/users/{$userId}/grants/{$grants[$deptId]}", ['roleKeys' => $roleKeys], $org);
                } else {
                    $g = $this->call('POST', "/management/v1/users/{$userId}/grants", ['projectId' => $projectId, 'roleKeys' => $roleKeys], $org);
                    if (! empty($g['userGrantId'])) {
                        $grants[$deptId] = $g['userGrantId'];
                    }
                }
            }
            foreach ($grants as $deptId => $grantId) {
                if (! in_array($deptId, (array) ($member['departments'] ?? []), true)) {
                    $this->call('DELETE', "/management/v1/users/{$userId}/grants/{$grantId}", [], $org, true);
                    unset($grants[$deptId]);
                }
            }

            return ['ok' => true, 'error' => null, 'user_id' => $userId, 'grants' => $grants];
        });
    }

    /**
     * Which services a person may open, as project roles `res_<service id>` on
     * the customer's main project; one grant per person carries them all.
     *
     * @param array<string,mixed> $member
     * @param array<string,string> $labels service id => name (shown in NeoGuard)
     * @param list<string> $serviceIds
     * @return array{ok:bool,error:?string,grant_id?:string}
     */
    public function syncAccess(Client $client, array $member, array $serviceIds, array $labels): array
    {
        return $this->guard($client, function (string $org) use ($client, $member, $serviceIds, $labels) {
            $project = ZitadelProvisioning::where('client_id', $client->id)->value('zitadel_project_id');
            $userId = (string) ($member['zitadel_user_id'] ?? '');
            if (! is_string($project) || $project === '' || $userId === '') {
                return ['ok' => false, 'error' => 'project-or-user-missing'];
            }
            $grantId = (string) ($member['access_grant'] ?? '');
            $roleKeys = array_map(fn ($id) => 'res_'.$id, $serviceIds);

            if ($roleKeys === []) {
                if ($grantId !== '') {
                    $this->call('DELETE', "/management/v1/users/{$userId}/grants/{$grantId}", [], $org, true);
                }

                return ['ok' => true, 'error' => null, 'grant_id' => ''];
            }
            foreach ($serviceIds as $id) {
                $this->call('POST', "/management/v1/projects/{$project}/roles", [
                    'roleKey' => 'res_'.$id, 'displayName' => mb_substr($labels[$id] ?? ('Servicio '.$id), 0, 60), 'group' => 'recursos',
                ], $org, true);
            }
            if ($grantId !== '') {
                $this->call('PUT', "/management/v1/users/{$userId}/grants/{$grantId}", ['roleKeys' => $roleKeys], $org);
            } else {
                $g = $this->call('POST', "/management/v1/users/{$userId}/grants", ['projectId' => $project, 'roleKeys' => $roleKeys], $org);
                $grantId = (string) ($g['userGrantId'] ?? '');
            }

            return ['ok' => true, 'error' => null, 'grant_id' => $grantId];
        });
    }

    /** @return array{ok:bool,error:?string} */
    public function removeUser(Client $client, string $userId): array
    {
        return $this->guard($client, function (string $org) use ($userId) {
            $this->call('DELETE', "/management/v1/users/{$userId}", [], $org, true);

            return ['ok' => true, 'error' => null];
        });
    }

    /** @return array{ok:bool,error:?string} */
    public function setActive(Client $client, string $userId, bool $active): array
    {
        return $this->guard($client, function (string $org) use ($userId, $active) {
            $this->call('POST', "/management/v1/users/{$userId}/".($active ? '_reactivate' : '_deactivate'), [], $org);

            return ['ok' => true, 'error' => null];
        });
    }

    private function guard(Client $client, callable $fn): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'error' => 'disabled'];
        }
        $org = $this->organizationId($client);
        if ($org === null && auth()->check()) {
            // The sign-up provisioning never finished: do it now with the signed-in owner.
            try {
                app(ZitadelProvisioningService::class)->provision($client, auth()->user());
            } catch (\Throwable $e) {
                Log::warning('Zitadel late provisioning failed', ['client_id' => $client->id, 'error' => $e->getMessage()]);
            }
            $org = $this->organizationId($client);
        }
        if ($org === null) {
            $why = ZitadelProvisioning::where('client_id', $client->id)->value('last_error');

            return ['ok' => false, 'error' => 'organization-not-provisioned'.($why ? ': '.$why : '')];
        }
        try {
            return $fn($org);
        } catch (\Throwable $e) {
            Log::warning('Zitadel org sync failed', ['client_id' => $client->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 200)];
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function call(string $method, string $path, array $payload, string $org, bool $tolerateExisting = false): array
    {
        $issuer = rtrim((string) config('services.zitadel.issuer'), '/');
        try {
            $req = Http::withToken($this->token($issuer))->acceptJson()
                ->timeout((int) config('services.zitadel.timeout', 15))
                ->withHeaders(['x-zitadel-orgid' => $org]);
            $response = $method === 'DELETE' || $method === 'GET'
                ? $req->send($method, $issuer.$path)
                : $req->send($method, $issuer.$path, ['json' => (object) $payload]);
        } catch (ConnectionException) {
            throw new \RuntimeException('Could not connect to NeoGuard');
        }

        // Already there / already gone is the state we wanted.
        if ($tolerateExisting && in_array($response->status(), [404, 409], true)) {
            return [];
        }
        if (! $response->successful()) {
            throw new \RuntimeException(sprintf('NeoGuard HTTP %d on %s %s: %s', $response->status(), $method, preg_replace('#/[0-9]{6,}#', '/{id}', $path), mb_substr((string) ($response->json('message') ?? $response->body()), 0, 140)));
        }

        return (array) $response->json();
    }

    private function token(string $issuer): string
    {
        $t = app(ZitadelDirectory::class);
        $m = new \ReflectionMethod($t, 'token');
        $m->setAccessible(true);
        $token = $m->invoke($t, $issuer);
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Could not get a NeoGuard access token');
        }

        return $token;
    }
}
