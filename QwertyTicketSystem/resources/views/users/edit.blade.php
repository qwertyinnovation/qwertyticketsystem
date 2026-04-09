<x-layouts.app :title="'Edit User | Qwerty Ticket System'">
    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'users', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <article class="panel rounded-2xl border bg-white p-4">
                <h1 class="text-2xl font-extrabold tracking-tight">Edit User</h1>
                <p class="mt-1 text-sm text-slate-600">Update user role and permissions.</p>

                <form method="POST" action="{{ route('users.update', $managedUser) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    @method('PUT')

                    <label class="grid gap-1 text-sm font-semibold">
                        Name
                        <input type="text" name="name" value="{{ old('name', $managedUser->name) }}" class="rounded-lg px-3 py-2 text-sm" required />
                        @error('name')
                            <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Email
                        <input type="email" name="email" value="{{ old('email', $managedUser->email) }}" class="rounded-lg px-3 py-2 text-sm" required />
                        @error('email')
                            <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        New Password (optional)
                        <input type="password" name="password" class="rounded-lg px-3 py-2 text-sm" />
                        @error('password')
                            <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Role
                        <select name="role" class="rounded-lg px-3 py-2 text-sm" required>
                            @foreach ($roles as $roleKey => $roleName)
                                <option value="{{ $roleKey }}" @selected(old('role', $managedUser->role) === $roleKey)>{{ $roleName }}</option>
                            @endforeach
                        </select>
                        @error('role')
                            <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <div class="grid gap-2 md:col-span-2">
                        <p class="text-sm font-semibold">Permissions</p>
                        @foreach ($permissions as $permissionKey => $permissionName)
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $permissionKey }}"
                                    class="rounded"
                                    @checked(in_array($permissionKey, old('permissions', $managedUser->permissions ?? []), true) || old('role', $managedUser->role) === \App\Models\User::ROLE_ADMIN)
                                />
                                {{ $permissionName }}
                            </label>
                        @endforeach
                        @error('permissions')
                            <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="md:col-span-2 flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary rounded-lg px-3 py-2 text-sm font-bold text-white">Save Changes</button>
                        <a href="{{ route('users.index') }}" class="btn rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700">Cancel</a>
                    </div>
                </form>

                <form method="POST" action="{{ route('users.destroy', $managedUser) }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="btn rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-bold text-red-700"
                        onclick="return confirm('Delete {{ $managedUser->name }}?');"
                        @disabled((int) $managedUser->id === (int) $currentUser->id)
                    >
                        Delete User
                    </button>
                </form>
            </article>
        </section>
    </div>
</x-layouts.app>
