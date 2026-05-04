<x-layouts.app :title="'Service Tickets | Qwerty Ticket System'">
    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'service-tickets', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <header class="panel rounded-2xl border p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight">Service Tickets</h1>
                        <p class="mt-1 text-sm text-slate-600">Track requests from client, internal staff, and
                            third-party providers.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($canGeneratePublicLink)
                            <a href="{{ route('service-tickets.public-links.index') }}"
                                class="btn inline-flex rounded-lg border border-cyan-300 bg-cyan-50 px-3 py-2 text-sm font-bold text-cyan-700">
                                One-Time Public Form
                            </a>
                        @endif
                        <a href="{{ route('service-tickets.create') }}"
                            class="btn btn-primary inline-flex rounded-lg px-3 py-2 text-sm font-bold text-white">
                            Submit New Ticket
                        </a>
                    </div>
                </div>
            </header>

            <article class="panel rounded-2xl border bg-white p-4">
                <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-bold">Ticket List</h2>
                    <span class="badge rounded-full px-2 py-1 text-xs">Total: {{ $tickets->total() }}</span>
                </div>

                <form method="GET" action="{{ route('service-tickets.index') }}"
                    class="mb-3 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="grid gap-3 md:grid-cols-12">
                        <label class="grid min-w-0 gap-1 text-xs font-semibold md:col-span-6">
                            Search
                            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                                placeholder="Search ID, title, or description" class="rounded-lg px-3 py-2 text-sm" />
                        </label>

                        <label class="grid min-w-0 gap-1 text-xs font-semibold md:col-span-3">
                            Project
                            <select name="project_id" class="rounded-lg px-3 py-2 text-sm">
                                <option value="">All</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected((int) ($filters['project_id'] ?? 0) === (int) $project->id)>{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="grid min-w-0 gap-1 text-xs font-semibold md:col-span-3">
                            Service Type
                            <select name="service_type" class="rounded-lg px-3 py-2 text-sm">
                                <option value="">All</option>
                                @foreach ($serviceTypes as $serviceType)
                                    <option value="{{ $serviceType }}" @selected(($filters['service_type'] ?? '') === $serviceType)>{{ $serviceType }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="grid gap-3 md:grid-cols-12">
                        <label class="grid min-w-0 gap-1 text-xs font-semibold md:col-span-3">
                            Requester Type
                            <select name="requester_role" class="rounded-lg px-3 py-2 text-sm">
                                <option value="">All</option>
                                @foreach ($requesterRoles as $roleKey => $roleLabel)
                                    <option value="{{ $roleKey }}" @selected(($filters['requester_role'] ?? '') === $roleKey)>
                                        {{ $roleLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="grid min-w-0 gap-1 text-xs font-semibold md:col-span-3">
                            Status
                            <select name="status" class="rounded-lg px-3 py-2 text-sm">
                                <option value="">All</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                                        {{ $status }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="grid min-w-0 gap-1 text-xs font-semibold md:col-span-3">
                            Created From
                            <input type="date" name="created_date_from" value="{{ $filters['created_date_from'] ?? '' }}"
                                class="rounded-lg px-3 py-2 text-sm" />
                        </label>

                        <label class="grid min-w-0 gap-1 text-xs font-semibold md:col-span-3">
                            Created To
                            <input type="date" name="created_date_to" value="{{ $filters['created_date_to'] ?? '' }}"
                                class="rounded-lg px-3 py-2 text-sm" />
                        </label>
                    </div>

                    <div class="grid gap-3 md:grid-cols-12">
                        <label class="grid min-w-0 gap-1 text-xs font-semibold md:col-span-3">
                            Response From
                            <input type="date" name="response_date_from" value="{{ $filters['response_date_from'] ?? '' }}"
                                class="rounded-lg px-3 py-2 text-sm" />
                        </label>

                        <label class="grid min-w-0 gap-1 text-xs font-semibold md:col-span-3">
                            Response To
                            <input type="date" name="response_date_to" value="{{ $filters['response_date_to'] ?? '' }}"
                                class="rounded-lg px-3 py-2 text-sm" />
                        </label>

                        <div class="flex flex-wrap items-end gap-2 md:col-span-6 md:justify-end">
                            <button type="submit"
                                class="btn btn-primary rounded-lg px-3 py-2 text-sm font-bold text-white">Apply</button>
                            <a href="{{ route('service-tickets.index') }}"
                                class="btn rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700">Clear</a>
                        </div>
                    </div>
                </form>

                <form method="POST" action="{{ route('service-tickets.bulk-destroy') }}" data-bulk-delete-form data-bulk-confirm="Delete selected service tickets? Attachments and responses will also be deleted.">
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
                                        <input type="checkbox" class="rounded" data-bulk-select-all aria-label="Select all service tickets on this page" />
                                    </th>
                                    <th class="px-3 py-2 font-bold text-slate-700">ID</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Project</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Requester</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Title</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Status</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Submitted By</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Dates</th>
                                    <th class="px-3 py-2 font-bold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($tickets as $ticket)
                                    @php
                                        $latestResponse = $ticket->latestResponse;
                                        $canDeleteTicket = $canManageAllTickets || (int) $ticket->submitted_by_user_id === (int) $currentUser->id;
                                    @endphp
                                    <tr class="border-t border-slate-100 bg-white">
                                        <td class="px-3 py-2">
                                            <input
                                                type="checkbox"
                                                name="selected_ids[]"
                                                value="{{ $ticket->id }}"
                                                class="rounded"
                                                data-bulk-select-row
                                                aria-label="Select ticket #{{ $ticket->id }}"
                                                @disabled(! $canDeleteTicket)
                                            />
                                        </td>
                                        <td class="px-3 py-2 font-semibold">#{{ $ticket->id }}</td>
                                        <td class="px-3 py-2 text-slate-600">
                                            <div class="font-medium text-slate-700">{{ $ticket->project?->name ?? 'N/A' }}</div>
                                            <div class="text-xs text-slate-500">{{ $ticket->project?->service_type ?? 'No service type' }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{ $requesterRoles[$ticket->requester_role] ?? ucfirst($ticket->requester_role) }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{ $ticket->title ? \Illuminate\Support\Str::limit($ticket->title, 45) : 'No title' }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="badge rounded-full px-2 py-1 text-xs">{{ $ticket->status }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{ $ticket->submittedBy?->name ?? 'Public One-Time Link' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs text-slate-600">
                                            <div>Created: {{ $ticket->created_at?->format('Y-m-d') ?? 'N/A' }}</div>
                                            <div class="mt-1">Last response: {{ $latestResponse?->created_at?->format('Y-m-d') ?? 'No response' }}</div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <a href="{{ route('service-tickets.show', $ticket) }}"
                                                class="btn inline-flex rounded-lg border border-slate-300 bg-slate-50 px-2 py-1 text-xs font-bold text-slate-700">View
                                                Details</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-3 py-6 text-center text-slate-500">No tickets found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>

                <div class="mt-3">
                    {{ $tickets->links() }}
                </div>
            </article>
        </section>
    </div>
</x-layouts.app>
