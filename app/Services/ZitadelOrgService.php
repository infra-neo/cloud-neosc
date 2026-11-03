<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ZitadelProvisioning;
use App\Support\ClientOrg;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * People, groups and access of a customer's organization in NeoGuard (Zitadel).
 *
 * The application (OIDC client) lives in ONE project of the main organization
 * (ZITADEL_MAIN_PROJECT_ID). Each customer organization receives that project
 * through a Project Grant, and every person gets one user grant on it. What
 * they may do travels in the token as project roles:
 *
 *   role_admin | role_manager | role_user   their role in the organization
 *   grp_<group id>                          each group they belong to
 *   res_<service id>                        each server/desktop they may open
 *
 * Never throws: every method answers ['ok'=>bool,'error'=>?string,...] so the
 * portal keeps its own copy and shows the sync state.
 */
class ZitadelOrgService
{
    /** @var array<int,string> client id => project grant id, per request */
    private array $prepared = [];

    public function enabled(): bool
    {
        return (bool) config('services.zitadel.enabled');
    }

    public function mainProjectId(): string
    {
        return trim((string) config('services.zitadel.main_project_id'));
    }

    public function organizationId(Client $client): ?string
    {
        $id = ZitadelProvisioning::where('client_id', $client->id)->value('zitadel_organization_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Make the main project, its roles and the customer's project grant match
     * the organization: call before syncing people. Cached per request.
     *
     * @param array<string,string> $labels service id => name
     * @return array{ok:bool,error:?string}
     */
    public function prepare(Client $client, array $org, array $labels): array
    {
        return $this->guard($client, function (string $customerOrg) use ($client, $org, $labels) {
            $project = $this->mainProjectId();
            if ($project === '') {
                return ['ok' => false, 'error' => 'ZITADEL_MAIN_PROJECT_ID is not set'];
            }

            $roles = ['role_admin' => 'Administrador', 'role_manager' => 'Responsable', 'role_user' => 'Usuario'];
            foreach ($org['departments'] as $d) {
                $roles['grp_'.$d['id']] = 'Grupo: '.$d['name'];
            }
            foreach ($labels as $id => $label) {
                $roles['res_'.$id] = 'Recurso: '.$label;
            }

            foreach ($roles as $key => $display) {
                $this->call('POST', "/management/v1/projects/{$project}/roles", [
                    'roleKey' => $key, 'displayName' => mb_substr($display, 0, 80), 'group' => str_starts_with($key, 'res_') ? 'recursos' : (str_starts_with($key, 'grp_') ? 'grupos' : 'roles'),
                ], null, true);
            }

            $grantId = $this->findProjectGrant($project, $customerOrg);
            $keys = array_keys($roles);
            if ($grantId === '') {
                $g = $this->call('POST', "/management/v1/projects/{$project}/grants", ['grantedOrgId' => $customerOrg, 'roleKeys' => $keys], null);
                $grantId = (string) ($g['grantId'] ?? '');
                if ($grantId === '') {
                    $grantId = $this->findProjectGrant($project, $customerOrg);
                }
            } else {
                $this->call('PUT', "/management/v1/projects/{$project}/grants/{$grantId}", ['roleKeys' => $keys], null);
            }
            if ($grantId === '') {
                return ['ok' => false, 'error' => 'Could not create the project grant for the organization'];
            }
            $this->prepared[(int) $client->id] = $grantId;

            return ['ok' => true, 'error' => null];
        });
    }

    /**
     * Make Zitadel match one person: the user exists and has one grant on the
     * customer's project with exactly their roles.
     *
     * @param array<string,mixed> $member local record
     * @param list<string> $serviceIds services they may open
     * @return array{ok:bool,error:?string,user_id?:string,grant_id?:string}
     */
    public function syncMember(Client $client, array $member, array $serviceIds, ?string $tempPassword = null): array
    {
        return $this->guard($client, function (string $org) use ($client, $member, $serviceIds, $tempPassword) {
            $project = $this->mainProjectId();
            $projectGrantId = $this->prepared[(int) $client->id] ?? '';
            if ($project === '' || $projectGrantId === '') {
                return ['ok' => false, 'error' => 'project-not-prepared'];
            }

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

            $roleKeys = ['role_'.ClientOrg::normalizeRole((string) $member['role'])];
            foreach ((array) ($member['departments'] ?? []) as $g) {
                $roleKeys[] = 'grp_'.$g;
            }
            foreach ($serviceIds as $s) {
                $roleKeys[] = 'res_'.$s;
            }

            $grantId = (string) ($member['access_grant'] ?? '');
            if ($grantId === '') {
                $grantId = $this->findUserGrant($userId, $project, $org);
            }
            if ($grantId !== '') {
                $this->call('PUT', "/management/v1/users/{$userId}/grants/{$grantId}", ['roleKeys' => $roleKeys], $org);
            } else {
                $g = $this->call('POST', "/management/v1/users/{$userId}/grants", [
                    'projectId' => $project, 'projectGrantId' => $projectGrantId, 'roleKeys' => $roleKeys,
                ], $org);
                $grantId = (string) ($g['userGrantId'] ?? '');
            }

            return ['ok' => true, 'error' => null, 'user_id' => $userId, 'grant_id' => $grantId];
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

    private function findProjectGrant(string $project, string $customerOrg): string
    {
        $r = $this->call('POST', "/management/v1/projects/{$project}/grants/_search", [
            'queries' => [['grantedOrgIdQuery' => ['grantedOrgId' => $customerOrg]]],
        ], null);

        return (string) ($r['result'][0]['grantId'] ?? '');
    }

    private function findUserGrant(string $userId, string $project, string $org): string
    {
        $r = $this->call('POST', '/management/v1/users/grants/_search', [
            'queries' => [['userIdQuery' => ['userId' => $userId]], ['projectIdQuery' => ['projectId' => $project]]],
        ], $org);

        return (string) ($r['result'][0]['id'] ?? ($r['result'][0]['userGrantId'] ?? ''));
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

            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 220)];
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @param string|null $org customer org id; null = the service account's own (main) organization
     * @return array<string,mixed>
     */
    private function call(string $method, string $path, array $payload, ?string $org, bool $tolerateExisting = false): array
    {
        $issuer = rtrim((string) config('services.zitadel.issuer'), '/');
        $header = $org ?? trim((string) config('services.zitadel.main_org_id'));
        try {
            $req = Http::withToken($this->token($issuer))->acceptJson()
                ->timeout((int) config('services.zitadel.timeout', 15));
            if ($header !== '') {
                $req = $req->withHeaders(['x-zitadel-orgid' => $header]);
            }
            $response = in_array($method, ['DELETE', 'GET'], true)
                ? $req->send($method, $issuer.$path)
                : $req->send($method, $issuer.$path, ['json' => (object) $payload]);
        } catch (ConnectionException) {
            throw new \RuntimeException('Could not connect to NeoGuard');
        }

        $message = (string) ($response->json('message') ?? '');
        // Already there / already gone / nothing to change is the state we wanted.
        if (! $response->successful()) {
            $unchanged = $response->status() === 400 && preg_match('/has not been changed|not changed|no changes|already exists/i', $message);
            if ($unchanged || ($tolerateExisting && in_array($response->status(), [404, 409], true)) || $response->status() === 409) {
                return [];
            }
            throw new \RuntimeException(sprintf('NeoGuard HTTP %d on %s %s: %s', $response->status(), $method, preg_replace('#/[0-9]{6,}#', '/{id}', $path), mb_substr($message !== '' ? $message : $response->body(), 0, 140)));
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
