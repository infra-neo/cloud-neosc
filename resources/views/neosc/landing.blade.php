{{-- NeoSC public landing. Port of the Lovable design; plans come from the catalog. Add ?classic=1 to see the old page. --}}
@php
    $brand = $brandName ?? 'NeoSC';
    $cur = $currency ?? null;
    $hasOffering = class_exists(\App\Support\StoreOffering::class);
    $kindMeta = $hasOffering ? \App\Support\StoreOffering::KINDS : [];

    $plans = [];
    foreach (($products ?? collect()) as $product) {
        $priced = method_exists($product, 'pricedCycles') ? $product->pricedCycles($cur?->id ?? null) : [];
        $cfg = is_string($product->config_options) ? json_decode($product->config_options, true) : ($product->config_options ?? []);
        $specs = [];
        for ($i = 1; $i <= 6; $i++) {
            if (! empty($cfg["f{$i}"])) { $specs[] = (string) $cfg["f{$i}"]; }
        }
        if (! $specs && method_exists($product, 'resourceSummary')) {
            $specs = array_map(fn ($r) => (string) $r['text'], $product->resourceSummary());
        }
        $kind = 'all';
        if ($hasOffering) {
            try { $kind = \App\Support\StoreOffering::kind($product); } catch (\Throwable $e) { $kind = 'other'; }
        }
        $plans[] = [
            'name' => $product->name,
            'kind' => $kind,
            'featured' => (bool) $product->is_featured,
            'desc' => \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 110),
            'specs' => array_slice($specs, 0, 6),
            'cycles' => array_map('floatval', $priced),
            'url' => '/client/store/configure/'.$product->slug,
        ];
    }
    $kinds = [];
    foreach ($kindMeta as $k => $m) {
        $n = count(array_filter($plans, fn ($p) => $p['kind'] === $k));
        if ($n > 0) { $kinds[$k] = ['name' => __("store.kind.$k.name"), 'blurb' => __("store.kind.$k.blurb"), 'count' => $n]; }
    }
    $jsCur = ['prefix' => (string) ($cur?->prefix ?? '$'), 'suffix' => (string) ($cur?->suffix ?? '')];

    $apps = [
        'Populares' => [['n8n','Automatización de flujos'],['Uptime Kuma','Monitoreo de sitios y servicios'],['Chatwoot','Chat de soporte a clientes'],['Gitea','Tu propio servidor Git'],['Nextcloud','Archivos, calendario y contactos'],['Ollama','Tus propios modelos de IA'],['Open WebUI','Interfaz de chat con IA'],['WordPress','Sitios y blogs optimizados']],
        'Sitios y contenido' => [['WordPress','Sitios y blogs optimizados'],['Ghost','Publicaciones y newsletters'],['Joomla','Gestor de contenidos'],['Drupal','CMS para sitios complejos'],['Immich','Biblioteca de fotos y video']],
        'Automatización e IA' => [['n8n','Automatización de flujos'],['Ollama','Tus propios modelos de IA'],['Open WebUI','Interfaz de chat con IA']],
        'Equipos y productividad' => [['Nextcloud','Archivos, calendario y contactos'],['Chatwoot','Chat de soporte a clientes'],['ONLYOFFICE','Documentos en equipo']],
        'Desarrollo' => [['Gitea','Tu propio servidor Git'],['code-server','VS Code en el navegador'],['Laravel','Framework de aplicaciones PHP']],
        'Bases de datos' => [['MySQL','Base de datos relacional'],['PostgreSQL','Base de datos avanzada'],['MongoDB','Base de datos de documentos'],['Redis','Caché y datos en memoria']],
        'Monitoreo y analítica' => [['Uptime Kuma','Monitoreo de sitios y servicios'],['Matomo Analytics','Analítica de visitantes'],['Umami','Analítica simple y privada']],
    ];
    $deploy = [
        ['NeoSC Cloud', [['NeoSC Cloud México','ok']]],
        ['Nubes públicas', [['Google Cloud','soon'],['Oracle Cloud','soon'],['Amazon Web Services','soon'],['Microsoft Azure','soon'],['Huawei Cloud','soon'],['Alibaba Cloud','ask'],['IBM Cloud','ask'],['DigitalOcean','ask']]],
        ['Nubes privadas', [['OpenStack','ask'],['VMware vSphere','soon']]],
        ['Hipervisores', [['KVM','ok']]],
        ['Contenedores', [['LXD','ok'],['Docker','soon'],['Kubernetes','ask'],['OpenShift','ask']]],
        ['Otro', [['Servidor propio','soon']]],
    ];
    $st = ['ok' => 'Disponible', 'soon' => 'Próximamente', 'ask' => 'Bajo petición'];
    $check = '<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $brand }} — Escritorios Windows con Zero Trust</title>
