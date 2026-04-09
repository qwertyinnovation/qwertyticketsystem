<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
        $viberGroupLink = trim((string) config('services.viber.group_link', ''));
        $showViberWidget = $viberGroupLink !== '' || app()->environment('local');
        $resolvedViberGroupLink = $viberGroupLink !== '' ? $viberGroupLink : 'https://www.viber.com/';
    @endphp

    @if ($showViberWidget)
        <div class="viber-widget" data-viber-widget>
            <section class="viber-card" data-viber-card>
                <button type="button" class="viber-card-close" data-viber-close aria-label="Close Viber card">×</button>
                <p class="viber-card-title">Join our Viber Community!</p>
                <p class="viber-card-text">Get help, updates, and support from our team.</p>
                @if ($viberGroupLink === '')
                    <p class="viber-card-hint">Set <code>VIBER_GROUP_LINK</code> in <code>.env</code> to use your group link.</p>
                @endif
                <a href="{{ $resolvedViberGroupLink }}" target="_blank" rel="noopener noreferrer" class="viber-card-link">
                    Join Viber Group →
                </a>
            </section>

            <button type="button" class="viber-fab" data-viber-trigger aria-label="Open Viber Group Card">
                <span class="viber-fab-ring"></span>
                <span class="viber-fab-core">
                    <i class="fa-brands fa-viber" aria-hidden="true"></i>
                </span>
            </button>
        </div>
    @endif

    <script>
        (() => {
            const widget = document.querySelector('[data-viber-widget]');
            const closeButton = widget?.querySelector('[data-viber-close]');
            const card = widget?.querySelector('[data-viber-card]');
            const triggerButton = widget?.querySelector('[data-viber-trigger]');

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
            };

            const showCard = () => {
                clearAutoClose();
                card.classList.add('is-open');
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
