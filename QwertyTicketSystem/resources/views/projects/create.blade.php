<x-layouts.app :title="'Create Project | Qwerty Ticket System'">
    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'projects', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <article class="panel rounded-2xl border bg-white p-4">
                <h1 class="text-2xl font-extrabold tracking-tight">Create Project</h1>
                <p class="mt-1 text-sm text-slate-600">Add a new project for ticket tracking and operations.</p>

                <form method="POST" action="{{ route('projects.store') }}" class="mt-4 grid gap-3">
                    @csrf

                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-semibold">
                            Name
                            <input type="text" name="name" value="{{ old('name') }}" class="rounded-lg px-3 py-2 text-sm" required />
                            @error('name')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold">
                            Category
                            <select name="category" class="rounded-lg px-3 py-2 text-sm" required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                            @error('category')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold">
                            Service Type
                            <select name="service_type" class="rounded-lg px-3 py-2 text-sm" required>
                                @foreach ($serviceTypes as $serviceType)
                                    <option value="{{ $serviceType }}" @selected(old('service_type') === $serviceType)>{{ $serviceType }}</option>
                                @endforeach
                            </select>
                            @error('service_type')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold">
                            Priority
                            <select name="priority" class="rounded-lg px-3 py-2 text-sm" required>
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority }}" @selected(old('priority') === $priority)>{{ $priority }}</option>
                                @endforeach
                            </select>
                            @error('priority')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold md:col-span-2">
                            Status
                            <select name="status" class="rounded-lg px-3 py-2 text-sm" required>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(old('status') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold md:col-span-2">
                            Description
                            <textarea name="description" rows="4" class="rounded-lg px-3 py-2 text-sm">{{ old('description') }}</textarea>
                            @error('description')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold md:col-span-2">
                            Assigned Users (Internal / Third-Party / Client)
                            @php
                                $selectedAssigneeIds = collect(old('assignee_user_ids', []))
                                    ->map(static fn ($value): int => (int) $value)
                                    ->all();
                            @endphp
                            <select name="assignee_user_ids[]" class="rounded-lg px-3 py-2 text-sm" multiple size="10">
                                @foreach ($assignableUsersByRole as $role => $users)
                                    @if ($users !== [])
                                        <optgroup label="{{ $assignableRoleLabels[$role] ?? ucfirst($role) }}">
                                            @foreach ($users as $user)
                                                <option value="{{ $user->id }}" @selected(in_array((int) $user->id, $selectedAssigneeIds, true))>
                                                    {{ $user->name }} ({{ $user->email }})
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
                            <span class="text-xs text-slate-500">Selected users can only access tickets from this project.</span>
                            @error('assignee_user_ids')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                            @error('assignee_user_ids.*')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary rounded-lg px-3 py-2 text-sm font-bold text-white">Create Project</button>
                        <a href="{{ route('projects.index') }}" class="btn rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700">Back to Projects</a>
                    </div>
                </form>
            </article>
        </section>
    </div>
</x-layouts.app>
