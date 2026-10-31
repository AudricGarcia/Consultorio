<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Clínica') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        :root{font-family:Inter,"Segoe UI",Arial,sans-serif;color:#183438;background:#f5f8f8;font-synthesis:none;text-rendering:optimizeLegibility}*{box-sizing:border-box}body{margin:0;min-height:100vh}a{color:#216b66}.shell{min-height:100vh;display:grid;grid-template-columns:238px 1fr}.side{background:#123b40;color:#e9f5f4;padding:24px 16px;display:flex;flex-direction:column}.brand{display:flex;align-items:center;gap:11px;padding:0 8px 26px;color:#fff;text-decoration:none}.mark{height:38px;width:38px;border-radius:12px;background:#b8e5dd;color:#123b40;display:grid;place-items:center;font-weight:800;font-size:20px}.brand strong{font-size:14px}.brand small{display:block;color:#9bbab8;font-size:10px;margin-top:3px}.navlabel{font-size:10px;letter-spacing:.13em;color:#82a5a3;text-transform:uppercase;margin:9px 10px}.nav{display:grid;gap:4px}.nav a{padding:10px 11px;border-radius:9px;color:#c7dcda;text-decoration:none;font-size:12px}.nav a:hover,.nav a.current{background:#205158;color:#fff}.nav form{margin:0}.nav button{width:100%;text-align:left;background:none;border:0;color:#c7dcda;padding:10px 11px;font:inherit;cursor:pointer}.sidefoot{margin-top:auto;padding:14px 9px;border-top:1px solid #315b5e;color:#a6c1bf;font-size:10px;line-height:1.7}.main{padding:30px clamp(18px,4vw,52px) 44px;min-width:0}.bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;color:#718b8d;font-size:12px}.user{display:flex;align-items:center;gap:9px;color:#557175}.user .badge{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:#d8eeea;color:#21625e;font-weight:700}.heading{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;margin:0 0 22px}.kicker{font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:#48867f;font-weight:700}.heading h1{font-size:26px;line-height:1.2;letter-spacing:-.03em;margin:7px 0}.muted{color:#718b8d;font-size:12px}.panel{background:#fff;border:1px solid #e3eceb;border-radius:13px;padding:18px;margin-bottom:14px}.panel h2,.panel h3{font-size:14px;margin:0 0 14px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.stat{background:#fff;border:1px solid #e3eceb;border-radius:12px;padding:16px}.stat label{color:#718b8d;font-size:11px}.stat strong{display:block;font-size:22px;margin:10px 0 4px}.stat small{font-size:10px;color:#8a9d9e}.tablewrap{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:12px}th,td{text-align:left;padding:11px 9px;border-bottom:1px solid #edf2f1;vertical-align:top}th{font-size:10px;color:#779093;font-weight:650}td{color:#355457}.actions{display:flex;gap:8px;flex-wrap:wrap}.btn,button.btn{border:1px solid #cbdcda;border-radius:8px;background:#fff;color:#315b5e;text-decoration:none;font-size:12px;padding:9px 12px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}.btn.primary{border-color:#176d66;background:#176d66;color:white}.btn.danger{border-color:#e7caca;color:#a54040}.btn.small{font-size:10px;padding:6px 9px}.formgrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.field{display:grid;gap:6px}.field.wide{grid-column:1/-1}.field label{font-size:11px;color:#567477}.field input,.field select,.field textarea{width:100%;border:1px solid #dce7e6;border-radius:8px;padding:10px 11px;background:#fff;color:#183438;font:inherit;font-size:12px}.field textarea{min-height:88px;resize:vertical}.hint{font-size:10px;color:#83999a}.error{color:#b43b3b;font-size:11px}.alert{border-radius:9px;padding:11px 13px;margin-bottom:13px;font-size:12px;background:#e8f4f1;color:#285d59}.alert.errorbox{background:#fff0ef;color:#913c39}.tag{display:inline-flex;border-radius:30px;padding:5px 8px;background:#eff5f4;color:#527574;font-size:10px}.pagination{display:flex;justify-content:space-between;padding:14px 0 0;font-size:11px}.empty{padding:30px 10px;text-align:center;color:#84999a;font-size:12px;line-height:1.6}.toothgrid{display:grid;grid-template-columns:repeat(8,minmax(36px,1fr));gap:7px}.tooth{border:1px solid #dbe7e5;border-radius:9px;background:#f9fbfb;padding:8px 5px;text-align:center;text-decoration:none;color:#264e4e;font-size:11px}.tooth small{display:block;color:#88a1a1;font-size:9px;margin-top:4px}.tooth.has-finding{border-color:#e5b778;background:#fff8e9}.success{color:#276a54}
        @media(max-width:900px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.shell{grid-template-columns:205px 1fr}.side{padding:18px 12px}}
        @media(max-width:640px){.shell{display:block}.side{padding:11px 13px;display:block}.brand{padding:0}.side .navlabel,.sidefoot{display:none}.nav{display:flex;overflow:auto;margin-top:11px}.nav a{white-space:nowrap;padding:8px}.main{padding:22px 14px 34px}.bar{margin-bottom:20px}.heading{align-items:flex-start;flex-direction:column}.heading h1{font-size:23px}.formgrid,.grid{grid-template-columns:1fr}.stats{gap:8px}.stat{padding:12px}.stat strong{font-size:20px}.user span{display:none}}
    </style>
