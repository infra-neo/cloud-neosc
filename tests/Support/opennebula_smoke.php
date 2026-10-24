<?php

/*
 * Smoke test for the OpenNebula module, with no MySQL and no real oned.
 *
 *   php tests/Support/opennebula_smoke.php
 *
 * Boots the application on an in-memory SQLite database (only the services
 * table the module reads) and puts a fake oned behind Http::fake(): it speaks
 * XML-RPC, keeps VMs in memory and records every call, so the checks can say
 * what the module sent as well as what it answered. Exits non-zero on a failure.
 */

putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('k', 32)));
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('CACHE_STORE=array');
putenv('SESSION_DRIVER=array');
putenv('QUEUE_CONNECTION=sync');
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = ':memory:';
$_ENV['CACHE_STORE'] = $_SERVER['CACHE_STORE'] = 'array';
$_ENV['SESSION_DRIVER'] = $_SERVER['SESSION_DRIVER'] = 'array';
$_ENV['APP_KEY'] = $_SERVER['APP_KEY'] = 'base64:'.base64_encode(str_repeat('k', 32));

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Contracts\VpsModuleInterface;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Servers\OpenNebula\OpenNebulaModule;
use Modules\Servers\Proxmox\ProxmoxModule;

Schema::create('services', function ($t) {
    $t->id();
    $t->unsignedBigInteger('client_id')->nullable();
    $t->unsignedBigInteger('server_id')->nullable();
    $t->string('status')->nullable();
    $t->string('domain')->nullable();
    $t->string('username')->nullable();
    $t->text('password')->nullable();
    $t->text('notes')->nullable();
    $t->text('module_data')->nullable();
    $t->timestamps();
});

// The plan reads the options a customer chose at checkout; none here.
Schema::create('service_config_options', function ($t) {
    $t->id();
    $t->unsignedBigInteger('service_id');
    $t->unsignedBigInteger('config_option_id')->nullable();
    $t->unsignedBigInteger('config_option_sub_id')->nullable();
    $t->integer('qty')->default(0);
    $t->timestamps();
});

/** A fake oned: XML-RPC in, XML-RPC out, VMs kept in memory. */
final class FakeOned
{
    public array $vms = [];

    public array $calls = [];

    public array $templates = [10 => 'Win11-VDI', 11 => 'Debian-12'];

    public bool $badLogin = false;

    private int $next = 100;

    private int $snap = 0;

    public function install(): void
    {
        Http::fake(['*' => fn (Request $r) => $this->handle($r)]);
    }

    public function vm(int $id, array $o = []): int
    {
        $this->vms[$id] = $o + [
            'name' => "vm-$id", 'state' => 3, 'lcm' => 3, 'vcpu' => 2, 'memory' => 4096, 'disk' => 61440,
            'ip' => "10.0.0.$id", 'user' => [], 'snaps' => [], 'cpu' => 50, 'mem_kb' => 1048576,
        ];

        return $id;
    }

    /** Calls to one method, by name. */
    public function sent(string $method): array
    {
        return array_values(array_filter($this->calls, fn ($c) => $c[0] === $method));
    }

    /** one.vm.action calls that sent this action. */
    public function actions(string $action): array
    {
        return array_values(array_filter($this->sent('one.vm.action'), fn ($c) => $c[1][0] === $action));
    }

