<aside class="sidebar panel rounded-2xl border p-4 lg:sticky lg:top-4 lg:h-[calc(100vh-2rem)]">
    <div class="flex items-center gap-3">
        <div class="h-4 w-4 rounded-full bg-gradient-to-br from-cyan-400 to-orange-500"></div>
        <div>
            <h1 class="text-lg font-bold">Service Desk</h1>
            <p class="text-xs text-slate-300">service.qwertyinnovation.com</p>
        </div>
    </div>

    <nav class="mt-4 grid gap-2">
        <a
            href="{{ route('dashboard') }}"
            class="menu-item {{ ($activeMenu ?? 'dashboard') === 'dashboard' ? 'active' : '' }} rounded-lg px-3 py-2 text-left text-sm font-bold"
        >
            Dashboard
        </a>

        @if (($currentUser ?? auth()->user())?->hasPermission(\App\Models\User::PERMISSION_MANAGE_USERS))
            <a
                href="{{ route('users.index') }}"
                class="menu-item {{ ($activeMenu ?? '') === 'users' ? 'active' : '' }} rounded-lg px-3 py-2 text-left text-sm font-bold"
            >
                User Management
            </a>
        @endif

        <button type="button" class="menu-item rounded-lg px-3 py-2 text-left text-sm font-bold">Projects</button>
        <button type="button" class="menu-item rounded-lg px-3 py-2 text-left text-sm font-bold">Service Tickets</button>
        <button type="button" class="menu-item rounded-lg px-3 py-2 text-left text-sm font-bold">Settings</button>
    </nav>

    <div class="mt-6 rounded-xl border border-white/20 bg-white/5 p-3 text-xs">
        <p class="font-bold">Logged in as</p>
        <p>{{ ($currentUser ?? auth()->user())?->name }}</p>
        <p class="mt-1">
            Role: {{ \App\Models\User::roles()[($currentUser ?? auth()->user())?->role] ?? ucfirst(($currentUser ?? auth()->user())?->role ?? '') }}
        </p>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="btn w-full rounded-lg border border-white/30 bg-white/10 px-3 py-2 text-sm font-bold text-white">
            Logout
        </button>
    </form>
</aside>
