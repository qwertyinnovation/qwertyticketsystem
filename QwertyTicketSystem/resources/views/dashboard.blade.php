<x-layouts.app :title="'Dashboard | Qwerty Ticket System'">
    <div id="appShell" class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'dashboard', 'currentUser' => $user])

        <main class="grid gap-4">
            @include('partials.alerts')

            <header class="panel rounded-2xl border p-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.08em] text-cyan-700">Service Desk Command Center</p>
                        <h2 class="text-2xl font-extrabold tracking-tight">Dashboard</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            @if ($canManageAllTickets)
                                Live overview across all projects, users, and service ticket workflows.
                            @else
                                Live overview for your assigned project tickets and accessible operations.
                            @endif
                        </p>
                    </div>

                    <div class="rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-900">
                        <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700">Today</p>
                        <p class="font-bold">{{ now()->format('d M Y') }}</p>
                        <p class="text-xs text-cyan-800/90">
                            {{ $canManageAllTickets ? 'Viewing all ticket activity' : 'Viewing assigned project ticket activity' }}
                        </p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($permissions as $permissionKey => $permissionLabel)
                        @if ($user->hasPermission($permissionKey))
                            <span class="badge rounded-full px-2 py-1 text-xs">{{ $permissionLabel }}</span>
                        @endif
                    @endforeach
                </div>
            </header>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <article class="panel metric rounded-2xl border bg-white p-4">
                    <h4 class="text-xs font-bold uppercase tracking-[0.04em] text-slate-500">Total Tickets</h4>
                    <p class="mt-2 text-3xl font-extrabold">{{ $totalTickets }}</p>
                    <p class="mt-1 text-xs text-slate-500">All tickets in your current view scope.</p>
                </article>
                <article class="panel metric rounded-2xl border bg-white p-4">
                    <h4 class="text-xs font-bold uppercase tracking-[0.04em] text-slate-500">Open Tickets</h4>
                    <p class="mt-2 text-3xl font-extrabold">{{ $openTickets }}</p>
                    <p class="mt-1 text-xs text-slate-500">New, in progress, waiting, or active states.</p>
                </article>
                <article class="panel metric rounded-2xl border bg-white p-4">
                    <h4 class="text-xs font-bold uppercase tracking-[0.04em] text-slate-500">Closed Tickets</h4>
                    <p class="mt-2 text-3xl font-extrabold">{{ $closedTickets }}</p>
                    <p class="mt-1 text-xs text-slate-500">Resolved, completed, or closed outcomes.</p>
                </article>
                <article class="panel metric rounded-2xl border bg-white p-4">
                    <h4 class="text-xs font-bold uppercase tracking-[0.04em] text-slate-500">Active Projects</h4>
                    <p class="mt-2 text-3xl font-extrabold">{{ $activeProjects }}</p>
                    <p class="mt-1 text-xs text-slate-500">Open projects currently being delivered.</p>
                </article>
                <article class="panel metric rounded-2xl border bg-white p-4">
                    @if ($user->hasPermission(\App\Models\User::PERMISSION_MANAGE_USERS))
                        <h4 class="text-xs font-bold uppercase tracking-[0.04em] text-slate-500">Total Users</h4>
                        <p class="mt-2 text-3xl font-extrabold">{{ $totalUsers }}</p>
                        <p class="mt-1 text-xs text-slate-500">System users across all requester roles.</p>
                    @elseif ($canGeneratePublicLink)
                        <h4 class="text-xs font-bold uppercase tracking-[0.04em] text-slate-500">Active Public Links</h4>
                        <p class="mt-2 text-3xl font-extrabold">{{ $activePublicLinksCount }}</p>
                        <p class="mt-1 text-xs text-slate-500">One-time links not used and not expired.</p>
                    @else
                        <h4 class="text-xs font-bold uppercase tracking-[0.04em] text-slate-500">Total Projects</h4>
                        <p class="mt-2 text-3xl font-extrabold">{{ $totalProjects }}</p>
                        <p class="mt-1 text-xs text-slate-500">Projects tracked in the workspace.</p>
                    @endif
                </article>
            </div>

            <div class="grid gap-4 xl:grid-cols-[1.3fr_1fr]">
                <section class="panel rounded-2xl border bg-white p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-lg font-bold">Ticket Status Breakdown</h3>
                        <span class="badge rounded-full px-2 py-1 text-xs">Total: {{ $totalTickets }}</span>
                    </div>

                    <div class="mt-3 grid gap-3">
                        @forelse ($ticketStatusCounts as $status => $count)
                            @php
                                $statusPercent = $totalTickets > 0 ? (int) round(($count / $totalTickets) * 100) : 0;
                            @endphp
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs font-semibold text-slate-600">
                                    <span>{{ $status }}</span>
                                    <span>{{ $count }} ({{ $statusPercent }}%)</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-blue-600"
                                        style="width: {{ $statusPercent }}%;"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No ticket data available.</p>
                        @endforelse
                    </div>
                </section>

                <div class="grid gap-4">
                    <section class="panel hero rounded-2xl border p-4">
                        <h3 class="text-lg font-bold">Quick Actions</h3>
                        <p class="mt-1 text-sm text-slate-600">Go directly to high-frequency tasks.</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                            @if ($user->hasPermission(\App\Models\User::PERMISSION_MANAGE_TICKETS))
                                <a href="{{ route('service-tickets.create') }}"
                                    class="btn btn-primary inline-flex items-center justify-center rounded-lg px-3 py-2 text-sm font-bold text-white">
                                    Submit New Ticket
                                </a>
                                <a href="{{ route('service-tickets.index') }}"
                                    class="btn inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700">
                                    Open Ticket List
                                </a>
                            @endif

                            @if ($user->hasPermission(\App\Models\User::PERMISSION_MANAGE_PROJECTS))
                                <a href="{{ route('projects.create') }}"
                                    class="btn inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700">
                                    Create Project
                                </a>
                            @endif

                            @if ($user->hasPermission(\App\Models\User::PERMISSION_MANAGE_USERS))
                                <a href="{{ route('users.create') }}"
                                    class="btn inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700">
                                    Create User
                                </a>
                            @endif

                            @if ($canGeneratePublicLink)
                                <a href="{{ route('service-tickets.public-links.index') }}"
                                    class="btn inline-flex items-center justify-center rounded-lg border border-cyan-300 bg-cyan-50 px-3 py-2 text-sm font-bold text-cyan-700">
                                    One-Time Public Form
                                </a>
                            @endif

                            @if ($user->hasPermission(\App\Models\User::PERMISSION_MANAGE_SETTINGS))
                                <a href="{{ route('settings.index') }}"
                                    class="btn inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700">
                                    Settings
                                </a>
                            @endif
                        </div>
                    </section>

                    <section class="panel rounded-2xl border bg-white p-4">
                        <h3 class="text-lg font-bold">Requester Mix</h3>
                        <p class="mt-1 text-sm text-slate-600">Ticket count by requester type.</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($requesterRoles as $roleKey => $roleLabel)
                                <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $roleLabel }}</p>
                                    <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $requesterRoleCounts[$roleKey] ?? 0 }}</p>
                                </article>
                            @endforeach
                        </div>
                    </section>
                </div>
            </div>

            <section class="panel rounded-2xl border bg-white p-4">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="text-lg font-bold">Recent Tickets</h3>
                    @if ($user->hasPermission(\App\Models\User::PERMISSION_MANAGE_TICKETS))
                        <a href="{{ route('service-tickets.index') }}"
                            class="btn inline-flex rounded-lg border border-slate-300 bg-slate-50 px-2 py-1 text-xs font-bold text-slate-700">
                            View All
                        </a>
                    @endif
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full border-collapse text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-3 py-2 font-bold text-slate-700">ID</th>
                                <th class="px-3 py-2 font-bold text-slate-700">Title</th>
                                <th class="px-3 py-2 font-bold text-slate-700">Project</th>
                                <th class="px-3 py-2 font-bold text-slate-700">Requester</th>
                                <th class="px-3 py-2 font-bold text-slate-700">Status</th>
                                <th class="px-3 py-2 font-bold text-slate-700">Submitted By</th>
                                <th class="px-3 py-2 font-bold text-slate-700">Updated</th>
                                <th class="px-3 py-2 font-bold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentTickets as $ticket)
                                <tr class="border-t border-slate-100 bg-white">
                                    <td class="px-3 py-2 font-semibold">#{{ $ticket->id }}</td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{ \Illuminate\Support\Str::limit($ticket->title ?: 'No title', 40) }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">{{ $ticket->project?->name ?? 'N/A' }}</td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{ $requesterRoles[$ticket->requester_role] ?? ucfirst((string) $ticket->requester_role) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="badge rounded-full px-2 py-1 text-xs">{{ $ticket->status }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{ $ticket->submittedBy?->name ?? 'Public One-Time Link' }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">{{ $ticket->updated_at?->format('d M Y H:i') ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        @if ($user->hasPermission(\App\Models\User::PERMISSION_MANAGE_TICKETS))
                                            <a href="{{ route('service-tickets.show', $ticket) }}"
                                                class="btn inline-flex rounded-lg border border-slate-300 bg-slate-50 px-2 py-1 text-xs font-bold text-slate-700">
                                                View
                                            </a>
                                        @else
                                            <span class="text-xs text-slate-400">No access</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-3 py-6 text-center text-slate-500">No recent tickets found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($user->hasPermission(\App\Models\User::PERMISSION_MANAGE_USERS))
                <section class="panel rounded-2xl border bg-white p-4">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h3 class="text-lg font-bold">User Role Distribution</h3>
                        <span class="badge rounded-full px-2 py-1 text-xs">Total: {{ $totalUsers }}</span>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Admins</p>
                            <p class="mt-1 text-2xl font-extrabold">{{ $adminUsers }}</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">PMs</p>
                            <p class="mt-1 text-2xl font-extrabold">{{ $pmUsers }}</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Clients</p>
                            <p class="mt-1 text-2xl font-extrabold">{{ $clientUsers }}</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Internal</p>
                            <p class="mt-1 text-2xl font-extrabold">{{ $internalUsers }}</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vendors</p>
                            <p class="mt-1 text-2xl font-extrabold">{{ $vendorUsers }}</p>
                        </article>
                    </div>
                </section>
            @endif
        </main>
    </div>
</x-layouts.app>