    private function handle(Request $request)
    {
        $xml = simplexml_load_string($request->body());
        $method = (string) $xml->methodName;
        $params = [];
        foreach ($xml->params->param as $p) {
            $v = $p->value->children()[0];
            $params[] = match ($v->getName()) {
                'int', 'i4' => (int) $v, 'boolean' => (string) $v === '1', default => (string) $v,
            };
        }
        $session = array_shift($params);
        $this->calls[] = [$method, $params, $session];

        if ($this->badLogin || $session !== 'oneadmin:secret') {
            return $this->rpc(false, 'User couldn\'t be authenticated, aborting call.', 0x0100);
        }

        return match ($method) {
            'one.system.version' => $this->rpc(true, '6.10.0'),
            'one.templatepool.info' => $this->rpc(true, '<VMTEMPLATE_POOL>'.collect($this->templates)->map(fn ($n, $id) => "<VMTEMPLATE><ID>$id</ID><NAME>$n</NAME>".$this->templateBody().'</VMTEMPLATE>')->implode('').'</VMTEMPLATE_POOL>'),
            'one.template.info' => isset($this->templates[$params[0]])
                ? $this->rpc(true, "<VMTEMPLATE><ID>{$params[0]}</ID><NAME>{$this->templates[$params[0]]}</NAME>".$this->templateBody().'</VMTEMPLATE>')
                : $this->rpc(false, "[TemplateInfo] Error getting virtual machine template [{$params[0]}].", 0x0400),
            'one.clusterpool.info' => $this->rpc(true, '<CLUSTER_POOL><CLUSTER><ID>100</ID><NAME>kvm-a</NAME></CLUSTER></CLUSTER_POOL>'),
            'one.datastorepool.info' => $this->rpc(true, '<DATASTORE_POOL><DATASTORE><ID>0</ID><NAME>system</NAME><TYPE>1</TYPE></DATASTORE><DATASTORE><ID>1</ID><NAME>images</NAME><TYPE>0</TYPE></DATASTORE></DATASTORE_POOL>'),
            'one.vnpool.info' => $this->rpc(true, '<VNET_POOL><VNET><ID>3</ID><NAME>lan</NAME></VNET><VNET><ID>4</ID><NAME>dmz</NAME></VNET></VNET_POOL>'),
            'one.secgrouppool.info' => $this->rpc(true, '<SECURITY_GROUP_POOL><SECURITY_GROUP><ID>0</ID><NAME>default</NAME></SECURITY_GROUP></SECURITY_GROUP_POOL>'),
            'one.vmpool.info' => $this->rpc(true, '<VM_POOL>'.collect(array_keys($this->vms))->map(fn ($id) => $this->xml($id))->implode('').'</VM_POOL>'),
            'one.vm.info' => isset($this->vms[$params[0]]) ? $this->rpc(true, $this->xml($params[0])) : $this->rpc(false, "[VirtualMachineInfo] Error getting virtual machine [{$params[0]}].", 0x0400),
            'one.template.instantiate' => $this->instantiate($params),
            'one.vm.update' => $this->update($params),
            'one.vm.updateconf' => isset($this->vms[$params[0]]) ? $this->rpc(true, $params[0]) : $this->rpc(false, 'no such vm', 0x0400),
            'one.vm.action' => $this->action($params),
            'one.vm.snapshotcreate' => $this->snapshotCreate($params),
            'one.vm.snapshotrevert', 'one.vm.snapshotdelete' => $this->rpc(true, $params[0]),
            'one.vm.monitoring' => $this->rpc(true, $this->monitoring()),
            default => $this->rpc(false, "unknown method $method", 0x1000),
        };
    }

    /** What every fake template contains: sizes, two disks, one card, a context. */
    private function templateBody(): string
    {
        return '<TEMPLATE><CPU>2</CPU><VCPU>2</VCPU><MEMORY>4096</MEMORY>'
            .'<DISK><IMAGE_ID>7</IMAGE_ID><DRIVER>qcow2</DRIVER></DISK><DISK><IMAGE_ID>8</IMAGE_ID></DISK>'
            .'<NIC><NETWORK_ID>3</NETWORK_ID></NIC>'
            .'<CONTEXT><NETWORK>YES</NETWORK><SSH_PUBLIC_KEY>$USER[SSH_PUBLIC_KEY]</SSH_PUBLIC_KEY></CONTEXT></TEMPLATE>';
    }

    private function instantiate(array $p)
    {
        $id = $this->next++;
        $user = [];
        preg_match_all('/^([A-Z_]+)="([^"]*)"$/m', $p[3], $m, PREG_SET_ORDER);
        foreach ($m as $kv) {
            $user[$kv[1]] = $kv[2];
        }
        $this->vm($id, ['name' => $p[1], 'user' => $user + ['PNLCS_SNAPSHOTS' => '2']]);

        return $this->rpc(true, $id);
    }

