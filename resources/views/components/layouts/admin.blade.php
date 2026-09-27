@props([
    'title' => null,
    'breadcrumbs' => [],
])
@php
    $admin = auth('admin')->user();
    $platform = \App\Support\PlatformSettings::get('name', 'DukaPOS');
    $nav = [
        [null, [
            [__('Dashboard'), 'bi-speedometer2', 'admin.dashboard', 'admin.dashboard', false],
        ]],
        [__('Customers'), [
            [__('Businesses'), 'bi-shop', 'admin.tenants.index', 'admin.tenants.*', false],
            [__('Users'), 'bi-people', 'admin.users.index', 'admin.users.*', false],
        ]],
        [__('Billing'), [
            [__('Payments'), 'bi-cash-coin', 'admin.payments.index', 'admin.payments.*', false],
            [__('Plans'), 'bi-stars', 'admin.plans.index', 'admin.plans.*', false],
        ]],
        [__('Platform'), [
            [__('Announcements'), 'bi-megaphone', 'admin.announcements.index', 'admin.announcements.*', false],
            [__('Activity log'), 'bi-clock-history', 'admin.activity.index', 'admin.activity.*', false],
            [__('Admins'), 'bi-person-gear', 'admin.admins.index', 'admin.admins.*', true],
            [__('Settings'), 'bi-gear', 'admin.settings.edit', 'admin.settings.*', true],
        ]],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $title ? $title.' · ' : '' }}{{ $platform }} {{ __('Admin') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>(function(){try{var t=localStorage.getItem('dp-theme');if(t)document.documentElement.setAttribute('data-bs-theme',t);}catch(e){}})();</script>
    @vite(['resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body data-authenticated="1" class="admin-area">
<a href="#main" class="visually-hidden-focusable position-absolute top-0 start-0 p-2 bg-primary text-white z-3">{{ __('Skip to content') }}</a>
<div class="app-shell" x-data :class="{ 'sidebar-collapsed': $store.sidebar.collapsed, 'sidebar-open': $store.sidebar.open }">
    <aside class="app-sidebar admin-sidebar" aria-label="{{ __('Admin navigation') }}">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
            <span class="brand-logo"><i class="bi bi-shield-lock-fill"></i></span>
            <span class="brand-text">{{ $platform }}<small>{{ __('Platform admin') }}</small></span>
        </a>
        <nav class="sidebar-nav">
            @foreach ($nav as [$heading, $items])
                @php $items = array_filter($items, fn ($i) => ! $i[4] || $admin?->is_super); @endphp
                @if ($items)
                    @if ($heading)<div class="nav-heading">{{ $heading }}</div>@endif
                    @foreach ($items as [$label, $icon, $route, $active])
                        @php $isActive = request()->routeIs($active); @endphp
                        <a href="{{ route($route) }}" class="nav-link {{ $isActive ? 'active' : '' }}" title="{{ $label }}" @if ($isActive) aria-current="page" @endif>
                            <i class="bi {{ $icon }}"></i><span>{{ $label }}</span>
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>
        <div class="sidebar-footer">DukaPOS v{{ config('dukapos.version') }}</div>
    </aside>
    <div class="sidebar-backdrop" @click="$store.sidebar.open = false"></div>

    <div class="app-main">
        <header class="app-navbar">
            <button type="button" class="nav-icon-btn" @click="$store.sidebar.toggle()" aria-label="{{ __('Toggle sidebar') }}"><i class="bi bi-list"></i></button>
            <div class="navbar-title min-w-0 me-auto">
                @if ($breadcrumbs)
                    <nav aria-label="breadcrumb" class="d-none d-md-block">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Admin') }}</a></li>
                            @foreach ($breadcrumbs as $label => $url)
                                @if (is_int($label))
                                    <li class="breadcrumb-item active" aria-current="page">{{ $url }}</li>
                                @else
                                    <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                                @endif
                            @endforeach
                        </ol>
                    </nav>
                @endif
                <h1>{{ $title }}</h1>
            </div>

            <form method="GET" action="{{ route('admin.tenants.index') }}" class="d-none d-lg-block global-search position-relative" role="search">
                <i class="bi bi-search search-icon"></i>
                <input type="search" name="q" class="form-control" placeholder="{{ __('Find a business, owner or phone…') }}" aria-label="{{ __('Search businesses') }}">
            </form>

            <form method="POST" action="{{ route('preferences.locale') }}" class="d-none d-sm-block">@csrf
                <input type="hidden" name="locale" value="{{ app()->getLocale() === 'sw' ? 'en' : 'sw' }}">
                <button class="nav-icon-btn fw-semibold small" style="font-size:.78rem" aria-label="{{ __('Switch language') }}">{{ strtoupper(app()->getLocale()) }}</button>
            </form>
            <button type="button" class="nav-icon-btn" onclick="dpToggleTheme()" aria-label="{{ __('Toggle dark mode') }}"><i class="bi bi-moon-stars"></i></button>

            <div class="dropdown">
                <button class="btn p-0 border-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Admin menu') }}">
                    <span class="avatar avatar-sm bg-primary-soft text-primary fw-semibold rounded-circle d-grid" style="width:34px;height:34px;place-items:center">{{ strtoupper(mb_substr($admin->name, 0, 1)) }}</span>
                    <span class="d-none d-xl-block text-start lh-sm">
                        <span class="d-block small fw-semibold">{{ $admin->name }}</span>
                        <span class="d-block text-body-secondary" style="font-size:.72rem">{{ $admin->is_super ? __('Super admin') : __('Support admin') }}</span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2 small"><div class="fw-semibold">{{ $admin->name }}</div><div class="text-body-secondary">{{ $admin->email }}</div></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('admin.logout') }}">@csrf
                            <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> {{ __('Sign out') }}</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main id="main" class="app-content">
            {{ $slot }}
        </main>

        <footer class="app-footer">
            <span>&copy; {{ date('Y') }} {{ $platform }}</span>
            <span>DukaPOS v{{ config('dukapos.version') }}</span>
        </footer>
    </div>
</div>

@include('partials.flash')
<x-confirm-modal />
@stack('modals')
@stack('scripts')
@livewireScripts
</body>
</html>
