<x-layouts.app :title="'Live Chat | Qwerty Ticket System'">
    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'live-chat', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <article class="panel chat-lobby-hero rounded-[2rem] border p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.12em] text-cyan-100/90">Messenger Mode</p>
                        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-white">Live Chat</h1>
                        <p class="mt-2 max-w-2xl text-sm text-cyan-50/90">
                            Fast project rooms for updates, blockers, and decisions. Assigned users can join their rooms, while admins and project managers stay auto-connected.
                        </p>
                    </div>
                    <div class="chat-lobby-summary">
                        <span class="chat-lobby-summary-label">Available Rooms</span>
                        <span class="chat-lobby-summary-value">{{ $projects->count() + 1 }}</span>
                    </div>
                </div>
            </article>

            <section class="chat-room-grid">
                @php
                    $generalLatestMessage = $generalChat['latest_message'] ?? null;
                    $generalStatus = $generalChat['status'] ?? 'not_joined';
                @endphp
                <article class="panel chat-room-card rounded-[1.75rem] border bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="chat-room-avatar">
                                G
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-lg font-bold text-slate-900">General Chat</h2>
                                    <span class="chat-room-status">{{ $generalChat['status_label'] ?? 'Request Required' }}</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-600">
                                    @if ($generalLatestMessage)
                                        <span class="font-semibold text-slate-700">{{ $generalLatestMessage->author?->name ?? 'Unknown' }}:</span>
                                        {{ \Illuminate\Support\Str::limit($generalLatestMessage->message, 90) }}
                                    @elseif (($generalChat['can_access'] ?? false) || $generalStatus === \App\Models\GeneralChatMember::STATUS_APPROVED)
                                        No messages yet. Open the room and start the shared thread.
                                    @elseif ($generalStatus === \App\Models\GeneralChatMember::STATUS_PENDING)
                                        Your request is waiting for admin approval.
                                    @elseif ($generalStatus === \App\Models\GeneralChatMember::STATUS_KICKED)
                                        You were removed from this room. Send a new request if you need access again.
                                    @else
                                        Request admin approval to join the company-wide room.
                                    @endif
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span>{{ $generalChat['messages_count'] ?? 0 }} {{ \Illuminate\Support\Str::plural('message', $generalChat['messages_count'] ?? 0) }}</span>
                                    <span>
                                        {{ $generalLatestMessage?->created_at?->diffForHumans() ?? 'Waiting for first update' }}
                                    </span>
                                    @if (($generalChat['unread_count'] ?? 0) > 0)
                                        <span class="inline-flex items-center rounded-full bg-cyan-100 px-2.5 py-1 font-bold text-cyan-700">
                                            {{ $generalChat['unread_count'] }} unread
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <a
                            href="{{ route('general-chat.show') }}"
                            class="btn chat-room-open inline-flex items-center rounded-full px-4 py-2 text-sm font-bold"
                        >
                            @if ($generalStatus === \App\Models\GeneralChatMember::STATUS_PENDING)
                                View Status
                            @elseif ($generalChat['can_access'] ?? false)
                                Open Chat
                            @else
                                Join
                            @endif
                        </a>
                    </div>
                </article>

                @forelse ($projects as $project)
                    @php
                        $latestMessage = $project->latestMessage;
                    @endphp
                    <article class="panel chat-room-card rounded-[1.75rem] border bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="chat-room-avatar">
                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($project->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="truncate text-lg font-bold text-slate-900">{{ $project->name }}</h2>
                                        <span class="chat-room-status">{{ $project->status }}</span>
                                    </div>
                                    <p class="mt-2 text-sm text-slate-600">
                                        @if ($latestMessage)
                                            <span class="font-semibold text-slate-700">{{ $latestMessage->author?->name ?? 'Unknown' }}:</span>
                                            {{ \Illuminate\Support\Str::limit($latestMessage->message, 90) }}
                                        @else
                                            No messages yet. Open the room and start the thread.
                                        @endif
                                    </p>
                                    <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                        <span>{{ $project->messages_count }} {{ \Illuminate\Support\Str::plural('message', $project->messages_count) }}</span>
                                        <span>
                                            {{ $latestMessage?->created_at?->diffForHumans() ?? 'Waiting for first update' }}
                                        </span>
                                        @if (($project->unread_messages_count ?? 0) > 0)
                                            <span class="inline-flex items-center rounded-full bg-cyan-100 px-2.5 py-1 font-bold text-cyan-700">
                                                {{ $project->unread_messages_count }} unread
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <a
                                href="{{ route('project-chat.show', $project) }}"
                                class="btn chat-room-open inline-flex items-center rounded-full px-4 py-2 text-sm font-bold"
                            >
                                Open Chat
                            </a>
                        </div>
                    </article>
                @empty
                    <article class="panel rounded-[1.75rem] border bg-white p-6 text-center">
                        <h2 class="text-lg font-bold text-slate-900">No chat rooms yet</h2>
                        <p class="mt-2 text-sm text-slate-600">
                            You will see a live chat room here once you are assigned to a project.
                        </p>
                    </article>
                @endforelse
            </section>
        </section>
    </div>
</x-layouts.app>
