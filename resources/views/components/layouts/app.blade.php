@props([
    'title' => null,
    'breadcrumbs' => [],
    'pos' => false,
])
@php
    $user = auth()->user();
    $context = branch_context();
    $currentBranch = $context->current();
    $openShift = $user && class_exists(\App\Models\Shift::class) ? \App\Models\Shift::query()->where('user_id', $user->id)->where('status', 'open')->first() : null;
    $unread = $user ? $user->unreadNotifications()->count() : 0;
    $has = fn ($r) => \Illuminate\Support\Facades\Route::has($r);
    $theme = $user?->theme ?? 'light';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="dp-user" content="{{ device_key() }}">
        @if ($legacyKey = legacy_device_key())<meta name="dp-legacy-user" content="{{ $legacyKey }}">@endif
        <meta name="dp-offline-ping" content="{{ route('pos.offline.ping') }}">
        <meta name="dp-offline-sync" content="{{ route('pos.offline.sync') }}">
        @if ($pos)<meta name="dp-sw" content="{{ asset('sw.js') }}">@endif
    @endauth
    <title>{{ $title ? $title.' · ' : '' }}{{ setting('business.name', config('app.name')) }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>
        (function () {
            try { var t = localStorage.getItem('dp-theme'); if (t) document.documentElement.setAttribute('data-bs-theme', t); } catch (e) {}
        })();
    </script>
    @vite(['resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body data-authenticated="1" data-scan-sound="{{ setting('pos.scan_sound', true) ? '1' : '0' }}" class="{{ $pos ? 'pos-mode' : '' }}">
<a href="#main" class="visually-hidden-focusable position-absolute top-0 start-0 p-2 bg-primary text-white z-3">{{ __('Skip to content') }}</a>
<div class="app-shell" x-data
     :class="{ 'sidebar-collapsed': $store.sidebar.collapsed || {{ $pos ? 'true' : 'false' }}, 'sidebar-open': $store.sidebar.open }">

    {{-- Sidebar --}}
    <aside class="app-sidebar" aria-label="{{ __('Main navigation') }}">
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            <span class="brand-logo"><i class="bi bi-bag-check-fill"></i></span>
            <span class="brand-text">DukaPOS<small>{{ \Illuminate\Support\Str::limit(setting('business.name'), 26) }}</small></span>
        </a>
        <nav class="sidebar-nav">
            @foreach (\App\Support\Navigation::visible() as $group)
                @if ($group['heading'])
                    <div class="nav-heading">{{ $group['heading'] }}</div>
                @endif
                @foreach ($group['items'] as $item)
                    <a href="{{ $item['url'] }}" class="nav-link {{ $item['active'] ? 'active' : '' }}" title="{{ $item['label'] }}"
                       @if ($item['active']) aria-current="page" @endif>
                        <i class="bi {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>
        <div class="sidebar-footer">DukaPOS v{{ config('dukapos.version') }}</div>
    </aside>
    <div class="sidebar-backdrop" @click="$store.sidebar.open = false"></div>

    <div class="app-main">
        {{-- Navbar --}}
        <header class="app-navbar">
            <button type="button" class="nav-icon-btn" @click="$store.sidebar.toggle()" aria-label="{{ __('Toggle sidebar') }}">
                <i class="bi bi-list"></i>
            </button>

            <div class="navbar-title min-w-0 me-auto">
                @if ($breadcrumbs)
                    <nav aria-label="breadcrumb" class="d-none d-md-block">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
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

            {{-- Global search --}}
            <div class="global-search position-relative d-none d-lg-block" x-data="globalSearch" @click.outside="open = false">
                <i class="bi bi-search search-icon"></i>
                <input type="search" class="form-control" x-ref="input" x-model="q" @input="search" @focus="results.length && (open = true)"
                       @keydown.escape="open = false" placeholder="{{ __('Search products, customers, invoices…') }}" aria-label="{{ __('Global search') }}">
                <span class="kbd-hint"><kbd>Ctrl</kbd> <kbd>K</kbd></span>
                <div class="search-results card shadow-lg" x-show="open" x-cloak x-transition>
                    <div class="list-group list-group-flush">
                        <template x-for="r in results" :key="r.url">
                            <a :href="r.url" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                                <i class="bi" :class="r.icon"></i>
                                <div class="min-w-0">
                                    <div class="fw-medium text-truncate" x-text="r.title"></div>
                                    <div class="small text-body-secondary" x-text="r.subtitle"></div>
                                </div>
                            </a>
                        </template>
                        <div class="list-group-item text-body-secondary small" x-show="!results.length">{{ __('No results') }}</div>
                    </div>
                </div>
            </div>

            {{-- Branch switcher --}}
            @if ($context->accessibleBranches()->count() > 1 || $context->canViewAll())
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-shop"></i>
                        <span class="d-none d-sm-inline">{{ $currentBranch?->name ?? __('All branches') }}</span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">{{ __('Switch branch') }}</h6></li>
                        @if ($context->canViewAll())
                            <li>
                                <form method="POST" action="{{ route('branch.switch') }}">@csrf
                                    <input type="hidden" name="branch_id" value="all">
                                    <button class="dropdown-item {{ $context->isAll() ? 'active' : '' }}"><i class="bi bi-diagram-3"></i> {{ __('All branches') }}</button>
                                </form>
                            </li>
                        @endif
                        @foreach ($context->accessibleBranches() as $b)
                            <li>
                                <form method="POST" action="{{ route('branch.switch') }}">@csrf
                                    <input type="hidden" name="branch_id" value="{{ $b->id }}">
                                    <button class="dropdown-item {{ $currentBranch?->id === $b->id ? 'active' : '' }}">
                                        <i class="bi bi-shop-window"></i> {{ $b->name }} <span class="ms-auto small text-body-secondary">{{ $b->code }}</span>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Shift status --}}
            @if ($has('shifts.current') && $user->can('shifts.open'))
                <a href="{{ route('shifts.current') }}" class="d-none d-md-inline-flex text-decoration-none">
                    @if ($openShift)
                        <span class="badge rounded-pill text-bg-success-soft status-badge">{{ __('Shift open') }}</span>
                    @else
                        <span class="badge rounded-pill text-bg-secondary-soft status-badge">{{ __('Shift closed') }}</span>
                    @endif
                </a>
            @endif

            {{-- Notifications --}}
            <div class="dropdown">
                <button type="button" class="nav-icon-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="{{ __('Notifications') }}">
                    <i class="bi bi-bell"></i>
                    @if ($unread)
                        <span class="dot bg-danger text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" style="width: 340px">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <span class="fw-semibold">{{ __('Notifications') }}</span>
                        @if ($unread)
                            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-link btn-sm p-0 text-decoration-none">{{ __('Mark all read') }}</button></form>
                        @endif
                    </div>
                    <div style="max-height: 360px; overflow-y: auto">
                        @forelse ($user->notifications()->limit(6)->get() as $n)
                            <a href="{{ route('notifications.open', $n->id) }}" class="dropdown-item d-flex gap-2 py-2 {{ $n->read_at ? '' : 'notification-item unread' }}" style="white-space: normal">
                                <i class="bi {{ $n->data['icon'] ?? 'bi-bell' }} text-{{ $n->data['color'] ?? 'primary' }} mt-1"></i>
                                <span class="min-w-0"><span class="d-block small fw-semibold">{{ $n->data['title'] ?? '' }}</span><span class="d-block small text-body-secondary text-truncate">{{ $n->data['message'] ?? '' }}</span>
                                <span class="d-block text-body-secondary" style="font-size:.7rem">{{ $n->created_at->diffForHumans() }}</span></span>
                            </a>
                        @empty
                            <div class="text-center text-body-secondary small py-4">{{ __('You are all caught up') }}</div>
                        @endforelse
                    </div>
                    <a href="{{ route('notifications.index') }}" class="d-block text-center small py-2 border-top text-decoration-none">{{ __('View all') }}</a>
                </div>
            </div>

            {{-- Language --}}
            <form method="POST" action="{{ route('preferences.locale') }}" class="d-none d-sm-block">@csrf
                <input type="hidden" name="locale" value="{{ app()->getLocale() === 'sw' ? 'en' : 'sw' }}">
                <button class="nav-icon-btn fw-semibold small" style="font-size:.78rem" title="{{ __('Language') }}" aria-label="{{ __('Switch language') }}">
                    {{ strtoupper(app()->getLocale()) }}
                </button>
            </form>

            {{-- Theme --}}
            <button type="button" class="nav-icon-btn" onclick="dpToggleTheme()" aria-label="{{ __('Toggle dark mode') }}">
                <i class="bi bi-moon-stars"></i>
            </button>

            {{-- User --}}
            <div class="dropdown">
                <button class="btn p-0 border-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('User menu') }}">
                    <x-avatar :user="$user" />
                    <span class="d-none d-xl-block text-start lh-sm">
                        <span class="d-block small fw-semibold">{{ $user->name }}</span>
                        <span class="d-block text-body-secondary" style="font-size:.72rem">{{ $user->roleLabel() }}</span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2 small">
                        <div class="fw-semibold">{{ $user->name }}</div>
                        <div class="text-body-secondary">{{ $user->email }}</div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person"></i> {{ __('My profile') }}</a></li>
                    <li><a class="dropdown-item" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> {{ __('Notifications') }}</a></li>
                    @if (tenant() && $user->can('settings.manage'))
                        <li><a class="dropdown-item" href="{{ route('billing.index') }}"><i class="bi bi-credit-card-2-front"></i> {{ __('Subscription & billing') }}</a></li>
                    @endif
                    @if (session('impersonator_id'))
                        <li>
                            <form method="POST" action="{{ route('impersonate.leave') }}">@csrf
                                <button class="dropdown-item text-warning"><i class="bi bi-person-x"></i> {{ __('Stop impersonating') }}</button>
                            </form>
                        </li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> {{ __('Sign out') }}</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        @if (session('impersonator_id'))
            <div class="alert alert-warning rounded-0 border-0 mb-0 py-2 small d-flex align-items-center gap-2">
                <i class="bi bi-incognito"></i> {{ __('You are signed in as :name.', ['name' => $user->name]) }}
                <form method="POST" action="{{ route('impersonate.leave') }}" class="ms-auto">@csrf
                    <button class="btn btn-sm btn-warning py-0">{{ __('Return to my account') }}</button>
                </form>
            </div>
        @endif

        @include('partials.platform-banners', ['user' => $user])

        <main id="main" class="app-content">
        @if ($user?->hasRole('owner') && ($envWarnings = \App\Support\Environment::warnings(request()->getHost())))
            <div class="alert alert-danger rounded-0 mb-0 small no-print" role="alert">
                <i class="bi bi-shield-exclamation"></i> <strong>{{ __('Server configuration needs attention:') }}</strong>
                {{ implode(' ', $envWarnings) }} <span class="text-body-secondary">{{ __('Then run: php artisan config:cache') }}</span>
            </div>
        @endif
            {{ $slot }}
        </main>

        <footer class="app-footer">
            <span>&copy; {{ date('Y') }} {{ setting('business.name') }}</span>
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
