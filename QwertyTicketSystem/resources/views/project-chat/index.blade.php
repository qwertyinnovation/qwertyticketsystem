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
                        <span class="chat-lobby-summary-value">{{ $projects->count() }}</span>
                    </div>
                </div>
            </article>

            <section class="chat-room-grid">
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
