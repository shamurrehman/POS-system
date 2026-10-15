<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ setting('store_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
@php
    $user = auth()->user();
    $isManager = $user->isManagerOrAdmin();
    $nav = fn (string $pattern) => request()->routeIs($pattern) ? 'nav-link active' : 'nav-link';
@endphp
<body class="bg-slate-100 text-slate-800 antialiased" x-data="{ sidebar: false }">
<div class="min-h-screen flex">
    {{-- Sidebar --}}
    <aside class="no-print fixed inset-y-0 left-0 z-40 w-64 bg-slate-900 text-white flex flex-col transform transition-transform lg:translate-x-0"
           :class="sidebar ? 'translate-x-0' : '-translate-x-full'">
        <div class="h-16 flex items-center gap-3 px-5 border-b border-slate-800">
            <div class="w-9 h-9 rounded-lg bg-brand-600 flex items-center justify-center"><x-icon name="pill" class="w-5 h-5" /></div>
            <div class="min-w-0">
                <div class="font-semibold truncate">{{ setting('store_name') }}</div>
                <div class="text-xs text-slate-400">Pharmacy POS</div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-0.5">
            <a href="{{ route('dashboard') }}" class="{{ $nav('dashboard') }}"><x-icon name="home" /> Dashboard</a>
            <a href="{{ route('pos') }}" class="{{ $nav('pos') }}"><x-icon name="cart" /> Point of Sale</a>
            <a href="{{ route('sales.index') }}" class="{{ $nav('sales.*') }}"><x-icon name="receipt" /> Sales & Invoices</a>
            <a href="{{ route('alerts') }}" class="{{ $nav('alerts') }}"><x-icon name="bell" /> Alerts
                @if($alertCount)<span class="ml-auto badge bg-red-500 text-white">{{ $alertCount }}</span>@endif
            </a>

            <div class="nav-section">Inventory</div>
            <a href="{{ route('products.index') }}" class="{{ $nav('products.*') }}"><x-icon name="pill" /> Medicines</a>
            @if($isManager)
                <a href="{{ route('inventory.index') }}" class="{{ $nav('inventory.index') }}"><x-icon name="box" /> Stock & Batches</a>
                <a href="{{ route('purchases.index') }}" class="{{ $nav('purchases.*') }}"><x-icon name="inbox" /> Stock In / Purchases</a>
                <a href="{{ route('adjustments.index') }}" class="{{ $nav('adjustments.*') }}"><x-icon name="adjust" /> Adjustments & Write-offs</a>
                <a href="{{ route('inventory.history') }}" class="{{ $nav('inventory.history') }}"><x-icon name="history" /> Stock History</a>
                <a href="{{ route('suppliers.index') }}" class="{{ $nav('suppliers.*') }}"><x-icon name="truck" /> Suppliers</a>
                <a href="{{ route('categories.index') }}" class="{{ $nav('categories.*') }}"><x-icon name="tag" /> Categories</a>

                <div class="nav-section">Reports</div>
                <a href="{{ route('reports.index') }}" class="{{ $nav('reports.index') }}"><x-icon name="chart" /> Business Overview</a>
                <a href="{{ route('reports.daily') }}" class="{{ $nav('reports.daily') }}"><x-icon name="clock" /> Daily Report</a>
                <a href="{{ route('reports.monthly') }}" class="{{ $nav('reports.monthly') }}"><x-icon name="chart" /> Monthly Report</a>
                <a href="{{ route('reports.yearly') }}" class="{{ $nav('reports.yearly') }}"><x-icon name="chart" /> Yearly Report</a>
                <a href="{{ route('reports.products') }}" class="{{ $nav('reports.products') }}"><x-icon name="pill" /> Product Sales</a>
                <a href="{{ route('reports.inventory') }}" class="{{ $nav('reports.inventory') }}"><x-icon name="currency" /> Stock Valuation</a>
                <a href="{{ route('reports.expiry') }}" class="{{ $nav('reports.expiry') }}"><x-icon name="warning" /> Expiry Report</a>
                <a href="{{ route('reports.purchases') }}" class="{{ $nav('reports.purchases') }}"><x-icon name="inbox" /> Purchase Report</a>
            @endif

            @if($user->isAdmin())
                <div class="nav-section">Administration</div>
                <a href="{{ route('users.index') }}" class="{{ $nav('users.*') }}"><x-icon name="users" /> Users</a>
                <a href="{{ route('settings.edit') }}" class="{{ $nav('settings.*') }}"><x-icon name="cog" /> Settings</a>
                <a href="{{ route('activity') }}" class="{{ $nav('activity') }}"><x-icon name="shield" /> Activity Log</a>
                <a href="{{ route('backups.index') }}" class="{{ $nav('backups.*') }}"><x-icon name="database" /> Backups</a>
            @endif
        </nav>
        <div class="border-t border-slate-800 p-3">
            <a href="{{ route('profile') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-slate-800">
                <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-sm font-semibold">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</div>
                <div class="min-w-0">
                    <div class="text-sm font-medium truncate">{{ $user->name }}</div>
                    <div class="text-xs text-slate-400">{{ $user->role_label }}</div>
                </div>
            </a>
        </div>
    </aside>
    <div class="no-print fixed inset-0 z-30 bg-black/40 lg:hidden" x-show="sidebar" x-cloak @click="sidebar=false"></div>

    {{-- Main --}}
    <div class="flex-1 min-w-0 lg:ml-64 print-full">
        <header class="no-print sticky top-0 z-20 h-16 bg-white border-b border-slate-200 flex items-center gap-4 px-4 lg:px-6">
            <button class="lg:hidden btn btn-secondary btn-sm" @click="sidebar = !sidebar"><x-icon name="menu" /></button>
            <h1 class="font-semibold text-slate-700 truncate">@yield('title', 'Dashboard')</h1>
            <div class="ml-auto flex items-center gap-2">
                <span class="hidden md:inline text-sm text-slate-500" x-data="{ now: '' }" x-init="const t = () => now = new Date().toLocaleString(undefined, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }); t(); setInterval(t, 30000)" x-text="now"></span>
                <a href="{{ route('pos') }}" class="btn btn-primary btn-sm"><x-icon name="cart" class="w-4 h-4" /> New Sale</a>
                <a href="{{ route('alerts') }}" class="relative btn btn-secondary btn-sm" title="Alerts">
                    <x-icon name="bell" class="w-4 h-4" />
                    @if($alertCount)<span class="absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-red-500 text-white text-[10px] flex items-center justify-center">{{ $alertCount > 99 ? '99+' : $alertCount }}</span>@endif
                </a>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="btn btn-secondary btn-sm" title="Log out"><x-icon name="logout" class="w-4 h-4" /><span class="hidden sm:inline">Logout</span></button>
                </form>
            </div>
        </header>

        <main class="p-4 lg:p-6 print-full">
            @if(session('success'))
                <div class="no-print mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="no-print mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="no-print mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc pl-5 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