<meta name="description" content="Escritorios Windows persistentes y no persistentes, con acceso Zero Trust, apps en contenedor y cero puertos abiertos.">
@if(!empty($customFavicon))<link rel="icon" href="{{ $customFavicon }}">@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg:#f6f7f9;--card:#fff;--fg:#14161a;--muted:#5b6370;--line:#d9dce1;--sel:#eef4fd;--accent:#1a6fd1;--accent-h:#1559a8;--on-accent:#fff;--accent-dark:#3b8cf0;--panel:#111418;--ok:#1a7f45}
html[data-theme="dark"]{--bg:#0e1116;--card:#171b22;--fg:#e8eaee;--muted:#9aa3b1;--line:#2a303a;--sel:#16263d;--accent:#3b8cf0;--accent-h:#6aa8f5;--on-accent:#0b1220;--panel:#0a0c10;--ok:#3ecf7d}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--fg);font-family:Inter,system-ui,sans-serif;font-size:15px;line-height:1.5}
a{color:var(--accent)}a:hover{color:var(--accent-h)}
button{font-family:inherit}
:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
[hidden]{display:none!important}
.w{max-width:1200px;margin:0 auto;padding:0 24px}
.bar{background:var(--panel);color:#c9ced6;font-size:13px;text-align:center;padding:9px 24px}.bar a{color:#fff;font-weight:600}
header.top{background:var(--card);border-bottom:1px solid var(--line)}
header.top .w{display:flex;flex-wrap:wrap;align-items:center;gap:16px 32px;padding-top:16px;padding-bottom:16px}
.logo{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--fg);font-size:20px;font-weight:800;letter-spacing:-.02em}
.logo i{display:inline-flex;width:30px;height:30px;border-radius:8px;background:var(--accent);align-items:center;justify-content:center}
.logo b{color:var(--accent)}
.nav{display:flex;flex-wrap:wrap;gap:8px 24px;flex-grow:1}.nav a{color:var(--fg);text-decoration:none;font-weight:500;font-size:14px}.nav a:hover{color:var(--accent)}
.acts{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.btn{display:inline-block;text-align:center;text-decoration:none;font-weight:700;font-size:14px;padding:11px 18px;border-radius:10px;border:1px solid var(--fg);background:var(--card);color:var(--fg);cursor:pointer}
.btn:hover{border-color:var(--accent);color:var(--accent)}
.btn.pri{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}.btn.pri:hover{background:var(--accent-h);border-color:var(--accent-h);color:var(--on-accent)}
.lnk{color:var(--fg);text-decoration:none;font-weight:600;font-size:14px;padding:10px 14px}
.hero{padding:72px 0 56px;display:flex;flex-wrap:wrap;gap:48px;align-items:center}
.hero>div{flex:1 1 420px;min-width:0}
.eyebrow{margin:0 0 14px;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent)}
.hero h1{margin:0 0 18px;font-size:clamp(34px,5vw,50px);line-height:1.05;font-weight:800;letter-spacing:-.035em}
.lead{margin:0 0 24px;font-size:18px;color:var(--muted);max-width:540px}
.chips{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:10px}
.chips li{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border:1px solid var(--line);border-radius:999px;background:var(--card);font-size:14px;font-weight:600}
.chips svg{color:var(--ok)}
.wiz{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:32px}
.wiz h2{margin:0 0 4px;font-size:24px;font-weight:800;letter-spacing:-.02em;text-align:center}
.wiz .sub{margin:0 0 20px;text-align:center;color:var(--muted);font-size:14px}
.dots{display:flex;justify-content:center;gap:6px;margin-bottom:24px}.dots span{width:28px;height:4px;border-radius:2px;background:var(--line)}.dots span.on{background:var(--accent)}
.wiz h3{margin:0 0 14px;font-size:17px;font-weight:700}
.opts{display:grid;gap:10px}
.opt{text-align:left;cursor:pointer;background:var(--card);color:var(--fg);border:1px solid var(--line);border-radius:12px;padding:14px 16px;min-height:56px}
.opt:hover{border-color:var(--accent);background:var(--sel)}
.opt b{display:block;font-size:15px}.opt small{display:block;color:var(--muted);font-size:13px}
.wiz .back{margin-top:14px;background:none;border:0;color:var(--muted);cursor:pointer;font-size:13px;font-weight:600}
.res{border:2px solid var(--accent);border-radius:14px;padding:18px;background:var(--sel)}.res b{font-size:18px}.res p{margin:6px 0 14px;color:var(--muted);font-size:14px}
section.s{padding:24px 0 72px}
.s h2.t{margin:0 0 8px;font-size:clamp(26px,3.4vw,34px);font-weight:800;letter-spacing:-.03em}
.s p.d{margin:0 0 28px;color:var(--muted);max-width:700px}
.two{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px}
.card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:28px;display:flex;flex-direction:column;gap:16px}
.card .k{font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--accent)}
.card h3{margin:4px 0;font-size:24px;font-weight:800;letter-spacing:-.02em}.card p{margin:0;color:var(--muted)}
.ul{list-style:none;margin:0;padding:0;display:grid;gap:8px;font-size:14px}.ul li{display:flex;gap:10px;align-items:flex-start}.ul svg{flex:0 0 auto;margin-top:3px;color:var(--ok)}
.pills{display:flex;flex-wrap:wrap;gap:8px}.pills span{padding:6px 12px;border-radius:999px;background:var(--sel);font-size:13px;font-weight:600}
.card .ideal{border-top:1px solid var(--line);padding-top:14px}.card .ideal p{font-size:13px;font-weight:700;color:var(--fg);margin-bottom:8px}
.head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:20px;margin-bottom:24px}
.seg{display:inline-flex;background:var(--line);border-radius:10px;padding:3px}
.seg button{border:0;cursor:pointer;font-weight:600;font-size:14px;padding:9px 16px;border-radius:8px;min-height:40px;background:transparent;color:var(--muted)}
.seg button.on{background:var(--card);color:var(--fg)}
.tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.tab{cursor:pointer;font-weight:600;font-size:14px;padding:9px 16px;border-radius:999px;min-height:40px;border:1px solid var(--line);background:var(--card);color:var(--fg)}
.tab:hover{border-color:var(--accent)}
.tab[aria-selected="true"]{background:var(--fg);border-color:var(--fg);color:var(--bg)}
.tab b{font-size:11px;margin-left:6px;padding:1px 7px;border-radius:999px;background:var(--sel);color:var(--accent)}
.tab[aria-selected="true"] b{background:color-mix(in srgb,var(--bg) 20%,transparent);color:var(--bg)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;align-items:stretch}
.plan{position:relative;display:flex;flex-direction:column;background:var(--card);border:1px solid var(--line);border-radius:18px;padding:26px}
.plan:hover{border-color:var(--accent)}.plan.f{border:2px solid var(--accent);padding:25px}
.plan .bd{position:absolute;top:-12px;right:20px;font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px;background:var(--accent);color:var(--on-accent)}
.plan h3{margin:0 0 4px;font-size:20px;font-weight:800}.plan .ds{margin:0 0 16px;color:var(--muted);font-size:14px;min-height:42px}
.plan .pr{font-size:34px;font-weight:800;letter-spacing:-.03em}.plan .pr small{font-size:15px;font-weight:500;color:var(--muted);letter-spacing:0}
.plan .nt{margin:0 0 18px;color:var(--muted);font-size:12px}
.plan .ul{margin:0 0 22px;padding-top:14px;border-top:1px solid var(--line);flex-grow:1}
.plan.cu{border-style:dashed}
.zt{background:var(--panel);color:#e8eaee}.zt .w{padding-top:72px;padding-bottom:72px}
.zt .eyebrow{color:var(--accent-dark)}.zt h2{margin:0 0 12px;font-size:clamp(28px,4vw,38px);line-height:1.1;font-weight:800;letter-spacing:-.03em;color:#fff;max-width:760px}
.zt .d{margin:0 0 36px;color:#aab1bd;max-width:680px}
.flow{list-style:none;margin:0 0 36px;padding:0;display:flex;flex-wrap:wrap;gap:12px}
.flow li{flex:1 1 170px;display:flex;align-items:center;gap:12px}
.flow div{flex:1;border:1px solid #2a2f38;border-radius:14px;padding:16px;min-height:112px}
.flow em{display:block;font-style:normal;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--accent-dark);margin-bottom:6px}
.flow strong{display:block;color:#fff;margin-bottom:4px}.flow small{font-size:13px;color:#aab1bd}
.flow svg{flex:0 0 auto;color:#6b7480}
.facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px}
.facts div{border:1px solid #2a2f38;border-radius:16px;padding:22px}.facts b{display:block;font-size:30px;font-weight:800;letter-spacing:-.03em;color:#fff;margin-bottom:4px}
.facts h3{margin:0 0 6px;font-size:16px;color:#fff}.facts p{margin:0;color:#aab1bd;font-size:14px}
.apps{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px;margin-top:20px}
.app{display:flex;gap:14px;align-items:center;padding:16px;border:1px solid var(--line);border-radius:14px;background:var(--card);color:var(--fg);text-decoration:none}
.app:hover{border-color:var(--accent);color:var(--fg);transform:translateY(-2px)}
.app i{width:44px;height:44px;border-radius:10px;flex:none;display:inline-flex;align-items:center;justify-content:center;font-style:normal;font-weight:800;font-size:14px;background:var(--sel);color:var(--accent);border:1px solid var(--line)}
.app strong{display:block;font-size:15px}.app small{color:var(--muted);font-size:13px}
.steps{list-style:none;padding:0;margin:0 0 8px;display:flex;flex-wrap:wrap;gap:20px 32px}.steps li{display:flex;gap:12px}.steps span{width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--sel);color:var(--accent);font-weight:700;font-size:13px;flex:none}.steps small{display:block;color:var(--muted);font-size:13px}
.dep{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px}
.dep div{padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:var(--bg)}.dep strong{display:block;font-size:13px}
.dep small{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:var(--muted)}.dep small.ok{color:var(--ok)}.dep small:before{content:"";width:6px;height:6px;border-radius:50%;background:var(--line)}.dep small.ok:before{background:var(--ok)}
footer.ft{background:var(--panel);color:#aab1bd;font-size:13px}footer.ft .w{padding-top:36px;padding-bottom:36px;display:flex;flex-wrap:wrap;gap:16px 40px;justify-content:space-between;align-items:center}
footer.ft a{color:#aab1bd}footer.ft b{font-size:18px;color:#fff}footer.ft b span{color:var(--accent-dark)}
@media (prefers-reduced-motion:no-preference){.app{transition:border-color .15s,transform .15s}}
</style>
</head>
<body>
<div class="bar">Escritorios Windows persistentes y no persistentes, con acceso Zero Trust y cero puertos abiertos. <a href="#planes">Ver planes</a></div>
<header class="top"><div class="w">
 <a class="logo" href="/"><i><svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--on-accent)" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18V6l12 12V6"/></svg></i><span>{{ $brand === 'NeoSC' ? 'Neo' : $brand }}@if($brand === 'NeoSC')<b>SC</b>@endif</span></a>
 <nav class="nav" aria-label="Principal"><a href="#vdi">Escritorios VDI</a><a href="#planes">Planes</a><a href="#seguridad">Seguridad</a><a href="#apps">Aplicaciones</a><a href="#integraciones">Integraciones</a><a href="/client/contact">Soporte</a></nav>
 <div class="acts"><a class="lnk" href="{{ route('client.login') }}">Iniciar sesión</a><a class="btn pri" href="{{ route('client.register') }}">Comenzar</a></div>
</div></header>

<main>
<div class="w"><section class="hero">
 <div>
  <p class="eyebrow">{{ $brand }} Workstation · VDI Windows</p>
  <h1>Escritorios Windows persistentes o desechables, protegidos con Zero Trust.</h1>
  <p class="lead">Dale a cada persona su escritorio, o un puesto limpio en cada sesión. Se accede desde el navegador, por identidad, sin exponer ningún puerto a internet.</p>
  <ul class="chips">@foreach(['Persistente y no persistente','0 puertos abiertos','Acceso por identidad','GPU e IA'] as $c)<li>{!! $check !!}<span>{{ $c }}</span></li>@endforeach</ul>
 </div>
 <div class="wiz" id="wiz">
  <h2>Encontremos el plan para ti</h2><p class="sub">Tres preguntas cortas. Sin registro.</p>
  <div class="dots" aria-hidden="true"><span class="on"></span><span></span><span></span></div>
  <div id="wz-body"></div>
 </div>
</section></div>

<div class="w" id="vdi"><section class="s">
 <h2 class="t">Persistente o no persistente: tú decides cómo vive cada escritorio.</h2>
 <p class="d">Los dos modelos corren Windows 11 Pro o Windows Server 2025 y se administran desde el mismo panel. Cambia según el puesto, no según la tecnología.</p>
 <div class="two">
  <article class="card"><div><span class="k">Un escritorio, una persona</span><h3>Persistente</h3><p>El escritorio es de quien lo usa y se queda como lo dejó.</p></div>
   <ul class="ul">@foreach(['Conserva archivos, configuración y aplicaciones entre sesiones','Un escritorio asignado a cada usuario','Lo administras como un equipo propio'] as $i)<li>{!! $check !!}<span>{{ $i }}</span></li>@endforeach</ul>
   <div class="ideal"><p>Ideal para</p><div class="pills"><span>Desarrollo</span><span>Contabilidad</span><span>Diseño</span><span>Dirección</span></div></div></article>
  <article class="card"><div><span class="k">Puestos compartidos</span><h3>No persistente</h3><p>Cada sesión empieza limpia, como el primer día.</p></div>
   <ul class="ul">@foreach(['El escritorio vuelve a su estado original al terminar la sesión','Un mismo modelo para muchos puestos','Menos mantenimiento por equipo'] as $i)<li>{!! $check !!}<span>{{ $i }}</span></li>@endforeach</ul>
   <div class="ideal"><p>Ideal para</p><div class="pills"><span>Atención telefónica</span><span>Capacitación</span><span>Kioscos</span><span>Pruebas</span></div></div></article>
 </div>
</section></div>

<div class="w" id="planes"><section class="s" style="padding-top:0">
 <div class="head"><div><h2 class="t" style="margin:0 0 6px">Planes y precios</h2><p class="d" style="margin:0">Escritorios Windows, equipos con GPU, cómputo para IA y apps en contenedor.</p></div>
  <div class="seg" id="pl-cycles" role="group" aria-label="Periodo de pago" hidden></div></div>
 <div class="tabs" id="pl-tabs" role="tablist" aria-label="Tipo de oferta"></div>
 <p class="d" id="pl-blurb" style="margin-bottom:20px;font-size:14px" hidden></p>
 <div class="grid" id="pl-grid"></div>
 <p style="margin:20px 0 0;font-size:13px;color:var(--muted)">Los precios se muestran con impuestos según tu país. Cancela cuando quieras. <a href="/client/store">Ver todo el catálogo</a></p>
</section></div>

<section class="zt" id="seguridad"><div class="w">
 <p class="eyebrow">Arquitectura Zero Trust</p><h2>Sin puertos abiertos. Nadie entra sin identidad.</h2>
 <p class="d">Tu escritorio nunca se publica en internet. El acceso viaja por NeoMesh, una red privada cifrada, y solo se concede a quien se identifica y pertenece al grupo correcto.</p>
 <ol class="flow">
  @php $flow=[['Tu equipo','Navegador','Entras desde cualquier equipo, sin instalar nada.'],['NeoGuard','Identidad y MFA','Cada acceso se valida con tu cuenta de empresa.'],['NeoProxy','Políticas de acceso','Solo ves lo que tu grupo tiene permitido.'],['NeoMesh','Red privada cifrada','El tráfico viaja por un túnel privado hacia tu escritorio.'],['NeoDesk','Tu escritorio','Windows persistente o no persistente, listo para trabajar.']]; @endphp
  @foreach($flow as $n => $f)<li><div><em>{{ $f[0] }}</em><strong>{{ $f[1] }}</strong><small>{{ $f[2] }}</small></div>@if($n < 4)<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>@endif</li>@endforeach
 </ol>
 <div class="facts">
  <div><b>0</b><h3>Puertos abiertos a internet</h3><p>Tus escritorios no se publican. No hay RDP expuesto que atacar ni que escanear.</p></div>
  <div><b>Por identidad</b><h3>Acceso por persona y grupo</h3><p>Los permisos siguen a la persona, no a la dirección de red.</p></div>
  <div><b>Por empresa</b><h3>Aislamiento entre clientes</h3><p>Cada empresa tiene sus propios usuarios, grupos y red privada.</p></div>
  <div><b>Sin VPN</b><h3>Nada que instalar</h3><p>Tu equipo entra desde el navegador; la red privada la gestionamos nosotros.</p></div>
 </div>
</div></section>

<div class="w" id="apps"><section class="s" style="padding-top:72px">
 <h2 class="t" style="font-size:clamp(28px,4vw,40px)">Decenas de apps, un clic</h2>
 <p class="d" style="max-width:520px">Elige una app y queda instalada en tu cuenta. Administra tu dominio y tu app desde el mismo panel.</p>
 <ol class="steps">@foreach([['1','Elige','La app y el plan'],['2','Conecta','Tu dominio, SSL incluido'],['3','Úsala','Actualizaciones y respaldos en el panel']] as $s)<li><span>{{ $s[0] }}</span><div><strong>{{ $s[1] }}</strong><small>{{ $s[2] }}</small></div></li>@endforeach</ol>
 <div class="tabs" id="ap-tabs" role="tablist" aria-label="Categorías de apps" style="margin-top:28px"></div>
 <div class="apps" id="ap-grid"></div>
</section></div>

<div class="w" id="integraciones"><section class="s" style="padding-top:24px">
 <h2 class="t">Constrúyelo donde quieras.</h2>
 <p class="d">Despliega en NeoSC Cloud o conecta tu propia infraestructura y tus nubes. Todo se administra desde el mismo panel y se accede con la misma identidad.</p>
 <div class="tabs" id="dp-tabs" role="tablist" aria-label="Plataformas de despliegue"></div>
 <div class="dep" id="dp-grid"></div>
 <p style="margin:14px 0 0;font-size:13px;color:var(--muted)">¿Necesitas otra plataforma? <a href="/client/contact">Cuéntanos</a> y evaluamos la integración.</p>
</section></div>
</main>

<footer class="ft"><div class="w">
 <b>Neo<span>SC</span> <small style="font-weight:500;font-size:13px;color:#aab1bd">por Neogenesys</small></b>
 <span><a href="/client/contact">Soporte</a> · <a href="/client/tickets/create">Abrir un ticket</a> · <a href="{{ route('client.login') }}">Mi cuenta</a></span>
</div></footer>

<script>
(function () {
  var PLANS = @json($plans), KINDS = @json($kinds), CUR = @json($jsCur), APPS = @json($apps), DEP = @json($deploy), ST = @json($st);
  var $ = function (id) { return document.getElementById(id); };
  function el(t, c, x) { var e = document.createElement(t); if (c) e.className = c; if (x != null) e.textContent = x; return e; }
  function money(n) { return CUR.prefix + Number(n).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + CUR.suffix; }
  var CYL = {monthly: 'mes', quarterly: 'trimestre', semiannually: 'semestre', annually: 'año', biennially: '2 años', triennially: '3 años', onetime: 'pago único'};
  function cyl(c) { return CYL[c] || c; }
  var CK = '<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>';

  /* ---- Wizard: 3 preguntas -> sección del catálogo ---- */
  var wz = {n: 0, ans: {}};
  var QS = [
    {q: '¿Cuántas personas lo usarán?', k: 'who', o: [['solo', 'Solo yo', 'Un escritorio, una persona'], ['team', 'Equipo pequeño', 'De 2 a 10 personas'], ['biz', 'Empresa', 'Más de 10 personas']]},
    {q: '¿Cómo quieres que viva el escritorio?', k: 'mode', o: [['keep', 'Se queda como lo dejé', 'Persistente: conserva archivos y apps'], ['clean', 'Limpio en cada sesión', 'No persistente: ideal para puestos compartidos']]},
    {q: '¿Qué vas a hacer en él?', k: 'use', o: [['office', 'Trabajo de oficina y apps', 'Correo, documentos, sistemas de la empresa'], ['gpu', 'Diseño, 3D o video', 'Necesito GPU'], ['ai', 'Inteligencia artificial', 'Entrenar o ejecutar modelos']]}
  ];
  function wzDots() { var d = document.querySelectorAll('#wiz .dots span'); for (var i = 0; i < d.length; i++) d[i].className = i <= wz.n ? 'on' : ''; }
  function wzDraw() {
    var b = $('wz-body'); b.textContent = ''; wzDots();
    if (wz.n >= QS.length) {
      var kind = wz.ans.use === 'gpu' ? 'gpu' : wz.ans.use === 'ai' ? 'ia' : (wz.ans.mode === 'keep' ? 'vdi-persistent' : 'vdi-ephemeral');
      var label = KINDS[kind] ? KINDS[kind].name : 'Planes recomendados';
      var r = el('div', 'res'); r.appendChild(el('b', null, label));
      r.appendChild(el('p', null, 'Es lo que mejor encaja con lo que nos contaste. Elige el tamaño en el catálogo y lo configuras en minutos.'));
      var a = el('a', 'btn pri', 'Ver planes recomendados'); a.href = '/client/store?kind=' + encodeURIComponent(kind); r.appendChild(a);
      b.appendChild(r);
      var back = el('button', 'back', 'Empezar de nuevo'); back.type = 'button'; back.onclick = function () { wz = {n: 0, ans: {}}; wzDraw(); }; b.appendChild(back);
      return;
    }
    var Q = QS[wz.n]; b.appendChild(el('h3', null, Q.q)); var g = el('div', 'opts');
    Q.o.forEach(function (o) {
      var x = el('button', 'opt'); x.type = 'button'; x.appendChild(el('b', null, o[1])); x.appendChild(el('small', null, o[2]));
      x.onclick = function () { wz.ans[Q.k] = o[0]; wz.n++; wzDraw(); }; g.appendChild(x);
    });
    b.appendChild(g);
    if (wz.n > 0) { var bk = el('button', 'back', '← Atrás'); bk.type = 'button'; bk.onclick = function () { wz.n--; wzDraw(); }; b.appendChild(bk); }
  }
  wzDraw();

  /* ---- Planes desde el catálogo ---- */
  var keys = Object.keys(KINDS), st = {kind: keys[0] || 'all', cycle: ''};
  var cycles = [];
  PLANS.forEach(function (p) { Object.keys(p.cycles).forEach(function (c) { if (cycles.indexOf(c) < 0) cycles.push(c); }); });
  ['monthly', 'annually'].forEach(function (c) { var i = cycles.indexOf(c); if (i > -1) { cycles.splice(i, 1); cycles.push(c); } });
  cycles = cycles.sort(function (a, b) { return a === 'monthly' ? -1 : b === 'monthly' ? 1 : 0; }).slice(0, 4);
  st.cycle = cycles[0] || '';
  function plDraw() {
    var grid = $('pl-grid'); grid.textContent = '';
    PLANS.filter(function (p) { return st.kind === 'all' || p.kind === st.kind; }).forEach(function (p) {
      var c = el('article', 'plan' + (p.featured ? ' f' : ''));
      if (p.featured) c.appendChild(el('span', 'bd', 'Más popular'));
      c.appendChild(el('h3', null, p.name)); c.appendChild(el('p', 'ds', p.desc));
      var cy = p.cycles[st.cycle] !== undefined ? st.cycle : Object.keys(p.cycles)[0];
      var pr = el('div', 'pr');
      if (cy === undefined) pr.textContent = 'Contáctanos'; else { pr.textContent = money(p.cycles[cy]); pr.appendChild(el('small', null, ' /' + cyl(cy))); }
      c.appendChild(pr); c.appendChild(el('p', 'nt', cy === undefined ? 'Dinos qué necesitas y armamos la oferta.' : 'Se factura cada ' + cyl(cy) + '.'));
      var ul = el('ul', 'ul'); p.specs.forEach(function (s) { var li = el('li'); li.innerHTML = CK; li.appendChild(el('span', null, s)); ul.appendChild(li); }); c.appendChild(ul);
      var a = el('a', 'btn' + (p.featured ? ' pri' : ''), 'Elegir plan'); a.href = p.url + (cy ? '?cycle=' + encodeURIComponent(cy) : ''); c.appendChild(a);
      grid.appendChild(c);
    });
    var cu = el('article', 'plan cu'); cu.appendChild(el('h3', null, 'A tu medida')); cu.appendChild(el('p', 'ds', 'Más volumen, más recursos o requisitos propios.'));
    cu.appendChild(el('div', 'pr', 'Contáctanos')); cu.appendChild(el('p', 'nt', 'Dinos qué necesitas y armamos la oferta.'));
    var cul = el('ul', 'ul'); ['Volumen de escritorios y usuarios', 'Políticas de acceso por grupo', 'Acompañamiento en la puesta en marcha', 'Facturación a la medida'].forEach(function (s) { var li = el('li'); li.innerHTML = CK; li.appendChild(el('span', null, s)); cul.appendChild(li); });
    cu.appendChild(cul); var ca = el('a', 'btn', 'Hablar con ventas'); ca.href = '/client/contact'; cu.appendChild(ca); grid.appendChild(cu);
    var bl = KINDS[st.kind] ? KINDS[st.kind].blurb : ''; $('pl-blurb').textContent = bl; $('pl-blurb').hidden = !bl;
    Array.prototype.forEach.call(document.querySelectorAll('#pl-tabs .tab'), function (t) { t.setAttribute('aria-selected', t.dataset.k === st.kind ? 'true' : 'false'); });
    Array.prototype.forEach.call(document.querySelectorAll('#pl-cycles button'), function (t) { t.className = t.dataset.c === st.cycle ? 'on' : ''; });
  }
  function tabs(root, items, on) {
    items.forEach(function (it) { var b = el('button', 'tab', it.label); b.type = 'button'; b.setAttribute('role', 'tab'); b.dataset.k = it.key;
      if (it.count != null) b.appendChild(el('b', null, String(it.count))); b.onclick = function () { on(it.key); }; root.appendChild(b); });
  }
  tabs($('pl-tabs'), keys.length ? keys.map(function (k) { return {key: k, label: KINDS[k].name, count: KINDS[k].count}; }) : [{key: 'all', label: 'Todos los planes'}], function (k) { st.kind = k; plDraw(); });
  if (cycles.length > 1) { var sg = $('pl-cycles'); cycles.forEach(function (c) { var b = el('button', null, cyl(c)); b.type = 'button'; b.dataset.c = c; b.onclick = function () { st.cycle = c; plDraw(); }; sg.appendChild(b); }); sg.hidden = false; }
  plDraw();

  /* ---- Catálogo de apps ---- */
  var acat = Object.keys(APPS)[0];
  function apDraw() {
    var g = $('ap-grid'); g.textContent = '';
    APPS[acat].forEach(function (a) { var x = el('a', 'app'); x.href = '/client/store?kind=app-oneclick'; x.appendChild(el('i', null, a[0].slice(0, 2))); var d = el('span'); d.appendChild(el('strong', null, a[0])); d.appendChild(el('small', null, a[1])); x.appendChild(d); g.appendChild(x); });
    Array.prototype.forEach.call(document.querySelectorAll('#ap-tabs .tab'), function (t) { t.setAttribute('aria-selected', t.dataset.k === acat ? 'true' : 'false'); });
  }
  tabs($('ap-tabs'), Object.keys(APPS).map(function (k) { return {key: k, label: k}; }), function (k) { acat = k; apDraw(); }); apDraw();

  /* ---- Despliega donde quieras ---- */
  var dcat = 0;
  function dpDraw() {
    var g = $('dp-grid'); g.textContent = '';
    DEP[dcat][1].forEach(function (it) { var d = el('div'); d.appendChild(el('strong', null, it[0])); d.appendChild(el('small', it[1] === 'ok' ? 'ok' : '', ST[it[1]])); g.appendChild(d); });
    Array.prototype.forEach.call(document.querySelectorAll('#dp-tabs .tab'), function (t) { t.setAttribute('aria-selected', Number(t.dataset.k) === dcat ? 'true' : 'false'); });
  }
  tabs($('dp-tabs'), DEP.map(function (d, i) { return {key: String(i), label: d[0], count: d[1].length}; }), function (k) { dcat = Number(k); dpDraw(); }); dpDraw();
})();
</script>
</body>
</html>
