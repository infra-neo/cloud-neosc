<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Services\ZitadelOrgService;
use App\Support\ClientOrg;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "Organización": the customer's departments, people and roles. The local copy
 * is authoritative; NeoGuard (Zitadel) is kept in step and each record shows
 * its sync state, with a retry.
 */
class OrganizationController extends Controller
{
    use ResolvesClient;

    public function index(ZitadelOrgService $zitadel)
    {
        $client = $this->currentClient();
        abort_unless($client, 404);

        return view('client.organization.index', [
            'client' => $client,
            'org' => ClientOrg::get($client),
            'canManage' => $this->canManage($client),
            'zitadelOn' => $zitadel->enabled(),
            'roles' => ClientOrg::ROLES,
        ]);
    }

    public function storeDepartment(Request $request, ZitadelOrgService $zitadel)
    {
        $client = $this->managedClient();
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);

        $org = ClientOrg::get($client);
        $name = trim($data['name']);
        foreach ($org['departments'] as $d) {
            if (mb_strtolower($d['name']) === mb_strtolower($name)) {
                return back()->withErrors(['name' => __('portal.org.dept_exists')])->withInput();
            }
        }

        $dept = ['id' => ClientOrg::newId(), 'name' => $name, 'project_id' => '', 'sync' => 'local', 'error' => ''];
        if ($zitadel->enabled()) {
            $r = $zitadel->createDepartment($client, $name.' ('.$client->id.')');
            $dept['project_id'] = $r['id'] ?? '';
            $dept['sync'] = $r['ok'] ? 'ok' : 'failed';
            $dept['error'] = $r['ok'] ? '' : (string) $r['error'];
        }
        $org['departments'][] = $dept;
        ClientOrg::save($client, $org);

