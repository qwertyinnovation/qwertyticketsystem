<x-layouts.app :title="'Projects | Qwerty Ticket System'">
    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'projects', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <header class="panel rounded-2xl border p-4">
                <h1 class="text-2xl font-extrabold tracking-tight">Projects</h1>
                <p class="mt-1 text-sm text-slate-600">Create and maintain project records for your service ticket workflow.</p>
            </header>

            <article class="panel rounded-2xl border bg-white p-4">
                <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-bold">Project List</h2>
                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                        <span class="badge rounded-full px-2 py-1 text-xs">Total: {{ $projects->total() }}</span>
                        <a href="{{ route('projects.create') }}" class="btn btn-primary rounded-lg px-3 py-2 text-center text-xs font-bold text-white sm:w-auto">Create Project</a>
                    </div>
                </div>

                <form method="GET" action="{{ route('projects.index') }}" class="mb-3 grid gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-6">
                    <label class="grid gap-1 text-xs font-semibold md:col-span-2">
                        Search
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Search by project name or description"
                            class="rounded-lg px-3 py-2 text-sm"
                        />
                    </label>

                    <label class="grid gap-1 text-xs font-semibold">
                        Category
                        <select name="category" class="rounded-lg px-3 py-2 text-sm">
                            <option value="">All</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="grid gap-1 text-xs font-semibold">
                        Service Type
                        <select name="service_type" class="rounded-lg px-3 py-2 text-sm">
                            <option value="">All</option>
                            @foreach ($serviceTypes as $serviceType)
                                <option value="{{ $serviceType }}" @selected(($filters['service_type'] ?? '') === $serviceType)>{{ $serviceType }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="grid gap-1 text-xs font-semibold">
                        Priority
                        <select name="priority" class="rounded-lg px-3 py-2 text-sm">
                            <option value="">All</option>
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ $priority }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="grid gap-1 text-xs font-semibold">
                        Status
                        <select name="status" class="rounded-lg px-3 py-2 text-sm">
                            <option value="">All</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="flex flex-wrap items-end gap-2 md:col-span-6">
                        <button type="submit" class="btn btn-primary rounded-lg px-3 py-2 text-sm font-bold text-white">Apply</button>
                        <a href="{{ route('projects.index') }}" class="btn rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700">Clear</a>
                    </div>
                </form>

                <form method="POST" action="{{ route('projects.bulk-destroy') }}" data-bulk-delete-form data-bulk-confirm="Delete selected projects? Related tickets and chat records will also be deleted.">
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
                                        <input type="checkbox" class="rounded" data-bulk-select-all aria-label="Select all projects on this page" />
                                    </th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Name</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Category</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Service Type</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Priority</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Status</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($projects as $project)
                                    <tr class="border-t border-slate-100 bg-white">
                                        <td class="px-3 py-2">
                                            <input type="checkbox" name="selected_ids[]" value="{{ $project->id }}" class="rounded" data-bulk-select-row aria-label="Select {{ $project->name }}" />
                                        </td>
                                        <td class="px-3 py-2 font-semibold">{{ $project->name }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $project->category }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $project->service_type }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $project->priority }}</td>
                                        <td class="px-3 py-2">
                                            <span class="badge rounded-full px-2 py-1 text-xs">{{ $project->status }}</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-wrap gap-2">
                                                <a href="{{ route('projects.show', $project) }}" class="btn inline-flex rounded-lg border border-slate-300 bg-slate-50 px-2 py-1 text-xs font-bold text-slate-700">View Details</a>
                                                <a href="{{ route('projects.edit', $project) }}" class="btn inline-flex rounded-lg border border-cyan-300 bg-cyan-50 px-2 py-1 text-xs font-bold text-cyan-700">Edit</a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-3 py-6 text-center text-slate-500">No projects found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>

                <div class="mt-3">
                    {{ $projects->links() }}
                </div>
            </article>
        </section>
    </div>
</x-layouts.app>