    private function update(array $p)
    {
        if (! isset($this->vms[$p[0]])) {
            return $this->rpc(false, 'no such vm', 0x0400);
        }
        preg_match_all('/^([A-Z_]+)="([^"]*)"$/m', $p[1], $m, PREG_SET_ORDER);
        foreach ($m as $kv) {
            $this->vms[$p[0]]['user'][$kv[1]] = $kv[2];
        }

        return $this->rpc(true, $p[0]);
    }

    private function action(array $p)
    {
        [$action, $id] = $p;
        if (! isset($this->vms[$id])) {
            return $this->rpc(false, 'no such vm', 0x0400);
        }
        $vm = &$this->vms[$id];
        switch ($action) {
            case 'poweroff': case 'poweroff-hard':
                $vm['state'] = 8; $vm['lcm'] = 0; break;
            case 'resume':
                if (! in_array($vm['state'], [4, 5, 8, 9])) {
                    return $this->rpc(false, 'wrong state for resume', 0x0800);
                }
                $vm['state'] = 3; $vm['lcm'] = 3; break;
            case 'terminate-hard': case 'terminate':
                $vm['state'] = 6; $vm['lcm'] = 0; break;
        }

        return $this->rpc(true, $id);
    }

    private function snapshotCreate(array $p)
    {
        $id = $this->snap++;
        $this->vms[$p[0]]['snaps'][$id] = ['name' => $p[1], 'time' => 1_700_000_000 + $id];

        return $this->rpc(true, $id);
    }

    private function monitoring(): string
    {
        $rows = '';
        $t = time() - 600;
        foreach ([0, 1, 2, 3, 4] as $i) {
            $rx = $i === 3 ? 500 : 1000 * ($i + 1) * 60; // counter restarts at row 3 (a reboot)
            $rows .= '<MONITORING><TIMESTAMP>'.($t + $i * 60).'</TIMESTAMP><CPU>80</CPU><MEMORY>2097152</MEMORY>'
                ."<NETRX>$rx</NETRX><NETTX>".(500 * ($i + 1) * 60).'</NETTX><DISKRDBYTES>0</DISKRDBYTES><DISKWRBYTES>'.(3000 * ($i + 1) * 60).'</DISKWRBYTES></MONITORING>';
        }

        return "<MONITORING_DATA>$rows</MONITORING_DATA>";
    }

    private function xml(int $id): string
    {
        $v = $this->vms[$id];
        $user = collect($v['user'])->map(fn ($val, $k) => "<$k>$val</$k>")->implode('');
        $snaps = collect($v['snaps'])->map(fn ($s, $sid) => "<SNAPSHOT><SNAPSHOT_ID>$sid</SNAPSHOT_ID><NAME>{$s['name']}</NAME><TIME>{$s['time']}</TIME></SNAPSHOT>")->implode('');

        return "<VM><ID>$id</ID><NAME>{$v['name']}</NAME><STATE>{$v['state']}</STATE><LCM_STATE>{$v['lcm']}</LCM_STATE>"
            .'<HISTORY_RECORDS><HISTORY><HOSTNAME>kvm1</HOSTNAME><STIME>'.(time() - 7200).'</STIME></HISTORY></HISTORY_RECORDS>'
            ."<MONITORING><CPU>{$v['cpu']}</CPU><MEMORY>{$v['mem_kb']}</MEMORY><NETRX>7000</NETRX><NETTX>3000</NETTX>"
            .'<GUEST_IP_ADDRESSES>"10.9.9.9"</GUEST_IP_ADDRESSES></MONITORING>'
            ."<TEMPLATE><VCPU>{$v['vcpu']}</VCPU><MEMORY>{$v['memory']}</MEMORY>"
            ."<DISK><DISK_ID>0</DISK_ID><SIZE>{$v['disk']}</SIZE><IMAGE>win11-golden</IMAGE></DISK>"
            ."<NIC><IP>{$v['ip']}</IP></NIC>$snaps</TEMPLATE><USER_TEMPLATE>$user</USER_TEMPLATE></VM>";
    }

