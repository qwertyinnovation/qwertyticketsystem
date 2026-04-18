<x-layouts.app :title="'One-Time Public Form | Qwerty Ticket System'">
    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'service-tickets', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <header class="panel rounded-2xl border p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight">One-Time Public Form</h1>
                        <p class="mt-1 text-sm text-slate-600">Generate one-time links for users without login.</p>
                    </div>
                    <a href="{{ route('service-tickets.index') }}" class="btn rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700">
                        Back to Tickets
                    </a>
                </div>
            </header>

            <article class="panel rounded-2xl border bg-white p-4">
                <form method="POST" action="{{ route('service-tickets.public-links.store') }}" class="grid gap-3">
                    @csrf

                    <label class="grid gap-1 text-sm font-semibold">
                        Project
                        <select name="project_id" class="rounded-lg px-3 py-2 text-sm" required>
                            <option value="">Select project</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected((int) old('project_id') === (int) $project->id)>
                                    {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('project_id')
                            <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Requester Type
                        <select name="requester_role" class="rounded-lg px-3 py-2 text-sm" required>
                            @foreach ($requesterRoles as $roleKey => $roleLabel)
                                <option value="{{ $roleKey }}" @selected(old('requester_role') === $roleKey)>{{ $roleLabel }}</option>
                            @endforeach
                        </select>
                        @error('requester_role')
                            <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <p class="text-xs text-slate-500">
                        Link expires in {{ $publicLinkExpiryMinutes }} minutes (editable in Settings).
                    </p>

                    <button type="submit" class="btn btn-primary rounded-lg px-3 py-2 text-sm font-bold text-white">
                        Generate One-Time Link
                    </button>
                </form>
            </article>

            @if ($generatedPublicLink)
                <article class="panel rounded-2xl border bg-white p-4">
                    <p class="text-xs font-bold uppercase text-cyan-800">Generated Link</p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                        <input
                            id="generatedPublicLinkInput"
                            type="text"
                            readonly
                            value="{{ $generatedPublicLink }}"
                            class="w-full rounded-lg border border-cyan-200 bg-white px-3 py-2 text-xs text-cyan-900"
                        />
                        <button type="button" id="copyPublicLinkBtn" class="btn rounded-lg border border-cyan-300 bg-cyan-100 px-3 py-2 text-xs font-bold text-cyan-800 sm:w-auto">
                            Copy
                        </button>
                    </div>
                </article>
            @endif

            <article class="panel rounded-2xl border bg-white p-4">
                <h2 class="text-lg font-bold">Your Active Links</h2>
                <div class="mt-2 space-y-2">
                    @forelse ($activePublicLinks as $activeLink)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-2">
                            <p class="text-xs font-semibold text-slate-700">
                                {{ $activeLink->project?->name ?? 'Unknown Project' }} •
                                {{ $requesterRoles[$activeLink->requester_role] ?? ucfirst($activeLink->requester_role) }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Expires: {{ $activeLink->expires_at?->format('Y-m-d H:i') }}
                            </p>
                            <a href="{{ route('service-tickets.public.create', $activeLink) }}" target="_blank" rel="noreferrer" class="mt-1 inline-flex text-xs font-semibold text-cyan-700 underline">
                                Open Link
                            </a>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No active one-time links yet.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>

    <script>
        const copyButton = document.getElementById('copyPublicLinkBtn');
        const linkInput = document.getElementById('generatedPublicLinkInput');

        if (copyButton && linkInput) {
            copyButton.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(linkInput.value);
                    copyButton.textContent = 'Copied';
                    setTimeout(() => {
                        copyButton.textContent = 'Copy';
                    }, 1200);
                } catch (error) {
                    linkInput.select();
                    document.execCommand('copy');
                }
            });
        }
    </script>
</x-layouts.app>
