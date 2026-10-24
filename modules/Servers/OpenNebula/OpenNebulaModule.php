<?php

namespace Modules\Servers\OpenNebula;

use App\Contracts\VpsModuleInterface;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Cache;
use Modules\Servers\AbstractServerModule;
use SimpleXMLElement;

/**
 * OpenNebula (KVM): virtual machines sold and run from the billing portal.
 *
 * Gives a service the same virtual server panel the Proxmox module has: live
 * status, power, usage graphs, snapshots and (when the template allows it) a
 * root password. Reinstall and backups are not offered; vpsFeatures() leaves
 * them out and the panel hides them.
 *
 * The plan lives in the OpenNebula template, not in PNLCS: the product picks a
 * template as its "package" (listPackages() lists them), and CPU, memory,
 * disk, network and image come from it. Two attributes in the template's own
 * user template steer the panel:
 *
 *   PNLCS_SNAPSHOTS="3"    snapshots a customer may keep (absent: none)
 *   PNLCS_PASSWORD="YES"   offer a root/administrator password change
 *
 * Every VM this module creates or links carries PNLCS_SERVICE="<service id>" in
 * its user template. Anything that stops, changes or deletes a VM first checks
 * that mark, so a wrong or stale VM id in the billing records can never touch a
 * machine the operator runs for other reasons. The OpenNebula user PNLCS signs
 * in as should be in its own group, with rights over that group's VMs only.
 *
 * A VM that something else created (a OneFlow service, a hook) is picked up by
 * name: the server's "name_pattern" setting, NEOSC-VDI-CLI{client}-SRV{service}
 * by default, is looked up the first time the service page is opened, and the
 * VM found is marked and linked. Staff can also link one by id.
 */
class OpenNebulaModule extends AbstractServerModule implements VpsModuleInterface
{
    public const MARK = 'PNLCS_SERVICE';

    public const DEFAULT_NAME_PATTERN = 'NEOSC-VDI-CLI{client}-SRV{service}';

    /** Power actions a customer may run from the client area. */
    public const CLIENT_POWER_ACTIONS = ['start', 'shutdown', 'reboot', 'stop', 'reset'];

    public const SNAPSHOT_NAME = '/^[A-Za-z][A-Za-z0-9_-]{1,39}$/';

    // VM states
    private const ST_PENDING = 1;

    private const ST_HOLD = 2;

    private const ST_ACTIVE = 3;

    private const ST_STOPPED = 4;

    private const ST_SUSPENDED = 5;

    private const ST_DONE = 6;

    private const ST_POWEROFF = 8;

    private const ST_UNDEPLOYED = 9;

    private const ST_CLONING = 10;

    // LCM states that matter on their own
    private const LCM_RUNNING = 3;

    private const LCM_UNKNOWN = 16;

    private const LCM_NAMES = [
        1 => 'prolog', 2 => 'boot', 4 => 'migrating', 12 => 'shutdown', 17 => 'hotplug', 18 => 'shutdown',
        24 => 'snapshot', 14 => 'failure', 16 => 'unknown',
    ];

