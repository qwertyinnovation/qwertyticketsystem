<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'Error' }} | Qwerty Ticket System</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/qwerty-logo.svg') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
        rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen font-sans text-[#111827]">
    <main class="mx-auto flex min-h-screen w-full max-w-5xl items-center px-4 py-8 sm:px-6">
        <section class="panel grid w-full gap-6 overflow-hidden rounded-[28px] border p-6 sm:p-8 lg:grid-cols-[180px_minmax(0,1fr)] lg:p-10">
            <div class="flex flex-col justify-between gap-4 rounded-[24px] bg-slate-950 p-5 text-white">
                <div>
                    <img src="{{ asset('images/qwerty-logo.svg') }}" alt="Qwerty Innovation" class="h-14 w-14 rounded-2xl bg-white object-contain p-1 shadow-lg shadow-black/20" />
                    <p class="mt-3 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100">Qwerty Ticket System</p>
                    <p class="mt-3 text-5xl font-black tracking-tight text-white">{{ $statusCode ?? 'Error' }}</p>
                </div>
                <div class="rounded-2xl border border-white/12 bg-white/8 p-4 text-sm text-slate-100">
                    <p class="font-semibold text-white">{{ $statusLabel ?? 'Request error' }}</p>
                    <p class="mt-1 text-slate-100">The system handled the request, but it could not complete it successfully.</p>
                </div>
            </div>

            <div class="flex flex-col justify-center">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-cyan-800">{{ $eyebrow ?? 'Something needs attention' }}</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">{{ $title ?? 'Something went wrong' }}</h1>
                <p class="mt-3 max-w-2xl text-base leading-7 text-slate-800">{{ $message ?? 'An unexpected problem occurred.' }}</p>

                @if (app()->hasDebugModeEnabled() && isset($exception) && filled($exception->getMessage()))
                    <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <p class="font-semibold">Error details</p>
                        <p class="mt-1 break-words">{{ $exception->getMessage() }}</p>
                    </div>
                @endif

                <div class="mt-7 flex flex-wrap gap-3">
                    <button
                        type="button"
                        onclick="window.history.back()"
                        class="btn rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700"
                    >
                        Go Back
                    </button>
                    <a href="{{ url('/') }}" class="btn btn-primary rounded-lg px-4 py-2 text-sm font-bold text-white">
                        Return Home
                    </a>
                </div>
            </div>
        </section>
    </main>
</body>

</html>
