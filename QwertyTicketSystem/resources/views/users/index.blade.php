<x-layouts.app :title="'User Management | Qwerty Ticket System'">
    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'users', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <header class="panel rounded-2xl border p-4">
                <h1 class="text-2xl font-extrabold tracking-tight">User Management</h1>
                <p class="mt-1 text-sm text-slate-600">Users list with dedicated details and edit pages.</p>
            </header>

            <article class="panel rounded-2xl border bg-white p-4">
                <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-bold">Users Table</h2>
                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                        <span class="badge rounded-full px-2 py-1 text-xs">Total: {{ $users->total() }}</span>
                        <a href="{{ route('users.create') }}" class="btn btn-primary rounded-lg px-3 py-2 text-center text-xs font-bold text-white sm:w-auto">Create User</a>
                    </div>
                </div>

                <form method="GET" action="{{ route('users.index') }}" class="mb-3 grid gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-4">
                    <label class="grid gap-1 text-xs font-semibold md:col-span-2">
                        Search
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Search by name or email"
                            class="rounded-lg px-3 py-2 text-sm"
                        />
                    </label>

                    <label class="grid gap-1 text-xs font-semibold">
                        Role
                        <select name="role" class="rounded-lg px-3 py-2 text-sm">
                            <option value="">All roles</option>
                            @foreach ($roles as $roleKey => $roleName)
                                <option value="{{ $roleKey }}" @selected(($filters['role'] ?? '') === $roleKey)>{{ $roleName }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="btn btn-primary rounded-lg px-3 py-2 text-sm font-bold text-white">Apply</button>
                        <a href="{{ route('users.index') }}" class="btn rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700">Clear</a>
                    </div>
                </form>

                <form method="POST" action="{{ route('users.bulk-destroy') }}" data-bulk-delete-form data-bulk-confirm="Delete selected users?">
                    @csrf
                    @method('DELETE')

                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <span class="text-xs font-bold uppercase tracking-[0.06em] text-slate-500" data-bulk-selected-count>0 selected</span>
                        <button type="submit" class="btn rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700 disabled:cursor-not-allowed disabled:opacity-50" data-bulk-submit disabled>
                            Delete Selected
                        </button>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full border-collapse text-sm">
                            <thead class="bg-slate-50 text-left">
                                <tr>
                                    <th class="w-12 px-3 py-2">
                                        <input type="checkbox" class="rounded" data-bulk-select-all aria-label="Select all users on this page" />
                                    </th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Name</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Email</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Role</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Permissions</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $managedUser)
                                    <tr class="border-t border-slate-100 bg-white">
                                        <td class="px-3 py-2">
                                            <input
                                                type="checkbox"
                                                name="selected_ids[]"
                                                value="{{ $managedUser->id }}"
                                                class="rounded"
                                                data-bulk-select-row
                                                aria-label="Select {{ $managedUser->name }}"
                                                @disabled((int) $currentUser->id === (int) $managedUser->id)
                                            />
                                        </td>
                                        <td class="px-3 py-2 font-semibold">{{ $managedUser->name }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $managedUser->email }}</td>
                                        <td class="px-3 py-2">
                                            <span class="badge rounded-full px-2 py-1 text-xs">{{ $roles[$managedUser->role] ?? ucfirst($managedUser->role) }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{ $managedUser->role === \App\Models\User::ROLE_ADMIN ? 'All permissions' : count($managedUser->permissions ?? []) }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-wrap gap-2">
                                                <a href="{{ route('users.show', $managedUser) }}" class="btn inline-flex rounded-lg border border-slate-300 bg-slate-50 px-2 py-1 text-xs font-bold text-slate-700">View Details</a>
                                                <a href="{{ route('users.edit', $managedUser) }}" class="btn inline-flex rounded-lg border border-cyan-300 bg-cyan-50 px-2 py-1 text-xs font-bold text-cyan-700">Edit</a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">No users found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>

                <div class="mt-3">
                    {{ $users->links() }}
                </div>
            </article>
        </section>
    </div>
</x-layouts.app>
