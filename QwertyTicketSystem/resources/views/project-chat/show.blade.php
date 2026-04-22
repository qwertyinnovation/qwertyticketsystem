<x-layouts.app :title="'Project Live Chat | Qwerty Ticket System'">
    @php
        $roomInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($project->name, 0, 1));
    @endphp

    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'live-chat', 'currentUser' => $currentUser])

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.7fr)_340px]">
            <div class="grid gap-4">
                @include('partials.alerts')

                <article class="panel messenger-shell rounded-[2rem] border p-4">
                    <div class="messenger-room-bar">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="messenger-room-avatar">{{ $roomInitial }}</div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-[0.12em] text-cyan-700">Project Room</p>
                                <h1 class="truncate text-2xl font-extrabold tracking-tight text-slate-900">{{ $project->name }}</h1>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $participants->count() }} members • {{ $messages->count() }} {{ \Illuminate\Support\Str::plural('message', $messages->count()) }}
                                </p>
                            </div>
                        </div>
                        <a
                            href="{{ route('project-chat.index') }}"
                            class="btn messenger-room-back inline-flex rounded-full px-4 py-2 text-sm font-bold"
                        >
                            All Rooms
                        </a>
                    </div>
                    <div class="messenger-thread-wrap">
                        <div class="project-chat-thread messenger-thread" data-project-chat-thread>
                            @forelse ($messages as $message)
                                @php
                                    $isCurrentUser = (int) ($message->user_id ?? 0) === (int) ($currentUser->id ?? 0);
                                    $canManageMessage = $isCurrentUser || in_array($currentUser->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_PM], true);
                                    $isEditingMessage = (int) ($editingMessageId ?? 0) === (int) $message->id;
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
                                                    href="{{ route('project-chat.show', ['project' => $project, 'edit_message' => $message->id]) }}#message-{{ $message->id }}"
                                                    class="messenger-message-action"
                                                >
                                                    Edit
                                                </a>
                                                <form method="POST" action="{{ route('project-chat.destroy', [$project, $message]) }}">
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

                                        @if ($isEditingMessage)
                                            <form method="POST" action="{{ route('project-chat.update', [$project, $message]) }}" class="messenger-edit-form">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="editing_message_id" value="{{ $message->id }}" />
                                                <label class="sr-only" for="editMessage{{ $message->id }}">Edit message</label>
                                                <textarea
                                                    id="editMessage{{ $message->id }}"
                                                    name="update_message"
                                                    rows="3"
                                                    class="messenger-edit-input"
                                                    required
                                                >{{ old('update_message', $message->message) }}</textarea>
                                                <div class="messenger-edit-actions">
                                                    <button type="submit" class="btn messenger-send-button inline-flex rounded-full px-4 py-2 text-sm font-bold text-white">
                                                        Save
                                                    </button>
                                                    <a
                                                        href="{{ route('project-chat.show', $project) }}#message-{{ $message->id }}"
                                                        class="btn messenger-project-link inline-flex rounded-full px-3 py-2 text-sm font-bold"
                                                    >
                                                        Cancel
                                                    </a>
                                                </div>
                                                @error('update_message')
                                                    <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                                                @enderror
                                            </form>
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
                                    <p class="mt-1 text-sm text-slate-500">Send the first update and this room becomes the project thread.</p>
                                </div>
                            @endforelse
                        </div>

                        <form
                            method="POST"
                            action="{{ route('project-chat.store', $project) }}"
                            class="messenger-composer"
                            data-project-chat-form
                        >
                            @csrf

                            <label class="sr-only" for="projectChatMessage">Message</label>
                            <textarea
                                id="projectChatMessage"
                                name="message"
                                rows="1"
                                class="messenger-composer-input"
                                placeholder="Write a message... Press Enter to send"
                                data-project-chat-message
                                required
                            >{{ old('message') }}</textarea>

                            <div class="flex items-center gap-2">
                                @if ($currentUser->hasPermission(\App\Models\User::PERMISSION_MANAGE_PROJECTS))
                                    <a
                                        href="{{ route('projects.show', $project) }}"
                                        class="btn messenger-project-link inline-flex rounded-full px-3 py-2 text-sm font-bold"
                                    >
                                        Project
                                    </a>
                                @endif
                                <button type="submit" class="btn messenger-send-button inline-flex rounded-full px-4 py-3 text-sm font-bold text-white">
                                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                    <span>Send</span>
                                </button>
                            </div>

                            @error('message')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </form>
                    </div>
                </article>
            </div>

            <aside class="grid gap-4">
                <article class="panel rounded-[1.75rem] border bg-white p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-bold">Room Members</h2>
                        <span class="badge rounded-full px-2 py-1 text-xs">{{ $participants->count() }}</span>
                    </div>
                    <div class="mt-3 grid gap-2">
                        @foreach ($participants as $participant)
                            @php
                                $participantInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($participant->name, 0, 1));
                            @endphp
                            <div class="messenger-member-card">
                                <div class="messenger-member-avatar">{{ $participantInitial }}</div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-900">{{ $participant->name }}</p>
                                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">
                                        {{ $roleLabels[$participant->role] ?? ucfirst($participant->role) }}
                                        @if (in_array($participant->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_PM], true))
                                            • Auto Joined
                                        @elseif ($project->assignedUsers->contains('id', $participant->id))
                                            • Assigned
                                        @endif
                                    </p>
                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $participant->email }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            </aside>
        </section>
    </div>
</x-layouts.app>
