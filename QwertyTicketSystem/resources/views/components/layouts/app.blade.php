<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'Qwerty Ticket System' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen font-sans text-[#111827]">
    {{ $slot }}

    @php
        /** @var \App\Models\User|null $liveChatUser */
        $liveChatUser = auth()->user();
        $showLiveChatWidget = false;
        $liveChatRoomCount = 0;

        if ($liveChatUser?->hasPermission(\App\Models\User::PERMISSION_VIEW_DASHBOARD)) {
            $showLiveChatWidget = true;
            $liveChatRoomCount = in_array($liveChatUser->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_PM], true)
                ? \App\Models\Project::query()->count()
                : count($liveChatUser->assignedProjectIds());
        }
    @endphp

    @if ($showLiveChatWidget)
        <div class="chat-widget" data-chat-widget style="pointer-events: none;">
            <section class="chat-card" data-chat-card style="pointer-events: none;">
                <button type="button" class="chat-card-close" data-chat-close aria-label="Close live chat card">×</button>
                <div class="chat-card-header">
                    <div class="chat-card-presence">
                        <span class="chat-card-presence-dot"></span>
                        <span>Live support</span>
                    </div>
                    <div class="chat-card-avatars" aria-hidden="true">
                        <span>Q</span>
                        <span>P</span>
                        <span>A</span>
                    </div>
                </div>
                <p class="chat-card-title">Open Live Chat</p>
                <p class="chat-card-text">
                    Messenger-style project rooms for assigned users. Admins and PMs are already inside.
                </p>
                <div class="chat-card-preview">
                    <div class="chat-card-bubble chat-card-bubble-incoming">
                        <span class="chat-card-bubble-name">Support Team</span>
                        <span>Need an update? Drop it in the room.</span>
                    </div>
                    <div class="chat-card-bubble chat-card-bubble-outgoing">
                        <span>Open {{ $liveChatRoomCount }} {{ \Illuminate\Support\Str::plural('Room', $liveChatRoomCount) }}</span>
                    </div>
                </div>
                <a href="{{ route('project-chat.index') }}" class="chat-card-link">
                    Jump into chat →
                </a>
            </section>

            <button type="button" class="chat-fab" data-chat-trigger aria-label="Open live chat card" style="pointer-events: auto;">
                <span class="chat-fab-ring"></span>
                <span class="chat-fab-ping"></span>
                <span class="chat-fab-core">
                    <i class="fa-solid fa-comments" aria-hidden="true"></i>
                </span>
            </button>
        </div>
    @endif

    <script>
        (() => {
            const widget = document.querySelector('[data-chat-widget]');
            const closeButton = widget?.querySelector('[data-chat-close]');
            const card = widget?.querySelector('[data-chat-card]');
            const triggerButton = widget?.querySelector('[data-chat-trigger]');

            if (!widget || !closeButton || !card || !triggerButton) {
                return;
            }

            let autoCloseTimer = null;

            const clearAutoClose = () => {
                if (autoCloseTimer !== null) {
                    window.clearTimeout(autoCloseTimer);
                    autoCloseTimer = null;
                }
            };

            const hideCard = () => {
                clearAutoClose();
                card.classList.remove('is-open');
                card.style.pointerEvents = 'none';
            };

            const showCard = () => {
                clearAutoClose();
                card.classList.add('is-open');
                card.style.pointerEvents = 'auto';
                autoCloseTimer = window.setTimeout(hideCard, 5000);
            };

            triggerButton.addEventListener('click', (event) => {
                event.preventDefault();
                showCard();
            });

            closeButton.addEventListener('click', hideCard);

            card.addEventListener('mouseenter', clearAutoClose);
            card.addEventListener('mouseleave', () => {
                if (card.classList.contains('is-open')) {
                    autoCloseTimer = window.setTimeout(hideCard, 5000);
                }
            });
        })();
    </script>
</body>

</html>
