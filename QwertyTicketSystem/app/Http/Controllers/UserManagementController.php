<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $roles = User::roles();
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'role' => (string) $request->query('role', ''),
        ];

        $usersQuery = User::query()->orderBy('name');

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $usersQuery->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($filters['role'] !== '' && array_key_exists($filters['role'], $roles)) {
            $usersQuery->where('role', $filters['role']);
        }

        return view('users.index', [
            'currentUser' => $request->user(),
            'users' => $usersQuery->paginate(10)->withQueryString(),
            'roles' => $roles,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        return view('users.create', [
            'currentUser' => $request->user(),
            'roles' => User::roles(),
            'permissions' => User::availablePermissions(),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        return view('users.show', [
            'currentUser' => $request->user(),
            'managedUser' => $user,
            'roles' => User::roles(),
            'permissions' => User::availablePermissions(),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        return view('users.edit', [
            'currentUser' => $request->user(),
            'managedUser' => $user,
            'roles' => User::roles(),
            'permissions' => User::availablePermissions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(array_keys(User::roles()))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(User::availablePermissions()))],
        ]);

        $permissions = $validated['permissions'] ?? User::defaultPermissionsForRole($validated['role']);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'permissions' => $permissions,
        ]);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in(array_keys(User::roles()))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(User::availablePermissions()))],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->permissions = $validated['permissions'] ?? [];

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()->route('users.show', $user)->with('status', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ((int) auth()->id() === (int) $user->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return back()->with('status', 'User deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $selectedIds = array_map('intval', $validated['selected_ids']);

        if (in_array((int) $request->user()->id, $selectedIds, true)) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $deletedCount = User::query()
            ->whereIn('id', $selectedIds)
            ->delete();

        return back()->with('status', $deletedCount.' '.Str::plural('user', $deletedCount).' deleted.');
    }
}
