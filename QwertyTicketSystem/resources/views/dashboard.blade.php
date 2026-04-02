<x-layouts.app :title="'Dashboard | Qwerty Ticket System'">
    <div id="appShell" class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'dashboard', 'currentUser' => $user])

        <main class="grid gap-4">
            @include('partials.alerts')

            <header class="panel rounded-2xl border p-4">
                <h2 class="text-2xl font-extrabold tracking-tight">Dashboard</h2>
                <p class="mt-1 text-sm text-slate-600">Service ticket workspace for projects and ticket workflows.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($permissions as $permissionKey => $permissionLabel)
                        @if ($user->hasPermission($permissionKey))
                            <span class="badge rounded-full px-2 py-1 text-xs">{{ $permissionLabel }}</span>
                        @endif
                    @endforeach
                </div>
            </header>

            <section class="panel hero rounded-2xl border p-4">
                <h3 class="text-xl font-extrabold">Ticket Operations Center</h3>
                <p class="mt-1 text-sm text-slate-600">This is your first Laravel 12 + Tailwind page using your provided
                    UI direction.</p>
                @if ($user->hasPermission(\App\Models\User::PERMISSION_MANAGE_USERS))
                    <a href="{{ route('users.index') }}"
                        class="btn btn-primary mt-3 inline-flex rounded-lg px-3 py-2 text-sm font-bold text-white">Configure
                        User Access</a>
                @endif
            </section>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article class="panel metric rounded-2xl border bg-white p-4">
                    <h4 class="text-xs font-bold uppercase tracking-[0.04em] text-slate-500">Total Users</h4>
                    <p class="mt-2 text-3xl font-extrabold">{{ $totalUsers }}</p>
                </article>
                <article class="panel metric rounded-2xl border bg-white p-4">
                    <h4 class="text-xs font-bold uppercase tracking-[0.04em] text-slate-500">Admins</h4>
                    <p class="mt-2 text-3xl font-extrabold">{{ $adminUsers }}</p>
                </article>
                <article class="panel metric rounded-2xl border bg-white p-4">
                    <h4 class="text-xs font-bold uppercase tracking-[0.04em] text-slate-500">Project Managers</h4>
                    <p class="mt-2 text-3xl font-extrabold">{{ $pmUsers }}</p>
                </article>
                <article class="panel metric rounded-2xl border bg-white p-4">
                    <h4 class="text-xs font-bold uppercase tracking-[0.04em] text-slate-500">Clients</h4>
                    <p class="mt-2 text-3xl font-extrabold">{{ $clientUsers }}</p>
                </article>
            </div>
        </main>
    </div>
</x-layouts.app>
