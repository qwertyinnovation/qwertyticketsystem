<x-layouts.app :title="'Project Details | Qwerty Ticket System'">
    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'projects', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <article class="panel rounded-2xl border bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight">{{ $project->name }}</h1>
                        <p class="mt-1 text-sm text-slate-600">Project details page.</p>
                    </div>
                    <span class="badge rounded-full px-2 py-1 text-xs">ID #{{ $project->id }}</span>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-bold uppercase text-slate-500">Category</p>
                        <p class="mt-1 text-sm font-semibold">{{ $project->category }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-bold uppercase text-slate-500">Service Type</p>
                        <p class="mt-1 text-sm font-semibold">{{ $project->service_type }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-bold uppercase text-slate-500">Priority</p>
                        <p class="mt-1 text-sm font-semibold">{{ $project->priority }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-bold uppercase text-slate-500">Status</p>
                        <p class="mt-1 text-sm font-semibold">{{ $project->status }}</p>
                    </div>
                </div>

                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-bold uppercase text-slate-500">Description</p>
                    <p class="mt-1 text-sm text-slate-700">{{ $project->description ?: 'No description provided.' }}</p>
                </div>

                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-bold uppercase text-slate-500">Assigned Users</p>
                    @php
                        $assignedUsersByRole = $project->assignedUsers->groupBy('role');
                    @endphp
                    @if ($project->assignedUsers->isEmpty())
                        <p class="mt-1 text-sm text-slate-700">No assigned users yet.</p>
                    @else
                        <div class="mt-2 grid gap-2 md:grid-cols-3">
                            @foreach ($assignableRoleLabels as $role => $roleLabel)
                                <div class="rounded-lg border border-slate-200 bg-white p-2">
                                    <p class="text-xs font-bold uppercase text-slate-500">{{ $roleLabel }}</p>
                                    @php
                                        $roleUsers = $assignedUsersByRole->get($role, collect());
                                    @endphp
                                    @if ($roleUsers->isEmpty())
                                        <p class="mt-1 text-xs text-slate-500">None</p>
                                    @else
                                        <ul class="mt-1 space-y-1">
                                            @foreach ($roleUsers as $assignedUser)
                                                <li class="text-sm text-slate-700">
                                                    {{ $assignedUser->name }} <span class="text-xs text-slate-500">({{ $assignedUser->email }})</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('projects.edit', $project) }}" class="btn btn-primary rounded-lg px-3 py-2 text-sm font-bold text-white">Edit Project</a>
                    <a href="{{ route('projects.index') }}" class="btn rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700">Back to Projects</a>
                </div>
            </article>
        </section>
    </div>
</x-layouts.app>