    private function rpc(bool $ok, mixed $data, int $code = 0)
    {
        $d = is_int($data) ? "<int>$data</int>" : '<string>'.htmlspecialchars((string) $data, ENT_XML1).'</string>';

        return Http::response('<?xml version="1.0"?><methodResponse><params><param><value><array><data>'
            .'<value><boolean>'.(int) $ok."</boolean></value><value>$d</value><value><int>$code</int></value>"
            .'</data></array></value></param></params></methodResponse>', 200, ['Content-Type' => 'text/xml']);
    }
}

// ---------------------------------------------------------------------------

$failures = 0;
function check(bool $ok, string $what): void
{
    global $failures;
    echo ($ok ? '  ok    ' : '  FAIL  ').$what.PHP_EOL;
    $failures += $ok ? 0 : 1;
}
function section(string $s): void
{
    echo PHP_EOL.$s.PHP_EOL;
}

$server = (new Server)->forceFill([
    'id' => 1, 'name' => 'one-lab', 'type' => 'opennebula', 'hostname' => '10.1.1.1', 'ip_address' => '10.1.1.1',
    'username' => 'oneadmin', 'password' => 'secret', 'active' => true, 'settings' => [],
]);
function newService(Server $server, int $id, array $extra = []): Service
{
    $s = Service::forceCreate(['id' => $id, 'client_id' => 5, 'server_id' => 1, 'status' => 'active', 'username' => 'vdiuser'] + $extra);
    $s->setRelation('server', $server);

    return $s;
}

$one = new FakeOned;
$one->install();
$mod = new OpenNebulaModule;

section('Contract');
check($mod instanceof VpsModuleInterface, 'OpenNebulaModule implements VpsModuleInterface');
check((new ReflectionClass(ProxmoxModule::class))->implementsInterface(VpsModuleInterface::class), 'ProxmoxModule implements it too');
check(app(\App\Services\Module\ModuleRegistry::class)->getServerModule('opennebula') instanceof OpenNebulaModule, 'registry resolves "opennebula" to the module');

section('Connection');
check($mod->testConnection($server) === true, 'testConnection passes with the right user');
$one->badLogin = true;
check($mod->testConnection($server) === false, 'testConnection fails on a refused login');
$one->badLogin = false;
check($mod->listPackages($server) === [['id' => '10', 'name' => 'Win11-VDI'], ['id' => '11', 'name' => 'Debian-12']], 'listPackages lists the OpenNebula templates');

section('Create from a template');
$svc = newService($server, 12);
$svc->setRelation('product', (object) ['config_options' => json_encode(['package_name' => '10'])]);
$r = $mod->create($svc);
$inst = $one->sent('one.template.instantiate')[0] ?? null;
check($r['success'] === true, 'create succeeds: '.$r['message']);
check($inst && $inst[1][0] === 10 && $inst[1][1] === 'NEOSC-VDI-CLI5-SRV12', 'instantiates template 10 under the NEOSC-VDI-CLI5-SRV12 name');
check($inst && str_contains($inst[1][3], 'PNLCS_SERVICE="12"') && str_contains($inst[1][3], 'USERNAME="vdiuser"'), 'carries the ownership mark and one CONTEXT block');
check(substr_count($inst[1][3] ?? '', 'CONTEXT=') === 1, 'only one CONTEXT block is sent');
$vmid = $svc->fresh()->module_data['opennebula_vmid'] ?? null;
check($vmid === 100, 'the VM id is kept in module_data');
$again = $mod->create($svc->fresh()->setRelation('server', $server));
check($again['success'] && count($one->sent('one.template.instantiate')) === 1, 'a second create does not build a second VM');

