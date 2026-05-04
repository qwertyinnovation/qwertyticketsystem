@php
    $resolvedCurrentUser = $currentUser ?? auth()->user();
    $resolvedActiveMenu = $activeMenu ?? 'dashboard';

    $menuItems = [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'href' => route('dashboard'),
            'enabled' => true,
        ],
        [
            'key' => 'users',
            'label' => 'User Management',
            'href' => route('users.index'),
            'enabled' => $resolvedCurrentUser?->hasPermission(\App\Models\User::PERMISSION_MANAGE_USERS) ?? false,
        ],
        [
            'key' => 'projects',
            'label' => 'Projects',
            'href' => route('projects.index'),
            'enabled' => $resolvedCurrentUser?->hasPermission(\App\Models\User::PERMISSION_MANAGE_PROJECTS) ?? false,
        ],
        [
            'key' => 'service-tickets',
            'label' => 'Service Tickets',
            'href' => route('service-tickets.index'),
            'enabled' => $resolvedCurrentUser?->hasPermission(\App\Models\User::PERMISSION_MANAGE_TICKETS) ?? false,
        ],
        [
            'key' => 'live-chat',
            'label' => 'Live Chat',
            'href' => route('project-chat.index'),
            'enabled' => $resolvedCurrentUser?->hasPermission(\App\Models\User::PERMISSION_VIEW_DASHBOARD) ?? false,
        ],
        [
            'key' => 'settings',
            'label' => 'Settings',
            'href' => route('settings.index'),
            'enabled' => $resolvedCurrentUser?->hasPermission(\App\Models\User::PERMISSION_MANAGE_SETTINGS) ?? false,
        ],
    ];

    $resolvedActiveMenuLabel = collect($menuItems)->firstWhere('key', $resolvedActiveMenu)['label'] ?? 'Menu';
@endphp

<div class="service-desk-navigation" data-service-desk-nav>
    <div class="mobile-service-desk-nav panel lg:hidden">
        <div class="mobile-service-desk-nav-bar">
            <div class="flex min-w-0 items-center gap-3">
                <img src="{{ asset('images/qwerty-logo.svg') }}" alt="Qwerty Innovation" class="h-10 w-10 shrink-0 rounded-xl bg-white object-contain p-1 shadow-sm" />
                <div class="min-w-0">
                    <p class="truncate text-base font-extrabold text-slate-900">Service Desk</p>
                    <p class="truncate text-xs text-slate-500">service.qwertyinnovation.com</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="mobile-service-desk-pill">{{ $resolvedActiveMenuLabel }}</span>
                <button
                    type="button"
                    class="mobile-service-desk-toggle"
                    data-service-desk-toggle
                    aria-expanded="false"
                    aria-controls="mobileServiceDeskDrawer"
                    aria-label="Open service desk navigation"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </div>

    <div class="mobile-service-desk-backdrop lg:hidden" data-service-desk-overlay></div>

    <aside
        id="mobileServiceDeskDrawer"
        class="mobile-service-desk-drawer sidebar panel lg:hidden"
        data-service-desk-drawer
        aria-hidden="true"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <img src="{{ asset('images/qwerty-logo.svg') }}" alt="Qwerty Innovation" class="h-11 w-11 shrink-0 rounded-xl bg-white object-contain p-1 shadow-lg shadow-black/20" />
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-bold">Service Desk</h1>
                    <p class="truncate text-xs text-slate-300">service.qwertyinnovation.com</p>
                </div>
            </div>

            <button type="button" class="mobile-service-desk-close" data-service-desk-close aria-label="Close service desk navigation">
                <span></span>
                <span></span>
            </button>
        </div>

        <div class="mt-5 rounded-xl border border-white/15 bg-white/5 p-3 text-xs">
            <p class="font-bold">Logged in as</p>
            <p class="mt-1 text-sm font-semibold">{{ $resolvedCurrentUser?->name }}</p>
            <p class="mt-1">
                Role:
                {{ \App\Models\User::roles()[$resolvedCurrentUser?->role] ?? ucfirst($resolvedCurrentUser?->role ?? '') }}
            </p>
        </div>

        <nav class="mt-5 grid gap-2">
            @foreach ($menuItems as $item)
                @if ($item['enabled'])
                    <a
                        href="{{ $item['href'] }}"
                        data-service-desk-link
                        class="menu-item {{ $resolvedActiveMenu === $item['key'] ? 'active' : '' }} rounded-lg px-3 py-2.5 text-left text-sm font-bold"
                    >
                        {{ $item['label'] }}
                    </a>
                @else
                    <button type="button" disabled class="menu-item rounded-lg px-3 py-2.5 text-left text-sm font-bold opacity-70">
                        {{ $item['label'] }}
                    </button>
                @endif
            @endforeach
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="mt-5">
            @csrf
            <button type="submit" class="btn w-full rounded-lg border border-white/30 bg-white/10 px-3 py-2.5 text-sm font-bold text-white">
                Logout
            </button>
        </form>
    </aside>

    <aside class="sidebar panel hidden rounded-2xl border p-4 lg:sticky lg:top-4 lg:block lg:h-[calc(100vh-2rem)]">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/qwerty-logo.svg') }}" alt="Qwerty Innovation" class="h-12 w-12 shrink-0 rounded-xl bg-white object-contain p-1.5 shadow-lg shadow-black/20" />
            <div>
                <h1 class="text-lg font-bold">Service Desk</h1>
                <p class="text-xs text-slate-300">service.qwertyinnovation.com</p>
            </div>
        </div>

        <nav class="mt-4 grid gap-2">
            @foreach ($menuItems as $item)
                @if ($item['enabled'])
                    <a
                        href="{{ $item['href'] }}"
                        class="menu-item {{ $resolvedActiveMenu === $item['key'] ? 'active' : '' }} rounded-lg px-3 py-2 text-left text-sm font-bold"
                    >
                        {{ $item['label'] }}
                    </a>
                @else
                    <button type="button" disabled class="menu-item rounded-lg px-3 py-2 text-left text-sm font-bold opacity-70">
                        {{ $item['label'] }}
                    </button>
                @endif
            @endforeach
        </nav>

        <div class="mt-6 rounded-xl border border-white/20 bg-white/5 p-3 text-xs">
            <p class="font-bold">Logged in as</p>
            <p>{{ $resolvedCurrentUser?->name }}</p>
            <p class="mt-1">
                Role: {{ \App\Models\User::roles()[$resolvedCurrentUser?->role] ?? ucfirst($resolvedCurrentUser?->role ?? '') }}
            </p>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="btn w-full rounded-lg border border-white/30 bg-white/10 px-3 py-2 text-sm font-bold text-white">
                Logout
            </button>
        </form>
    </aside>
</div>