</head>
<body>
<div class="shell">
    <aside class="side">
        <a class="brand" href="{{ route('dashboard') }}"><span class="mark">✳</span><span><strong>{{ config('app.name') }}</strong><small>Gestión clínica</small></span></a>
        <div class="navlabel">Principal</div>
        <nav class="nav" aria-label="Navegación principal">
            <a href="{{ route('dashboard') }}">⌂ &nbsp; Resumen</a>
            @can('patients.view')<a href="{{ route('patients.index') }}">♙ &nbsp; Pacientes</a>@endcan
            @can('appointments.view')<a href="{{ route('appointments.index') }}">▦ &nbsp; Agenda</a>@endcan
            @can('clinical.view')<a href="{{ route('clinical-records.index') }}">◉ &nbsp; Historia clínica</a>@endcan
        </nav>
        <div class="navlabel" style="margin-top:22px">Clínica</div>
        <nav class="nav" aria-label="Módulos clínicos">
            @can('patients.view')<a href="{{ route('patients.index') }}">◌ &nbsp; Odontogramas</a>@endcan
            @can('treatments.view')<a href="{{ route('treatment-plans.index') }}">▤ &nbsp; Tratamientos</a>@endcan
        </nav>
        <div class="navlabel" style="margin-top:18px">Administración</div>
        <nav class="nav">@if(auth()->user()->isOwner())<a href="{{ route('users.index') }}">♙ &nbsp; Usuarios</a><a href="{{ route('roles.index') }}">🔑 &nbsp; Roles y permisos</a><a href="{{ route('treatment-catalog.index') }}">▤ &nbsp; Catálogo dental</a>@endif @can('reports.financial')<a href="{{ route('reports.financial') }}">▤ &nbsp; Reportes</a>@endcan @can('audit.view')<a href="{{ route('reports.audit') }}">◷ &nbsp; Auditoría</a>@endcan @can('payments.view')<a href="{{ route('payments.index') }}">◇ &nbsp; Pagos</a>@endcan @can('cash.view')<a href="{{ route('cash.index') }}">◫ &nbsp; Caja</a>@endcan @can('inventory.view')<a href="{{ route('inventory.index') }}">▧ &nbsp; Inventario</a>@endcan @can('purchases.manage')<a href="{{ route('purchase-orders.index') }}">▤ &nbsp; Compras</a>@endcan @can('suppliers.manage')<a href="{{ route('suppliers.index') }}">♧ &nbsp; Proveedores</a>@endcan</nav>
        <div class="sidefoot">Español · Soles (PEN)<br>Zona horaria: Lima<br><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Cerrar sesión</button></form></div>
    </aside>
    <main class="main">
        <div class="bar"><span>Clínica / @yield('title', 'Panel')</span><span class="user"><span class="badge">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span>{{ auth()->user()->name }}</span></span></div>
        @if(session('success'))<div class="alert">{{ session('success') }}</div>@endif
        @if(session('info'))<div class="alert">{{ session('info') }}</div>@endif
        @if($errors->any())<div class="alert errorbox"><strong>Revisa los datos:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
        <div class="muted" style="font-size:10px;margin-top:25px">{{ config('app.name') }} · Sistema en desarrollo</div>
    </main>
</div>
@include('components.voice-assistant', ['activePatient' => isset($patient) ? $patient : null])
@livewireScripts
</body>
</html>