section('Plan: sizes, network and placement');
$plan = fn (array $cfg) => \Modules\Servers\OpenNebula\OpenNebulaPlan::forService(new Service, $cfg);
check($plan(['package_name' => '10'])->templateId() === 10, 'the plan reads the template from package_name');
check($plan(['one_template' => '11', 'package_name' => '10'])->templateId() === 11, 'one_template wins over package_name');
check($plan(['package_name' => '866b0a94-43cc'])->templateId() === 0, 'a UUID left over from another module is not a template id');
$tpl = simplexml_load_string('<VMTEMPLATE><TEMPLATE><CPU>2</CPU><VCPU>2</VCPU><MEMORY>4096</MEMORY><DISK><IMAGE_ID>7</IMAGE_ID></DISK><DISK><IMAGE_ID>8</IMAGE_ID></DISK><NIC><NETWORK_ID>3</NETWORK_ID></NIC><CONTEXT><NETWORK>YES</NETWORK></CONTEXT></TEMPLATE></VMTEMPLATE>');
$svc2 = newService($server, 31);
$xtra = $mod->instantiationFor($svc2, $plan(['one_template' => '10']), $tpl);
check(! str_contains($xtra, 'VCPU=') && ! str_contains($xtra, 'MEMORY=') && ! str_contains($xtra, 'DISK=') && ! str_contains($xtra, 'NIC='), 'an empty plan leaves sizes, disks and cards to the template');
check(str_contains($xtra, 'CONTEXT=[ NETWORK="YES"') && str_contains($xtra, 'USERNAME="vdiuser"'), 'the template\'s own context is kept and the user added');
$xtra = $mod->instantiationFor($svc2, $plan(['one_template' => '10', 'one_cores' => 4, 'one_memory' => 8192, 'one_disk' => 80, 'one_network' => '4', 'one_rate' => 10, 'one_secgroup' => '0', 'one_cluster' => '100', 'one_datastore' => '0', 'one_snapshots' => 3, 'one_password' => 1, 'one_nameserver' => '1.1.1.1']), $tpl);
check(str_contains($xtra, 'VCPU="4"') && str_contains($xtra, 'CPU="4"') && str_contains($xtra, 'MEMORY="8192"'), 'vCPU, CPU share (following the vCPUs) and memory are sent');
check(str_contains($xtra, 'DISK=[ IMAGE_ID="7",') && str_contains($xtra, 'SIZE="81920"') && str_contains($xtra, 'DISK=[ IMAGE_ID="8" ]'), 'the boot disk is resized (GB to MB) and the other disk is sent back unchanged');
check(substr_count($xtra, 'NIC=') === 1 && str_contains($xtra, 'NETWORK_ID="4"') && str_contains($xtra, 'INBOUND_AVG_BW="10240"') && str_contains($xtra, 'SECURITY_GROUPS="0"'), 'the chosen network replaces the card, with the speed limit (MB/s to KB/s) and security group');
check(str_contains($xtra, 'SCHED_REQUIREMENTS="CLUSTER_ID=\\"100\\""') && str_contains($xtra, 'SCHED_DS_REQUIREMENTS="ID=\\"0\\""'), 'cluster and datastore become scheduler requirements');
check(str_contains($xtra, 'PNLCS_SNAPSHOTS="3"') && str_contains($xtra, 'PNLCS_PASSWORD="YES"') && str_contains($xtra, 'DNS="1.1.1.1"'), 'panel switches and DNS are sent');
check(substr_count($xtra, 'CONTEXT=') === 1, 'still one CONTEXT block');
$xtra = $mod->instantiationFor($svc2, $plan(['one_template' => '10', 'one_rate' => 5]), $tpl);
check(str_contains($xtra, 'NIC=[ NETWORK_ID="3"') && str_contains($xtra, 'INBOUND_AVG_BW="5120"'), 'a speed limit alone is put on the template\'s own card');
$one->calls = [];
$svc3 = newService($server, 32);
$svc3->setRelation('product', (object) ['config_options' => json_encode(['one_template' => '10', 'one_cores' => 4, 'one_memory' => 8192])]);
$r = $mod->create($svc3);
$inst = $one->sent('one.template.instantiate')[0] ?? null;
check($r['success'] && $inst && str_contains($inst[1][3], 'VCPU="4"') && str_contains($inst[1][3], 'MEMORY="8192"'), 'create sends the plan with the instantiation');
check(count($one->sent('one.template.info')) === 1, 'the template is read once, for its disks, cards and context');
$svc4 = newService($server, 33);
$svc4->setRelation('product', (object) ['config_options' => json_encode(['package_name' => '866b0a94-43cc'])]);
$r = $mod->create($svc4);
check($r['success'] === false && str_contains($r['message'], 'no OpenNebula template'), 'a product with a UUID instead of a template id says so');
$svc5 = newService($server, 34);
$svc5->setRelation('product', (object) ['config_options' => json_encode(['one_template' => '999'])]);
$r = $mod->create($svc5);
check($r['success'] === false && str_contains($r['message'], 'Error getting virtual machine template [999]'), 'a template that does not exist is reported by OpenNebula\'s own message');