        return back()->with('success', __('portal.org.dept_saved'));
    }

    public function destroyDepartment(string $department, ZitadelOrgService $zitadel)
    {
        $client = $this->managedClient();
        $org = ClientOrg::get($client);

        foreach ($org['departments'] as $i => $d) {
            if ($d['id'] !== $department) {
                continue;
            }
            if ($zitadel->enabled() && ($d['project_id'] ?? '') !== '') {
                $zitadel->deleteDepartment($client, $d['project_id']);
            }
            unset($org['departments'][$i]);
            foreach ($org['members'] as $j => $m) {
                $org['members'][$j]['departments'] = array_values(array_diff((array) ($m['departments'] ?? []), [$department]));
                unset($org['members'][$j]['grants'][$department]);
            }
        }
        ClientOrg::save($client, $org);

        return back()->with('success', __('portal.org.dept_deleted'));
    }

    public function storeMember(Request $request, ZitadelOrgService $zitadel)
    {
        $client = $this->managedClient();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'role' => ['required', 'in:'.implode(',', ClientOrg::ROLES)],
            'departments' => ['array'],
        ]);

        $org = ClientOrg::get($client);
        $email = mb_strtolower(trim($data['email']));
        foreach ($org['members'] as $m) {
            if (mb_strtolower($m['email']) === $email) {
                return back()->withErrors(['email' => __('portal.org.member_exists')])->withInput();
            }
        }

        $member = [
            'id' => ClientOrg::newId(),
            'name' => trim($data['name']),
            'email' => $email,
            'role' => ClientOrg::normalizeRole($data['role']),
            'departments' => ClientOrg::cleanDepartments($org, $data['departments'] ?? []),
            'zitadel_user_id' => '',
            'grants' => [],
            'status' => 'active',
            'sync' => 'local',
            'error' => '',
            'created_at' => now()->toDateTimeString(),
        ];

        // One-time temporary password: shown once, never stored.
        $temp = null;
        if ($zitadel->enabled()) {
            $temp = Str::password(16);
            $this->sync($zitadel, $client, $org, $member, $temp);
        }
        $org['members'][] = $member;
        ClientOrg::save($client, $org);

        $redirect = back()->with('success', __('portal.org.member_saved'));

        return $temp && $member['sync'] === 'ok'
            ? $redirect->with('nsc_temp_password', ['email' => $member['email'], 'password' => $temp])
            : $redirect;
    }

    public function updateMember(Request $request, string $member, ZitadelOrgService $zitadel)
    {
        $client = $this->managedClient();
        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', ClientOrg::ROLES)],
            'departments' => ['array'],
        ]);

        $org = ClientOrg::get($client);
        $i = ClientOrg::findMember($org, $member);
        abort_if($i === null, 404);

        $org['members'][$i]['role'] = ClientOrg::normalizeRole($data['role']);
        $org['members'][$i]['departments'] = ClientOrg::cleanDepartments($org, $data['departments'] ?? []);
        if ($zitadel->enabled()) {
            $this->sync($zitadel, $client, $org, $org['members'][$i]);
        }
        ClientOrg::save($client, $org);

        return back()->with('success', __('portal.org.member_saved'));
    }

    public function resync(string $member, ZitadelOrgService $zitadel)
    {
        $client = $this->managedClient();
        $org = ClientOrg::get($client);
        $i = ClientOrg::findMember($org, $member);
        abort_if($i === null, 404);

        $this->syncDepartments($zitadel, $client, $org);
        if ($zitadel->enabled()) {
            $this->sync($zitadel, $client, $org, $org['members'][$i]);
        }
        ClientOrg::save($client, $org);

        return back()->with('success', __('portal.org.resynced'));
    }

    public function toggleMember(string $member, ZitadelOrgService $zitadel)
    {
        $client = $this->managedClient();
        $org = ClientOrg::get($client);
        $i = ClientOrg::findMember($org, $member);
        abort_if($i === null, 404);

        $active = ($org['members'][$i]['status'] ?? 'active') !== 'active';
        $org['members'][$i]['status'] = $active ? 'active' : 'suspended';
        if ($zitadel->enabled() && ($org['members'][$i]['zitadel_user_id'] ?? '') !== '') {
            $zitadel->setActive($client, $org['members'][$i]['zitadel_user_id'], $active);
        }
        ClientOrg::save($client, $org);

        return back()->with('success', __('portal.org.member_saved'));
    }

    public function destroyMember(string $member, ZitadelOrgService $zitadel)
    {
        $client = $this->managedClient();
        $org = ClientOrg::get($client);
        $i = ClientOrg::findMember($org, $member);
        abort_if($i === null, 404);

        if ($zitadel->enabled() && ($org['members'][$i]['zitadel_user_id'] ?? '') !== '') {
            $zitadel->removeUser($client, $org['members'][$i]['zitadel_user_id']);
        }
        unset($org['members'][$i]);
        ClientOrg::save($client, $org);

        return back()->with('success', __('portal.org.member_deleted'));
    }

    /** Retry creating the project of any department that failed earlier. */
    private function syncDepartments(ZitadelOrgService $zitadel, $client, array &$org): void
    {
        if (! $zitadel->enabled()) {
            return;
        }
        foreach ($org['departments'] as $i => $d) {
            if (($d['project_id'] ?? '') !== '') {
                continue;
            }
            $r = $zitadel->createDepartment($client, $d['name'].' ('.$client->id.')');
            $org['departments'][$i]['project_id'] = $r['id'] ?? '';
            $org['departments'][$i]['sync'] = $r['ok'] ? 'ok' : 'failed';
            $org['departments'][$i]['error'] = $r['ok'] ? '' : (string) $r['error'];
        }
    }

    private function sync(ZitadelOrgService $zitadel, $client, array $org, array &$member, ?string $temp = null): void
    {
        $projects = [];
        foreach ($org['departments'] as $d) {
            if (($d['project_id'] ?? '') !== '') {
                $projects[$d['id']] = $d['project_id'];
            }
        }
        $r = $zitadel->syncMember($client, $member, $projects, $temp);
        if ($r['ok']) {
            $member['zitadel_user_id'] = $r['user_id'] ?? $member['zitadel_user_id'];
            $member['grants'] = $r['grants'] ?? [];
            $member['sync'] = 'ok';
            $member['error'] = '';
        } else {
            $member['sync'] = 'failed';
            $member['error'] = (string) $r['error'];
        }
    }

    /** Only the account owner changes the organization; everyone in it may look. */
    private function canManage($client): bool
    {
        $row = auth()->user()->clients()->where('clients.id', $client->id)->first();

        return (bool) ($row?->pivot?->owner);
    }

    private function managedClient()
    {
        $client = $this->currentClient();
        abort_unless($client, 404);
        abort_unless($this->canManage($client), 403);

        return $client;
    }
}
