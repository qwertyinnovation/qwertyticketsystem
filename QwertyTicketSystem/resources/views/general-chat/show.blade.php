<x-layouts.app :title="'General Chat | Qwerty Ticket System'">
    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'live-chat', 'currentUser' => $currentUser])

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.7fr)_360px]">
            <div class="grid gap-4">
                @include('partials.alerts')

                <article class="panel messenger-shell rounded-[2rem] border p-4">
                    <div class="messenger-room-bar">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="messenger-room-avatar">G</div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-[0.12em] text-cyan-700">General Room</p>
                                <h1 class="truncate text-2xl font-extrabold tracking-tight text-slate-900">General Chat</h1>
                                <p class="mt-1 text-sm text-slate-500">
                                    @if ($canAccessRoom)
                                        {{ $participants->count() }} members • {{ $messages->count() }} {{ \Illuminate\Support\Str::plural('message', $messages->count()) }}
                                    @else
                                        Admin approval is required before you can read or send messages.
                                    @endif
                                </p>
                                @if ($canAccessRoom)
                                    <p class="mt-1 text-xs font-semibold uppercase tracking-[0.12em] text-emerald-600" data-project-chat-online-count>
                                        Checking room presence...
                                    </p>
                                @endif
                            </div>
                        </div>
                        <a
                            href="{{ route('project-chat.index') }}"
                            class="btn messenger-room-back inline-flex rounded-full px-4 py-2 text-sm font-bold"
                        >
                            All Rooms
                        </a>
                    </div>

                    @if (! $canAccessRoom)
                        <div class="rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                            @php
                                $status = $membership?->status ?? 'not_joined';
                            @endphp

                            @if ($status === \App\Models\GeneralChatMember::STATUS_PENDING)
                                <h2 class="text-lg font-bold text-slate-900">Waiting for admin approval</h2>
                                <p class="mt-2 text-sm text-slate-600">Your join request is already in the admin queue.</p>
                            @elseif ($status === \App\Models\GeneralChatMember::STATUS_KICKED)
                                <h2 class="text-lg font-bold text-slate-900">You were removed from General Chat</h2>
                                <p class="mt-2 text-sm text-slate-600">You can send a new request if you need access again.</p>
                                <form method="POST" action="{{ route('general-chat.request') }}" class="mt-4">
                                    @csrf
                                    <button type="submit" class="btn inline-flex rounded-full px-4 py-2 text-sm font-bold">Request Again</button>
                                </form>
                            @else
                                <h2 class="text-lg font-bold text-slate-900">Join General Chat</h2>
                                <p class="mt-2 text-sm text-slate-600">Send a request and an admin can approve your access.</p>
                                <form method="POST" action="{{ route('general-chat.request') }}" class="mt-4">
                                    @csrf
                                    <button type="submit" class="btn inline-flex rounded-full px-4 py-2 text-sm font-bold">Request Access</button>
                                </form>
                            @endif
                        </div>
                    @else
                        <div class="messenger-thread-wrap">
                            <div
                                class="project-chat-thread messenger-thread"
                                data-project-chat-thread
                                data-project-id="0"
                                data-broadcast-channel="general-chat"
                                data-presence-channel="general-chat.presence"
                                data-message-created-event=".general.message.created"
                                data-message-updated-event=".general.message.updated"
                                data-message-deleted-event=".general.message.deleted"
                                data-can-manage-all-messages="false"
                                data-current-user-id="{{ $currentUser->id }}"
                                data-current-user-name="{{ $currentUser->name }}"
                                data-current-user-role="{{ $currentUser->role }}"
                                data-show-url="{{ route('general-chat.show') }}"
                                data-read-url="{{ route('general-chat.read') }}"
                                data-update-template="{{ route('general-chat.update', '__MESSAGE__') }}"
                                data-delete-template="{{ route('general-chat.destroy', '__MESSAGE__') }}"
                                data-role-labels='@json($roleLabels)'
                            >
                                @forelse ($messages as $message)
                                    @php
                                        $isCurrentUser = (int) ($message->user_id ?? 0) === (int) ($currentUser->id ?? 0);
                                        $canManageMessage = $isCurrentUser;
                                        $messageInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($message->author?->name ?? 'U', 0, 1));
                                    @endphp
                                    <article id="message-{{ $message->id }}" class="messenger-message {{ $isCurrentUser ? 'is-current-user' : 'is-remote-user' }}">
                                        @unless ($isCurrentUser)
                                            <div class="messenger-message-avatar">{{ $messageInitial }}</div>
                                        @endunless

                                        <div class="messenger-bubble-cluster">
                                            <div class="messenger-message-meta">
                                                <span class="font-bold text-slate-800">{{ $message->author?->name ?? 'Unknown User' }}</span>
                                                <span>{{ $roleLabels[$message->author?->role ?? ''] ?? ucfirst((string) $message->author?->role) }}</span>
                                                <span>•</span>
                                                <span>{{ $message->created_at?->format('M d, H:i') }}</span>
                                            </div>
                                            <div class="messenger-bubble">
                                                <p class="whitespace-pre-line text-sm leading-6">{{ $message->message }}</p>
                                            </div>

                                            @if ($canManageMessage)
                                                <div class="messenger-message-actions">
                                                    <a
                                                        href="#message-{{ $message->id }}"
                                                        class="messenger-message-action"
                                                        data-project-chat-edit
                                                    >
                                                        Edit
                                                    </a>
                                                    <form method="POST" action="{{ route('general-chat.destroy', $message) }}" data-project-chat-delete-form>
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                            class="messenger-message-action messenger-message-action-danger"
                                                            onclick="return confirm('Delete this message?');"
                                                        >
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>

                                        @if ($isCurrentUser)
                                            <div class="messenger-message-avatar messenger-message-avatar-self">{{ $messageInitial }}</div>
                                        @endif
                                    </article>
                                @empty
                                    <div class="messenger-empty-state">
                                        <div class="messenger-empty-icon">
                                            <i class="fa-solid fa-comments" aria-hidden="true"></i>
                                        </div>
                                        <p class="text-sm font-semibold text-slate-700">No messages yet.</p>
                                        <p class="mt-1 text-sm text-slate-500">Send the first message and this room becomes the shared thread.</p>
                                    </div>
                                @endforelse
                            </div>

                            <form
                                method="POST"
                                action="{{ route('general-chat.store') }}"
                                class="messenger-composer"
                                data-project-chat-form
                            >
                                @csrf

                                <label class="sr-only" for="generalChatMessage">Message</label>
                                <textarea
                                    id="generalChatMessage"
                                    name="message"
                                    rows="1"
                                    class="messenger-composer-input"
                                    placeholder="Write a message... Press Enter to send"
                                    data-project-chat-message
                                    required
                                >{{ old('message') }}</textarea>

                                <div class="hidden items-center gap-2 text-xs font-semibold text-cyan-700" data-project-chat-editing-banner>
                                    <span data-project-chat-editing-label>Editing message</span>
                                    <button type="button" class="messenger-message-action" data-project-chat-editing-cancel>Cancel edit</button>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button type="submit" class="btn messenger-send-button inline-flex rounded-full px-4 py-3 text-sm font-bold text-white">
                                        <i class="fa-solid fa-paper-plane" aria-hidden="true" data-project-chat-submit-icon></i>
                                        <span data-project-chat-submit-label>Send</span>
                                    </button>
                                </div>

                                <span class="text-xs font-medium text-slate-500" data-project-chat-typing-indicator></span>

                                @error('message')
                                    <span class="text-xs font-medium text-red-600" data-project-chat-error>{{ $message }}</span>
                                @else
                                    <span class="hidden text-xs font-medium text-red-600" data-project-chat-error></span>
                                @enderror
                            </form>
                        </div>
                    @endif
                </article>
            </div>

            <aside class="grid gap-4">
                @if ($canModerateRoom)
                    <article class="panel rounded-[1.75rem] border bg-white p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-lg font-bold">Pending Requests</h2>
                            <span class="badge rounded-full px-2 py-1 text-xs">{{ $pendingMembers->count() }}</span>
                        </div>
                        <div class="mt-3 grid gap-2">
                            @forelse ($pendingMembers as $pendingMember)
                                <div class="messenger-member-card">
                                    <div class="messenger-member-avatar">
                                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($pendingMember->user?->name ?? 'U', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-900">{{ $pendingMember->user?->name }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $pendingMember->user?->email }}</p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <form method="POST" action="{{ route('general-chat.members.approve', $pendingMember) }}">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn rounded-full px-3 py-1.5 text-xs font-bold">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('general-chat.members.reject', $pendingMember) }}">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="messenger-message-action messenger-message-action-danger">Reject</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-slate-500">No pending requests.</p>
                            @endforelse
                        </div>
                    </article>

                    <article class="panel rounded-[1.75rem] border bg-white p-4">
                        <h2 class="text-lg font-bold">Invite User</h2>
                        <form method="POST" action="{{ route('general-chat.members.invite') }}" class="mt-3 grid gap-3">
                            @csrf
                            <label class="grid gap-1 text-sm font-semibold text-slate-700">
                                User
                                <select name="user_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                    <option value="">Select user</option>
                                    @foreach ($inviteCandidates as $candidate)
                                        <option value="{{ $candidate->id }}">
                                            {{ $candidate->name }} - {{ $roleLabels[$candidate->role] ?? ucfirst($candidate->role) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            @error('user_id')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                            <button type="submit" class="btn inline-flex justify-center rounded-full px-4 py-2 text-sm font-bold">Invite</button>
                        </form>
                    </article>
                @endif

                @if ($canAccessRoom)
                    <article class="panel rounded-[1.75rem] border bg-white p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-lg font-bold">Room Members</h2>
                            <span class="badge rounded-full px-2 py-1 text-xs">{{ $participants->count() }}</span>
                        </div>
                        <div class="mt-3 grid gap-2">
                            @foreach ($participants as $participant)
                                @php
                                    $participantInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($participant->name, 0, 1));
                                    $participantMembership = $participant->generalChatMembership;
                                @endphp
                                <div class="messenger-member-card" data-project-chat-participant="{{ $participant->id }}">
                                    <div class="messenger-member-avatar">{{ $participantInitial }}</div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex h-2.5 w-2.5 rounded-full bg-slate-300" data-project-chat-presence-dot></span>
                                            <p class="truncate text-sm font-bold text-slate-900">{{ $participant->name }}</p>
                                        </div>
                                        <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">
                                            {{ $roleLabels[$participant->role] ?? ucfirst($participant->role) }}
                                        </p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500" data-project-chat-presence-label>Offline</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $participant->email }}</p>

                                        @if ($canModerateRoom && (int) $participant->id !== (int) $currentUser->id && $participantMembership)
                                            <form method="POST" action="{{ route('general-chat.members.kick', $participantMembership) }}" class="mt-3">
                                                @csrf
                                                @method('PUT')
                                                <button
                                                    type="submit"
                                                    class="messenger-message-action messenger-message-action-danger"
                                                    onclick="return confirm('Remove this member from General Chat?');"
                                                >
                                                    Kick
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endif
            </aside>
        </section>
    </div>
</x-layouts.app>