section('Catalog for the product form');
$cat = $mod->catalog($server);
check($cat['ok'] && count($cat['templates']) === 2 && $cat['templates'][0] === ['id' => '10', 'name' => 'Win11-VDI', 'vcpu' => 2, 'memory' => 4096, 'disk' => 0], 'templates come with their vCPU, memory and disk');
check($cat['clusters'] === [['id' => '100', 'name' => 'kvm-a']] && $cat['networks'][1] === ['id' => '4', 'name' => 'dmz'] && $cat['secgroups'] === [['id' => '0', 'name' => 'default']], 'clusters, networks and security groups are listed');
check(collect($cat['datastores'])->where('type', 1)->count() === 1, 'datastores carry their type so only system ones are offered');
$one->badLogin = true;
$bad = $mod->catalog($server);
check($bad['ok'] === false && $bad['error'] !== '', 'a refused login is reported, not hidden');
$one->badLogin = false;

section('Panel: features and status');
$svc = $svc->fresh()->setRelation('server', $server);
$f = $mod->vpsFeatures($svc);
check($f === ['status', 'power', 'graphs', 'snapshots'], 'features: status, power, graphs, snapshots (no reinstall, backups or password): '.json_encode($f));
$one->vms[100]['user']['PNLCS_PASSWORD'] = 'YES';
check(in_array('password', $mod->vpsFeatures($svc), true), 'PNLCS_PASSWORD=YES adds the password feature');
$s = $mod->vmStatus($svc);
check($s['available'] && $s['status'] === 'running' && $s['lock'] === '', 'status: running');
check($s['cpus'] === 2 && $s['cpu'] === 25.0, 'status: 2 vCPU, 50% of one-core units = 25.0% of the VM');
check($s['memory'] === ['used' => 1024, 'max' => 4096], 'status: memory in MB');
check($s['disk']['max'] === 60.0 && $s['disk']['used'] === null, 'status: disk 60 GB, usage unknown');
check($s['addresses'] === ['10.0.0.100', '10.9.9.9'], 'status: addresses from the NIC and the guest');
check($s['image'] === 'win11-golden' && $s['vmid'] === 100 && $s['type'] === 'qemu', 'status: image, vmid and type');
check($s['uptime'] > 7000, 'status: uptime from the history record');

section('Power');
check($mod->power($svc, 'stop')['success'] && $one->actions('poweroff-hard') !== [], 'stop sends poweroff-hard');
check($mod->vmStatus($svc)['status'] === 'stopped', 'status then reads stopped');
check($mod->power($svc, 'reboot')['success'] === false, 'reboot of a stopped server is refused');
check($mod->power($svc, 'start')['success'] && $one->actions('resume') !== [], 'start sends resume');
check($mod->power($svc, 'shutdown')['success'] && $one->actions('poweroff') !== [], 'shutdown sends the gentle poweroff');
$mod->power($svc, 'start');
check($mod->power($svc, 'reset')['success'] && $one->actions('reboot-hard') !== [], 'reset sends reboot-hard');
check($mod->power($svc, 'terminate')['success'] === false, 'a "terminate" power action is not accepted');
check($one->actions('terminate') === [] && $one->actions('terminate-hard') === [], 'no power action ever sent terminate');
$one->vms[100]['lcm'] = 12;
check($mod->power($svc, 'stop')['success'] === false && $mod->vmStatus($svc)['lock'] === 'shutdown', 'a VM mid-transition is "busy" and shows its lock');
$one->vms[100]['lcm'] = 3;

