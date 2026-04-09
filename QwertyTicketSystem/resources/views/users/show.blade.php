<x-layouts.app :title="'User Details | Qwerty Ticket System'">
    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'users', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <article class="panel rounded-2xl border bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight">{{ $managedUser->name }}</h1>
                        <p class="mt-1 text-sm text-slate-600">User details page.</p>
                    </div>
                    <span class="badge rounded-full px-2 py-1 text-xs">ID #{{ $managedUser->id }}</span>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-bold uppercase text-slate-500">Email</p>
                        <p class="mt-1 text-sm font-semibold">{{ $managedUser->email }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-bold uppercase text-slate-500">Role</p>
                        <p class="mt-1 text-sm font-semibold">{{ $roles[$managedUser->role] ?? ucfirst($managedUser->role) }}</p>
                    </div>
                </div>

                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-bold uppercase text-slate-500">Permissions</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if ($managedUser->role === \App\Models\User::ROLE_ADMIN)
                            <span class="badge rounded-full px-2 py-1 text-xs">All permissions</span>
                        @else
                            @forelse ($managedUser->permissions ?? [] as $permission)
                                <span class="badge rounded-full px-2 py-1 text-xs">{{ $permissions[$permission] ?? $permission }}</span>
                            @empty
                                <span class="text-sm text-slate-600">No permissions assigned.</span>
                            @endforelse
                        @endif
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('users.edit', $managedUser) }}" class="btn btn-primary rounded-lg px-3 py-2 text-sm font-bold text-white">Edit User</a>
                    <a href="{{ route('users.index') }}" class="btn rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700">Back to Users</a>
                </div>
            </article>
        </section>
    </div>
</x-layouts.app>