    public function getModuleName(): string
    {
        return 'opennebula';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'username', 'label' => 'OpenNebula user', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password'],
            ['name' => 'access_hash', 'label' => 'Login token (instead of the password)', 'type' => 'password'],
        ];
    }

    public function client(Server $server): OpenNebulaClient
    {
        return new OpenNebulaClient($server, $this->serverHost($server));
    }

    // =========================================================================
    // Screens
    // =========================================================================

    /** The server's address is the hypervisor's, not the customer's business. */
    public function servicePageHides(): array
    {
        return ['server_details', 'change_password'];
    }

    public function isProvisioned(Service $service): ?bool
    {
        return $this->vmId($service) !== null;
    }

    public function accountOnServer(Service $service): ?bool
    {
        $vm = $this->vm($service);
        if ($vm instanceof OpenNebulaResult) {
            return $vm->isMissing() ? false : null;
        }

        return true;
    }

    // =========================================================================
    // The VM behind a service
    // =========================================================================

    private function vmId(Service $service): ?int
    {
        $id = $this->getModuleData($service)['opennebula_vmid'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    private function mark(Service $service): string
    {
        return (string) $service->id;
    }

    public function owns(Service $service, SimpleXMLElement $vm): bool
    {
        return (string) ($vm->USER_TEMPLATE->{self::MARK} ?? '') === $this->mark($service);
    }

    /**
     * The VM behind a service, checked to be the one PNLCS made or linked for it.
     *
     * @return array{id: int, xml: SimpleXMLElement, state: int, lcm: int, api: OpenNebulaClient}|OpenNebulaResult
     */
    private function vm(Service $service, ?OpenNebulaClient $api = null): array|OpenNebulaResult
    {
        $server = $service->server ?? ($service->server_id ? Server::find($service->server_id) : null);
        $id = $this->vmId($service);
        if (! $server || $id === null) {
            return OpenNebulaResult::failed(__('opennebula.error.no_vm'));
        }

        $api ??= $this->client($server);
        $info = $api->call('one.vm.info', [$id]);
        if (! $info->ok) {
            return $info->isMissing()
                ? new OpenNebulaResult(false, OpenNebulaResult::NO_EXISTS, null, __('opennebula.error.vm_gone', ['id' => $id]))
                : $info;
        }

        $xml = OpenNebulaClient::xml($info->data);
        if (! $xml) {
            return OpenNebulaResult::failed(__('opennebula.error.not_xmlrpc'));
        }
        // A VM in DONE is deleted: oned still answers for it for a while.
        if ((int) $xml->STATE === self::ST_DONE) {
            return new OpenNebulaResult(false, OpenNebulaResult::NO_EXISTS, null, __('opennebula.error.vm_gone', ['id' => $id]));
        }
        if (! $this->owns($service, $xml)) {
            return new OpenNebulaResult(false, 409, null, __('opennebula.error.not_ours', ['id' => $id, 'mark' => self::MARK]));
        }

        return ['id' => $id, 'xml' => $xml, 'state' => (int) $xml->STATE, 'lcm' => (int) $xml->LCM_STATE, 'api' => $api];
    }

    private function busy(array $vm): ?array
    {
        if ($vm['state'] === self::ST_ACTIVE && $vm['lcm'] !== self::LCM_RUNNING && $vm['lcm'] !== self::LCM_UNKNOWN) {
            return $this->buildResult(false, __('opennebula.error.busy'));
        }

        return null;
    }

    /** Write attributes into the VM's user template, keeping the rest. */
    private function tag(OpenNebulaClient $api, int $id, array $attrs): OpenNebulaResult
    {
        $body = collect($attrs)->map(fn ($v, $k) => $k.'="'.addcslashes((string) $v, '"\\').'"')->implode("\n");

        return $api->call('one.vm.update', [$id, $body, 1]);
    }

    // =========================================================================
    // Create, link, suspend, remove
    // =========================================================================

    public function listPackages(Server $server): array
    {
        $r = $this->client($server)->call('one.templatepool.info', [-2, -1, -1]);
        $xml = $r->ok ? OpenNebulaClient::xml($r->data) : null;
        if (! $xml) {
            return [];
        }

        $out = [];
        foreach ($xml->VMTEMPLATE as $t) {
            $out[] = ['id' => (string) $t->ID, 'name' => (string) $t->NAME];
        }

        return $out;
    }

    /** The name a VM made for this service is given, and looked up by. */
    public function vmName(Service $service, ?Server $server = null): string
    {
        $pattern = (string) (($server ?? $service->server)?->setting('name_pattern', self::DEFAULT_NAME_PATTERN) ?? self::DEFAULT_NAME_PATTERN);

        return strtr($pattern, ['{client}' => (string) $service->client_id, '{service}' => (string) $service->id]);
    }

    public function create(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, __('opennebula.error.no_server'));
        }
        $api = $this->client($server);

        // Already built (the order ran twice, or a flow outside PNLCS made it).
        if ($this->vmId($service) !== null) {
            $vm = $this->vm($service, $api);
            if (is_array($vm)) {
                return $this->buildResult(true, __('opennebula.created_already'), ['vmid' => $vm['id']]);
            }
        }
        if ($found = $this->adoptByName($service, $server, $api)) {
            return $this->buildResult(true, __('opennebula.linked', ['id' => $found]), ['vmid' => $found]);
        }

        $template = (int) $this->getRemotePackage($service);
        if ($template <= 0) {
            return $this->buildResult(false, __('opennebula.error.no_template'));
        }

        $extra = self::MARK.'="'.$this->mark($service).'"';
        $context = [];
        if (! empty($service->username)) {
            $context[] = 'USERNAME="'.addcslashes((string) $service->username, '"\\').'"';
        }
        if ($service->password) {
            $context[] = 'PASSWORD="'.addcslashes((string) $service->password, '"\\').'"';
        }
        if (($keys = trim($this->storedSshKeys($service))) !== '') {
            $context[] = 'SSH_PUBLIC_KEY="'.addcslashes($keys, '"\\').'"';
        }
        // One CONTEXT block: a second would replace the first.
        if ($context !== []) {
            $extra .= "\nCONTEXT=[ ".implode(",\n  ", $context)." ]";
        }

        $r = $api->call('one.template.instantiate', [$template, $this->vmName($service, $server), false, $extra, false]);
        $result = $this->buildResult($r->ok, $r->ok ? __('opennebula.created', ['id' => (int) $r->data]) : $r->error, $r->ok ? ['vmid' => (int) $r->data] : []);
        if ($r->ok) {
            $this->setModuleData($service, ['opennebula_vmid' => (int) $r->data, 'opennebula_template' => $template]);
        }
        $this->logAction($service, 'create', $result);

        return $result;
    }

    /** The VM named for this service, when something else already built it. */
    private function adoptByName(Service $service, Server $server, OpenNebulaClient $api): ?int
    {
        $name = $this->vmName($service, $server);
        $pool = $api->call('one.vmpool.info', [-2, -1, -1, -1]);
        $xml = $pool->ok ? OpenNebulaClient::xml($pool->data) : null;
        if (! $xml) {
            return null;
        }

        foreach ($xml->VM as $vm) {
            if ((string) $vm->NAME !== $name) {
                continue;
            }
            $existing = (string) ($vm->USER_TEMPLATE->{self::MARK} ?? '');
            if ($existing !== '' && $existing !== $this->mark($service)) {
                return null; // belongs to another service: never take it over
            }
            $id = (int) $vm->ID;
            if ($existing === '' && ! $this->tag($api, $id, [self::MARK => $this->mark($service)])->ok) {
                return null;
            }
            $this->setModuleData($service, ['opennebula_vmid' => $id]);

            return $id;
        }

        return null;
    }

    /** Link a VM that already exists to this service ("Link to an existing account"). */
    public function claim(Service $service, int $vmid): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, __('opennebula.error.no_server'));
        }
        $api = $this->client($server);

        $info = $api->call('one.vm.info', [$vmid]);
        $xml = $info->ok ? OpenNebulaClient::xml($info->data) : null;
        if (! $xml) {
            return $this->buildResult(false, $info->ok ? __('opennebula.error.not_xmlrpc') : ($info->isMissing() ? __('opennebula.error.vm_gone', ['id' => $vmid]) : $info->error));
        }

        $existing = (string) ($xml->USER_TEMPLATE->{self::MARK} ?? '');
        if ($existing !== '' && $existing !== $this->mark($service)) {
            return $this->buildResult(false, __('opennebula.error.claimed', ['id' => $vmid, 'service' => $existing]));
        }
        if (Service::where('id', '!=', $service->id)->where('module_data', 'like', '%"opennebula_vmid":'.$vmid.'%')->exists()) {
            return $this->buildResult(false, __('opennebula.error.claimed_billing', ['id' => $vmid]));
        }

        $tagged = $this->tag($api, $vmid, [self::MARK => $this->mark($service)]);
        if (! $tagged->ok) {
            return $this->buildResult(false, $tagged->error);
        }
        $this->setModuleData($service, ['opennebula_vmid' => $vmid]);

        return $this->buildResult(true, __('opennebula.linked', ['id' => $vmid]), ['vmid' => $vmid]);
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        $vm = $this->vm($service);
        if ($vm instanceof OpenNebulaResult) {
            return $this->buildResult(false, $vm->error);
        }
        // Off, and not coming back by itself: a powered-off VM stays so across a host reboot.
        $result = in_array($vm['state'], [self::ST_POWEROFF, self::ST_UNDEPLOYED, self::ST_STOPPED], true)
            ? $this->buildResult(true, __('opennebula.already_off'))
            : $this->action($vm, 'poweroff-hard', __('opennebula.suspended'));
        $this->logAction($service, 'suspend', $result);

        return $result;
    }

    public function unsuspend(Service $service): array
    {
        $vm = $this->vm($service);
        if ($vm instanceof OpenNebulaResult) {
            return $this->buildResult(false, $vm->error);
        }
        $result = $this->power($service, 'start');
        $this->logAction($service, 'unsuspend', $result);

        return $result;
    }

    public function terminate(Service $service): array
    {
        $server = $service->server ?? ($service->server_id ? Server::find($service->server_id) : null);
        if (! $server || $this->vmId($service) === null) {
            return $this->buildResult(true, __('opennebula.nothing_to_remove'));
        }
        $api = $this->client($server);
        $vm = $this->vm($service, $api);

        // Already gone is what terminate wants.
        if ($vm instanceof OpenNebulaResult) {
            return $vm->isMissing()
                ? $this->buildResult(true, __('opennebula.nothing_to_remove'))
                : $this->buildResult(false, $vm->error);
        }

        $r = $api->call('one.vm.action', ['terminate-hard', $vm['id']]);
        $result = $this->buildResult($r->ok, $r->ok ? __('opennebula.terminated') : $r->error);
        if ($r->ok) {
            $this->setModuleData($service, ['opennebula_vmid' => null]);
        }
        $this->logAction($service, 'terminate', $result);

        return $result;
    }

    /** One one.vm.action on a checked VM. */
    private function action(array $vm, string $action, string $done): array
    {
        $r = $vm['api']->call('one.vm.action', [$action, $vm['id']]);

        return $this->buildResult($r->ok, $r->ok ? $done : $r->error);
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        return $this->buildResult(false, __('opennebula.error.resize_unsupported'));
    }

    public function usageUpdate(Server $server): array
    {
        return $this->buildResult(true, __('opennebula.no_usage'));
    }

    public function testConnection(Server $server): bool
    {
        $api = $this->client($server);
        if (! $api->call('one.system.version')->ok) {
            return false;
        }

        // A version call may need no sign-in; this one does.
        return $api->call('one.vmpool.info', [-3, -1, 1, -1])->ok;
    }

    // =========================================================================
    // What the panel shows
    // =========================================================================

    private function attr(SimpleXMLElement $xml, string $key): string
    {
        return trim((string) ($xml->USER_TEMPLATE->{$key} ?? ''));
    }

    /** Features the panel offers for this service. */
    public function vpsFeatures(Service $service): array
    {
        if ($this->vmId($service) === null) {
            $this->tryAdopt($service);
            if ($this->vmId($service) === null) {
                return [];
            }
        }

        $features = ['status', 'power', 'graphs'];
        $vm = $this->vm($service);
        if (is_array($vm)) {
            if ((int) $this->attr($vm['xml'], 'PNLCS_SNAPSHOTS') > 0) {
                $features[] = 'snapshots';
            }
            if (strtoupper($this->attr($vm['xml'], 'PNLCS_PASSWORD')) === 'YES') {
                $features[] = 'password';
            }
        }

        return $features;
    }

    /** Look for a VM something else built for this service, at most once a minute. */
    private function tryAdopt(Service $service): void
    {
        $server = $service->server ?? ($service->server_id ? Server::find($service->server_id) : null);
        if (! $server || ! Cache::add('opennebula-adopt-'.$service->id, 1, 60)) {
            return;
        }
        $this->adoptByName($service, $server, $this->client($server));
    }

    /** @return array{0: string, 1: string} status the panel knows, and what the VM is busy with */
    private function stateOf(int $state, int $lcm): array
    {
        return match (true) {
            $state === self::ST_ACTIVE && $lcm === self::LCM_RUNNING => ['running', ''],
            $state === self::ST_ACTIVE => ['running', self::LCM_NAMES[$lcm] ?? ($lcm >= 36 ? 'failure' : 'working')],
            in_array($state, [self::ST_POWEROFF, self::ST_UNDEPLOYED, self::ST_STOPPED, self::ST_SUSPENDED], true) => ['stopped', ''],
            in_array($state, [self::ST_PENDING, self::ST_HOLD, self::ST_CLONING], true) => ['stopped', 'pending'],
            default => ['stopped', 'working'],
        };
    }

    public function vmStatus(Service $service): array
    {
        $vm = $this->vm($service);
        if ($vm instanceof OpenNebulaResult) {
            return ['available' => false, 'error' => $vm->error];
        }

        $x = $vm['xml'];
        [$status, $lock] = $this->stateOf($vm['state'], $vm['lcm']);
        $running = $status === 'running' && $lock === '';

        $vcpu = max(1, (int) ($x->TEMPLATE->VCPU ?? 1));
        $memMax = (int) ($x->TEMPLATE->MEMORY ?? 0);
        $diskMb = (float) ($x->TEMPLATE->DISK[0]->SIZE ?? 0);

        $started = 0;
        foreach ($x->HISTORY_RECORDS->HISTORY ?? [] as $h) {
            $started = (int) $h->STIME ?: $started;
        }
        $host = '';
        foreach ($x->HISTORY_RECORDS->HISTORY ?? [] as $h) {
            $host = (string) $h->HOSTNAME;
        }

        $addresses = [];
        foreach ($x->TEMPLATE->NIC ?? [] as $nic) {
            foreach (['IP', 'IP6', 'IP6_GLOBAL'] as $k) {
                if (trim((string) ($nic->{$k} ?? '')) !== '') {
                    $addresses[] = trim((string) $nic->{$k});
                }
            }
        }
        foreach (array_filter(array_map('trim', explode(',', (string) ($x->MONITORING->GUEST_IP_ADDRESSES ?? '')))) as $ip) {
            $addresses[] = trim($ip, '"');
        }

        return [
            'available' => true,
            'vmid' => $vm['id'],
            'node' => $host,
            'type' => 'qemu',
            'name' => (string) $x->NAME,
            'status' => $status,
            'lock' => $lock,
            'uptime' => $running && $started > 0 ? max(0, time() - $started) : 0,
            'cpus' => $vcpu,
            'cpu' => $running ? round(min(100.0, (float) ($x->MONITORING->CPU ?? 0) / $vcpu), 1) : 0.0,
            'memory' => ['used' => $running ? (int) round(((float) ($x->MONITORING->MEMORY ?? 0)) / 1024) : 0, 'max' => $memMax],
            // OpenNebula knows the disk's size, not how full the guest has made it.
            'disk' => ['used' => null, 'max' => round($diskMb / 1024, 1), 'fs_size' => null],
            'net' => ['in' => (int) ($x->MONITORING->NETRX ?? 0), 'out' => (int) ($x->MONITORING->NETTX ?? 0)],
            'agent' => false,
            'addresses' => array_values(array_unique($addresses)),
            'image' => $this->attr($x, 'PNLCS_OS') ?: (string) ($x->TEMPLATE->DISK[0]->IMAGE ?? ''),
            'last_error' => '',
        ];
    }

    /**
     * Usage over time for the graphs: CPU, memory, network and disk I/O.
     *
     * oned keeps monitoring for a limited time (VM_MONITORING_EXPIRATION_TIME in
     * oned.conf), so a long timeframe shows only as much as it still holds.
     */
    public function graphs(Service $service, string $timeframe = 'hour'): array
    {
        $timeframe = in_array($timeframe, ['hour', 'day', 'week', 'month', 'year'], true) ? $timeframe : 'hour';
        $vm = $this->vm($service);
        if ($vm instanceof OpenNebulaResult) {
            return ['available' => false, 'error' => $vm->error];
        }

        $r = $vm['api']->call('one.vm.monitoring', [$vm['id']]);
        $xml = $r->ok ? OpenNebulaClient::xml($r->data) : null;
        if (! $xml) {
            return ['available' => false, 'error' => $r->ok ? __('opennebula.error.not_xmlrpc') : $r->error];
        }

        $since = time() - ['hour' => 3600, 'day' => 86400, 'week' => 604800, 'month' => 2592000, 'year' => 31536000][$timeframe];
        $vcpu = max(1, (int) ($vm['xml']->TEMPLATE->VCPU ?? 1));
        $maxmem = (int) ($vm['xml']->TEMPLATE->MEMORY ?? 0);

        $rows = [];
        foreach ($xml->MONITORING as $m) {
            $t = (int) $m->TIMESTAMP;
            if ($t >= $since) {
                $rows[$t] = $m;
            }
        }
        ksort($rows);

        $points = [];
        $prev = null;
        $prevT = null;
        foreach ($rows as $t => $m) {
            $rate = function (string $key) use ($m, $prev, $t, $prevT): ?int {
                if (! $prev || $t <= $prevT || ! isset($m->{$key}, $prev->{$key})) {
                    return null;
                }
                $delta = (float) $m->{$key} - (float) $prev->{$key};

                return $delta < 0 ? null : (int) round($delta / ($t - $prevT)); // counters restart at a reboot
            };
            $points[] = [
                't' => $t,
                'cpu' => isset($m->CPU) ? round(min(100.0, (float) $m->CPU / $vcpu), 2) : null,
                'mem' => isset($m->MEMORY) ? (int) round((float) $m->MEMORY / 1024) : null,
                'maxmem' => $maxmem ?: null,
                'netin' => $rate('NETRX'),
                'netout' => $rate('NETTX'),
                'diskread' => $rate('DISKRDBYTES'),
                'diskwrite' => $rate('DISKWRBYTES'),
            ];
            $prev = $m;
            $prevT = $t;
        }

        // A chart needs a few hundred points at most.
        if (count($points) > 240) {
            $step = (int) ceil(count($points) / 240);
            $points = array_values(array_filter($points, fn ($p, $i) => $i % $step === 0, ARRAY_FILTER_USE_BOTH));
        }

        return ['available' => true, 'timeframe' => $timeframe, 'points' => $points];
    }

    // =========================================================================
    // Power and password
    // =========================================================================

    public function power(Service $service, string $action): array
    {
        if (! in_array($action, self::CLIENT_POWER_ACTIONS, true)) {
            return $this->buildResult(false, __('opennebula.error.unknown_action', ['action' => $action]));
        }
        $vm = $this->vm($service);
        if ($vm instanceof OpenNebulaResult) {
            return $this->buildResult(false, $vm->error);
        }
        if ($busy = $this->busy($vm)) {
            return $busy;
        }

        $off = in_array($vm['state'], [self::ST_POWEROFF, self::ST_UNDEPLOYED, self::ST_STOPPED, self::ST_SUSPENDED], true);

        if ($action === 'start') {
            if ($vm['state'] === self::ST_ACTIVE) {
                return $this->buildResult(true, __('opennebula.power_done.start'));
            }
            $one = $vm['state'] === self::ST_HOLD ? 'release' : ($off ? 'resume' : null);
            if ($one === null) {
                return $this->buildResult(false, __('opennebula.error.busy'));
            }

            return $this->action($vm, $one, __('opennebula.power_done.start'));
        }

        // Nothing to switch off, nothing to restart.
        if ($off) {
            return $this->buildResult(false, __('opennebula.error.not_running'));
        }

        // "terminate" would delete the machine: it is never sent from here.
        $one = ['shutdown' => 'poweroff', 'stop' => 'poweroff-hard', 'reboot' => 'reboot', 'reset' => 'reboot-hard'][$action];

        return $this->action($vm, $one, __('opennebula.power_done.'.$action));
    }

    public function start(Service $service): array
    {
        return $this->power($service, 'start');
    }

    public function stop(Service $service): array
    {
        return $this->power($service, 'stop');
    }

    public function reboot(Service $service): array
    {
        return $this->power($service, 'reboot');
    }

    public function shutdown(Service $service): array
    {
        return $this->power($service, 'shutdown');
    }

    /** Sets the context password; the guest applies it at its next start (one-context). */
    public function changePassword(Service $service, string $newPassword): array
    {
        $vm = $this->vm($service);
        if ($vm instanceof OpenNebulaResult) {
            return $this->buildResult(false, $vm->error);
        }
        if (strtoupper($this->attr($vm['xml'], 'PNLCS_PASSWORD')) !== 'YES') {
            return $this->buildResult(false, __('opennebula.error.password_off'));
        }

        $r = $vm['api']->call('one.vm.updateconf', [$vm['id'], 'CONTEXT=[ PASSWORD="'.addcslashes($newPassword, '"\\')."\" ]\n", 1]);

        return $this->buildResult($r->ok, $r->ok ? __('opennebula.password_set') : $r->error);
    }

    // =========================================================================
    // Reinstall and backups: not offered
    // =========================================================================

    public function reinstallChoices(Service $service): array
    {
        return [];
    }

    public function imageName(string $image): string
    {
        return $image;
    }

    public function reinstall(Service $service, string $image, string $password): array
    {
        return $this->buildResult(false, __('opennebula.error.reinstall_unsupported'));
    }

    public static function normaliseSshKeys(?string $text): ?string
    {
        return trim((string) $text);
    }

    public function setSshKeys(Service $service, string $keys): void
    {
        $this->setModuleData($service, ['ssh_keys' => $keys]);
    }

    public function storedSshKeys(Service $service): string
    {
        return (string) ($this->getModuleData($service)['ssh_keys'] ?? '');
    }

    public function backupLimit(Service $service): int
    {
        return 0;
    }

    public function backups(Service $service): array|object
    {
        return OpenNebulaResult::failed(__('opennebula.error.backups_off'));
    }

    public function backupCreate(Service $service): array
    {
        return $this->buildResult(false, __('opennebula.error.backups_off'));
    }

    public function backupRestore(Service $service, string $volid): array
    {
        return $this->buildResult(false, __('opennebula.error.backups_off'));
    }

    public function backupDelete(Service $service, string $volid): array
    {
        return $this->buildResult(false, __('opennebula.error.backups_off'));
    }

    // =========================================================================
    // Snapshots
    // =========================================================================

    public function snapshotLimit(Service $service): int
    {
        $vm = $this->vm($service);

        return is_array($vm) ? max(0, (int) $this->attr($vm['xml'], 'PNLCS_SNAPSHOTS')) : 0;
    }

    /** @return array<int, array{id: int, name: string, time: int}> */
    private function snapshotsOf(SimpleXMLElement $x): array
    {
        $out = [];
        foreach ($x->TEMPLATE->SNAPSHOT ?? [] as $s) {
            $out[] = ['id' => (int) $s->SNAPSHOT_ID, 'name' => (string) $s->NAME, 'time' => (int) $s->TIME];
        }

        return $out;
    }

    public function snapshots(Service $service): array|object
    {
        $vm = $this->vm($service);
        if ($vm instanceof OpenNebulaResult) {
            return $vm;
        }

        return collect($this->snapshotsOf($vm['xml']))
            ->map(fn ($s) => ['name' => $s['name'], 'description' => '', 'time' => $s['time']])
            ->sortByDesc('time')->values()->all();
    }

    public function snapshotCreate(Service $service, string $name, string $description = ''): array
    {
        $vm = $this->vm($service);
        if ($vm instanceof OpenNebulaResult) {
            return $this->buildResult(false, $vm->error);
        }
        $limit = max(0, (int) $this->attr($vm['xml'], 'PNLCS_SNAPSHOTS'));
        if ($limit < 1) {
            return $this->buildResult(false, __('opennebula.error.snapshots_off'));
        }
        if (! preg_match(self::SNAPSHOT_NAME, $name)) {
            return $this->buildResult(false, __('opennebula.error.snapshot_name'));
        }
        if ($busy = $this->busy($vm)) {
            return $busy;
        }

        $existing = $this->snapshotsOf($vm['xml']);
        if (count($existing) >= $limit) {
            return $this->buildResult(false, __('opennebula.error.snapshot_limit', ['limit' => $limit]));
        }
        if (collect($existing)->contains('name', $name)) {
            return $this->buildResult(false, __('opennebula.error.snapshot_exists', ['name' => $name]));
        }

        $r = $vm['api']->call('one.vm.snapshotcreate', [$vm['id'], $name]);

        return $this->buildResult($r->ok, $r->ok ? __('opennebula.snapshot_created', ['name' => $name]) : $r->error);
    }

    public function snapshotRollback(Service $service, string $name): array
    {
        return $this->snapshotStep($service, $name, 'one.vm.snapshotrevert', 'snapshot_rolled_back');
    }

    public function snapshotDelete(Service $service, string $name): array
    {
        return $this->snapshotStep($service, $name, 'one.vm.snapshotdelete', 'snapshot_deleted');
    }

    private function snapshotStep(Service $service, string $name, string $method, string $done): array
    {
        $vm = $this->vm($service);
        if ($vm instanceof OpenNebulaResult) {
            return $this->buildResult(false, $vm->error);
        }
        if ($busy = $this->busy($vm)) {
            return $busy;
        }
        $snap = collect($this->snapshotsOf($vm['xml']))->firstWhere('name', $name);
        if (! $snap) {
            return $this->buildResult(false, __('opennebula.error.snapshot_missing', ['name' => $name]));
        }

        $r = $vm['api']->call($method, [$vm['id'], (int) $snap['id']]);

        return $this->buildResult($r->ok, $r->ok ? __('opennebula.'.$done, ['name' => $name]) : $r->error);
    }
}