section('Graphs');
$g = $mod->graphs($svc, 'hour');
check($g['available'] && $g['timeframe'] === 'hour' && count($g['points']) === 5, 'graphs: five points');
check($g['points'][1]['netin'] === 1000 && $g['points'][3]['netin'] === null, 'graphs: byte counters become rates, and a counter reset becomes null');
check($g['points'][0]['cpu'] === 40.0 && $g['points'][0]['mem'] === 2048 && $g['points'][0]['maxmem'] === 4096, 'graphs: cpu per VM, memory in MB');
check($mod->graphs($svc, 'nonsense')['timeframe'] === 'hour', 'graphs: unknown timeframe falls back to hour');

section('Snapshots');
check($mod->snapshotLimit($svc) === 2, 'limit comes from PNLCS_SNAPSHOTS');
check($mod->snapshotCreate($svc, 'one-snap')['success'] && $mod->snapshotCreate($svc, 'two-snap')['success'], 'two snapshots are created');
check($mod->snapshotCreate($svc, 'three')['success'] === false, 'the third is over the limit');
check($mod->snapshotCreate($svc, 'one-snap')['success'] === false, 'a repeated name is refused');
check($mod->snapshotCreate($svc, '1bad name')['success'] === false, 'a bad name is refused');
$list = $mod->snapshots($svc);
check(is_array($list) && count($list) === 2 && $list[0]['name'] === 'two-snap', 'snapshots lists newest first');
check($mod->snapshotRollback($svc, 'one-snap')['success'] && ($one->sent('one.vm.snapshotrevert')[0][1][1] ?? null) === 0, 'rollback sends the snapshot id for that name');
check($mod->snapshotDelete($svc, 'one-snap')['success'] && count($one->sent('one.vm.snapshotdelete')) === 1, 'delete sends the id');
check($mod->snapshotDelete($svc, 'nope')['success'] === false, 'deleting a missing snapshot is refused');

section('Password');
$r = $mod->changePassword($svc, 'N3w-pass"word');
$uc = $one->sent('one.vm.updateconf')[0] ?? null;
check($r['success'] && $uc && str_contains($uc[1][1], 'PASSWORD="N3w-pass\\"word"') && $uc[1][2] === 1, 'password goes to CONTEXT, merged, with quotes escaped');
unset($one->vms[100]['user']['PNLCS_PASSWORD']);
check($mod->changePassword($svc, 'x')['success'] === false, 'refused when the template does not enable it');

section('Ownership: never touch what PNLCS did not mark');
$foreign = $one->vm(300, ['name' => 'somebody-elses', 'user' => []]);
$other = $one->vm(301, ['name' => 'other-customer', 'user' => ['PNLCS_SERVICE' => '99']]);
foreach ([300 => 'unmarked', 301 => "another service's"] as $id => $why) {
    $s2 = newService($server, 40 + $id % 100, ['module_data' => ['opennebula_vmid' => $id]]);
    $before = count($one->calls);
    check($mod->vmStatus($s2)['available'] === false, "status of a $why VM is unavailable");
    check($mod->power($s2, 'stop')['success'] === false, "power on a $why VM is refused");
    check($mod->terminate($s2)['success'] === false, "terminate of a $why VM is refused");
    check($mod->suspend($s2)['success'] === false, "suspend of a $why VM is refused");
    check($one->actions('terminate-hard') === [] && $one->vms[$id]['state'] === 3, "the $why VM was left running");
}

