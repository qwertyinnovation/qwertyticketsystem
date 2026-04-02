<x-layouts.app :title="'User Management | Qwerty Ticket System'">
    <div class="app-shell app-shell-users">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'users', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <header class="panel rounded-2xl border p-4">
                <h1 class="text-2xl font-extrabold tracking-tight">User Management</h1>
                <p class="mt-1 text-sm text-slate-600">Table view for users. Click a row action to load details in the bottom editor panel.</p>
            </header>

            <article class="panel rounded-2xl border bg-white p-4">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-lg font-bold">Users Table</h2>
                    <span class="badge rounded-full px-2 py-1 text-xs">Total: {{ $users->count() }}</span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full border-collapse text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-3 py-2 font-bold text-slate-700">Name</th>
                                <th class="px-3 py-2 font-bold text-slate-700">Email</th>
                                <th class="px-3 py-2 font-bold text-slate-700">Role</th>
                                <th class="px-3 py-2 font-bold text-slate-700">Permissions</th>
                                <th class="px-3 py-2 font-bold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $managedUser)
                                @php
                                    $isSelected = $selectedUser && $selectedUser->id === $managedUser->id;
                                @endphp
                                <tr class="border-t border-slate-100 {{ $isSelected ? 'bg-cyan-50/70' : 'bg-white' }}">
                                    <td class="px-3 py-2 font-semibold">{{ $managedUser->name }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $managedUser->email }}</td>
                                    <td class="px-3 py-2">
                                        <span class="badge rounded-full px-2 py-1 text-xs">{{ $roles[$managedUser->role] ?? ucfirst($managedUser->role) }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{ $managedUser->role === \App\Models\User::ROLE_ADMIN ? 'All permissions' : count($managedUser->permissions ?? []) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <a
                                            href="{{ route('users.index', ['selected' => $managedUser->id]) }}"
                                            class="btn inline-flex rounded-lg border border-slate-300 bg-slate-50 px-2 py-1 text-xs font-bold text-slate-700"
                                        >
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-slate-500">No users found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            @if ($selectedUser)
                <article class="panel rounded-2xl border bg-white p-4">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h3 class="text-lg font-extrabold">Details: {{ $selectedUser->name }}</h3>
                            <p class="text-sm text-slate-600">Bottom detail editor for the selected table row.</p>
                        </div>
                        <span class="badge rounded-full px-2 py-1 text-xs">ID #{{ $selectedUser->id }}</span>
                    </div>

                    <form method="POST" action="{{ route('users.update', $selectedUser) }}" class="grid gap-3">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="grid gap-1 text-sm font-semibold">
                                Name
                                <input type="text" name="name" value="{{ $selectedUser->name }}" class="rounded-lg px-3 py-2 text-sm" required />
                            </label>

                            <label class="grid gap-1 text-sm font-semibold">
                                Email
                                <input type="email" name="email" value="{{ $selectedUser->email }}" class="rounded-lg px-3 py-2 text-sm" required />
                            </label>

                            <label class="grid gap-1 text-sm font-semibold">
                                New Password (optional)
                                <input type="password" name="password" class="rounded-lg px-3 py-2 text-sm" />
                            </label>

                            <label class="grid gap-1 text-sm font-semibold">
                                Role
                                <select name="role" class="rounded-lg px-3 py-2 text-sm" required>
                                    @foreach ($roles as $roleKey => $roleName)
                                        <option value="{{ $roleKey }}" @selected($selectedUser->role === $roleKey)>{{ $roleName }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="grid gap-2">
                            <p class="text-sm font-semibold">Permissions</p>
                            @foreach ($permissions as $permissionKey => $permissionName)
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permissionKey }}"
                                        class="rounded"
                                        @checked(in_array($permissionKey, $selectedUser->permissions ?? [], true) || $selectedUser->role === \App\Models\User::ROLE_ADMIN)
                                    />
                                    {{ $permissionName }}
                                </label>
                            @endforeach
                        </div>

                        <button type="submit" class="btn btn-primary rounded-lg px-3 py-2 text-sm font-bold text-white">Save Changes</button>
                    </form>

                    <form method="POST" action="{{ route('users.destroy', $selectedUser) }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="btn rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-bold text-red-700"
                            onclick="return confirm('Delete {{ $selectedUser->name }}?');"
                            @disabled((int) $selectedUser->id === (int) $currentUser->id)
                        >
                            Delete
                        </button>
                    </form>
                </article>
            @endif
        </section>

        <section class="panel user-create-panel rounded-2xl border p-4">
            <h2 class="text-xl font-extrabold">Create User</h2>
            <p class="mt-1 text-sm text-slate-600">Add users with role and explicit permission assignment.</p>

            <form method="POST" action="{{ route('users.store') }}" class="mt-3 grid gap-3">
                @csrf

                <label class="grid gap-1 text-sm font-semibold">
                    Name
                    <input type="text" name="name" class="rounded-lg px-3 py-2 text-sm" required />
                </label>

                <label class="grid gap-1 text-sm font-semibold">
                    Email
                    <input type="email" name="email" class="rounded-lg px-3 py-2 text-sm" required />
                </label>

                <label class="grid gap-1 text-sm font-semibold">
                    Password
                    <input type="password" name="password" class="rounded-lg px-3 py-2 text-sm" required />
                </label>

                <label class="grid gap-1 text-sm font-semibold">
                    Role
                    <select name="role" class="rounded-lg px-3 py-2 text-sm" required>
                        @foreach ($roles as $roleKey => $roleName)
                            <option value="{{ $roleKey }}">{{ $roleName }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="grid gap-2">
                    <p class="text-sm font-semibold">Permissions</p>
                    @foreach ($permissions as $permissionKey => $permissionName)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="{{ $permissionKey }}" class="rounded" />
                            {{ $permissionName }}
                        </label>
                    @endforeach
                </div>

                <button type="submit" class="btn btn-primary rounded-lg px-3 py-2 text-sm font-bold text-white">Create User</button>
            </form>

            <a href="{{ route('dashboard') }}" class="mt-4 inline-block text-sm font-bold text-cyan-700">Back to Dashboard</a>
        </section>
    </div>
</x-layouts.app>
