@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ tenant() ? setting('business.name', 'DukaPOS') : \App\Support\PlatformSettings::get('name', 'DukaPOS') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>(function(){try{var t=localStorage.getItem('dp-theme');if(t)document.documentElement.setAttribute('data-bs-theme',t);}catch(e){}})();</script>
    @vite(['resources/js/app.js'])
    @livewireStyles
</head>
<body data-authenticated="0">
<div class="auth-split">
    <section class="auth-brand">
        <div class="d-flex align-items-center gap-2">
            <span class="rounded-3 d-grid bg-white bg-opacity-10" style="width:44px;height:44px;place-items:center;font-size:1.3rem"><i class="bi bi-bag-check-fill"></i></span>
            <div>
                <div class="fw-bold fs-5 lh-1">DukaPOS</div>
                <div class="small opacity-75">{{ __('Point of Sale & Inventory') }}</div>
            </div>
        </div>

        <svg class="auth-illustration" viewBox="0 0 440 320" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs>
                <linearGradient id="scr" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#EEF2FF"/><stop offset="1" stop-color="#C7D2FE"/></linearGradient>
                <linearGradient id="bar" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#4F46E5"/><stop offset="1" stop-color="#818CF8"/></linearGradient>
            </defs>
            <rect x="40" y="30" width="300" height="200" rx="18" fill="#fff" opacity=".96"/>
            <rect x="56" y="48" width="268" height="164" rx="10" fill="url(#scr)"/>
            <rect x="72" y="64" width="110" height="12" rx="6" fill="#4F46E5" opacity=".85"/>
            <rect x="72" y="84" width="70" height="8" rx="4" fill="#A5B4FC"/>
            <rect x="80" y="150" width="22" height="46" rx="5" fill="url(#bar)"/>
            <rect x="112" y="128" width="22" height="68" rx="5" fill="url(#bar)"/>
            <rect x="144" y="140" width="22" height="56" rx="5" fill="url(#bar)"/>
            <rect x="176" y="110" width="22" height="86" rx="5" fill="url(#bar)"/>
            <rect x="208" y="120" width="22" height="76" rx="5" fill="url(#bar)"/>
            <circle cx="282" cy="130" r="30" fill="none" stroke="#4F46E5" stroke-width="12" stroke-dasharray="120 200" transform="rotate(-90 282 130)"/>
            <circle cx="282" cy="130" r="30" fill="none" stroke="#0EA5E9" stroke-width="12" stroke-dasharray="60 200" stroke-dashoffset="-120" transform="rotate(-90 282 130)"/>
            <rect x="170" y="230" width="40" height="30" fill="#fff" opacity=".9"/>
            <rect x="130" y="258" width="120" height="12" rx="6" fill="#fff" opacity=".9"/>
            <g transform="translate(300 170)">
                <rect width="110" height="130" rx="14" fill="#fff"/>
                <rect x="12" y="14" width="86" height="8" rx="4" fill="#E5E7EB"/>
                <rect x="12" y="30" width="60" height="8" rx="4" fill="#E5E7EB"/>
                <rect x="12" y="46" width="72" height="8" rx="4" fill="#E5E7EB"/>
                <line x1="12" y1="66" x2="98" y2="66" stroke="#CBD5E1" stroke-dasharray="4 3"/>
                <rect x="12" y="76" width="40" height="10" rx="5" fill="#111827"/>
                <rect x="60" y="76" width="38" height="10" rx="5" fill="#16A34A"/>
                <g fill="#111827"><rect x="18" y="98" width="3" height="20"/><rect x="24" y="98" width="1.5" height="20"/><rect x="28" y="98" width="4" height="20"/><rect x="35" y="98" width="2" height="20"/><rect x="40" y="98" width="3" height="20"/><rect x="46" y="98" width="1.5" height="20"/><rect x="50" y="98" width="4" height="20"/><rect x="57" y="98" width="2" height="20"/><rect x="62" y="98" width="3" height="20"/><rect x="68" y="98" width="1.5" height="20"/><rect x="72" y="98" width="4" height="20"/><rect x="79" y="98" width="2" height="20"/><rect x="84" y="98" width="3" height="20"/></g>
            </g>
            <circle cx="60" cy="260" r="26" fill="#16A34A"/>
            <path d="m48 260 8 8 16-16" stroke="#fff" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        <div>
            <h2 class="fw-bold mb-3 text-white" style="letter-spacing:-.02em">{{ __('Run every branch of your shop from one place.') }}</h2>
            <div class="auth-feature"><i class="bi bi-lightning-charge-fill"></i><div><div class="fw-semibold">{{ __('Sell in seconds') }}</div><div class="small opacity-75">{{ __('Barcode scanner, keyboard shortcuts and split payments with M-Pesa, Tigo Pesa & more.') }}</div></div></div>
            <div class="auth-feature"><i class="bi bi-box-seam-fill"></i><div><div class="fw-semibold">{{ __('Accurate stock, always') }}</div><div class="small opacity-75">{{ __('Every movement is recorded — transfers, stock takes, batches and expiry.') }}</div></div></div>
            <div class="auth-feature"><i class="bi bi-graph-up-arrow"></i><div><div class="fw-semibold">{{ __('Know your numbers') }}</div><div class="small opacity-75">{{ __('Sales, profit, debts and VAT reports on your phone or desktop.') }}</div></div></div>
        </div>
    </section>

    <section class="auth-form-wrap">
        <div class="auth-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex d-lg-none align-items-center gap-2">
                    <span class="brand-logo rounded-3 d-grid text-white" style="width:40px;height:40px;place-items:center;background:linear-gradient(135deg,#6366F1,#4338CA)"><i class="bi bi-bag-check-fill"></i></span>
                    <span class="fw-bold fs-5">DukaPOS</span>
                </div>
                <div class="ms-auto d-flex gap-1">
                    <form method="POST" action="{{ route('preferences.locale') }}">@csrf
                        <input type="hidden" name="locale" value="en">
                        <button class="btn btn-sm {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-light' }}">EN</button>
                    </form>
                    <form method="POST" action="{{ route('preferences.locale') }}">@csrf
                        <input type="hidden" name="locale" value="sw">
                        <button class="btn btn-sm {{ app()->getLocale() === 'sw' ? 'btn-primary' : 'btn-light' }}">SW</button>
                    </form>
                    <button type="button" class="btn btn-sm btn-light" onclick="dpToggleTheme()" aria-label="{{ __('Toggle dark mode') }}"><i class="bi bi-moon-stars"></i></button>
                </div>
            </div>
            {{ $slot }}
            <p class="text-center small text-body-secondary mt-5 mb-0">&copy; {{ date('Y') }} {{ tenant() ? setting('business.name') : \App\Support\PlatformSettings::get('name', 'DukaPOS') }} · DukaPOS v{{ config('dukapos.version') }}</p>
        </div>
    </section>
</div>
@include('partials.flash')
@livewireScripts
</body>
</html>