section('Link by name and by id');
$byName = $one->vm(400, ['name' => 'NEOSC-VDI-CLI5-SRV77']);
$s3 = newService($server, 77, ['module_data' => []]);
Cache::flush();
check($mod->vpsFeatures($s3) !== [] && $mod->vpsFeatures($s3)[0] === 'status', 'a VM named for the service is picked up when its page opens');
check(($one->vms[400]['user']['PNLCS_SERVICE'] ?? null) === '77', 'and it is marked for that service');
$one->vm(401, ['name' => 'NEOSC-VDI-CLI5-SRV78', 'user' => ['PNLCS_SERVICE' => '5']]);
$s4 = newService($server, 78, ['module_data' => []]);
Cache::flush();
check($mod->vpsFeatures($s4) === [], 'a VM with that name but marked for another service is not taken');
$s5 = newService($server, 79, ['module_data' => []]);
$one->vm(402, ['name' => 'hand-made']);
check($mod->claim($s5, 402)['success'] && ($one->vms[402]['user']['PNLCS_SERVICE'] ?? '') === '79', 'staff can link a VM by id');
check($mod->claim(newService($server, 80, ['module_data' => []]), 301)['success'] === false, 'linking a VM that belongs to another service is refused');
check($mod->claim(newService($server, 81, ['module_data' => []]), 402)['success'] === false, 'linking a VM already linked in billing is refused');
check($mod->claim(newService($server, 82, ['module_data' => []]), 9999)['success'] === false, 'linking a VM that does not exist is refused');

section('Suspend, unsuspend, terminate');
$svc = $svc->fresh()->setRelation('server', $server);
check($mod->suspend($svc)['success'] && $one->vms[100]['state'] === 8, 'suspend powers the VM off');
check($mod->suspend($svc)['success'], 'suspending twice is fine');
check($mod->unsuspend($svc)['success'] && $one->vms[100]['state'] === 3, 'unsuspend starts it again');
check($mod->accountOnServer($svc) === true, 'accountOnServer: true while it exists');
$r = $mod->terminate($svc);
check($r['success'] && count($one->actions('terminate-hard')) === 1 && $one->vms[100]['state'] === 6, 'terminate sends terminate-hard for the marked VM');
check(($svc->fresh()->module_data['opennebula_vmid'] ?? null) === null, 'and the VM id is cleared');
check($mod->terminate($svc->fresh()->setRelation('server', $server))['success'], 'terminating again, with nothing left, succeeds');
$gone = newService($server, 90, ['module_data' => ['opennebula_vmid' => 12345]]);
check($mod->terminate($gone)['success'] && $mod->accountOnServer($gone) === false, 'a VM deleted outside PNLCS terminates cleanly and reads as gone');

section('Not offered');
check($mod->reinstallChoices($svc) === [] && $mod->reinstall($svc, 'x', 'y')['success'] === false, 'reinstall is not offered');
check($mod->backupLimit($svc) === 0 && ! is_array($mod->backups($svc)) && $mod->backupCreate($svc)['success'] === false, 'backups are not offered');
check($mod->servicePageHides() === ['server_details', 'change_password'], 'the hypervisor address is hidden from the customer');

section('Endpoint');
$client = $mod->client($server);
check($client->endpoint() === 'http://10.1.1.1:2633/RPC2', 'default endpoint is http://host:2633/RPC2');
$tls = (new Server)->forceFill(['hostname' => 'https://one.example.com/', 'ip_address' => '1.2.3.4', 'username' => 'u', 'password' => 'p', 'settings' => []]);
check($mod->client($tls)->endpoint() === 'https://one.example.com/RPC2', 'a full https URL as the hostname is used as it is');
Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refused'));
check(str_contains($mod->client($server)->call('one.system.version')->error, '10.1.1.1'), 'an unreachable oned is reported with its address');

echo PHP_EOL.($failures === 0 ? 'ALL CHECKS PASSED' : "$failures CHECK(S) FAILED").PHP_EOL;
exit($failures === 0 ? 0 : 1);
