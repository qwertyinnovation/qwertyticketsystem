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
                <form method="POST" action="{{ route('service-tickets.public-links.bulk-destroy') }}" class="mt-2" data-bulk-delete-form data-bulk-confirm="Delete selected one-time links?">
                    @csrf
                    @method('DELETE')

                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <label class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.06em] text-slate-500">
                            <input type="checkbox" class="rounded" data-bulk-select-all aria-label="Select all active one-time links" />
                            <span data-bulk-selected-count>0 selected</span>
                        </label>
                        <button type="submit" class="btn rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700 disabled:cursor-not-allowed disabled:opacity-50" data-bulk-submit disabled>
                            Delete Selected
                        </button>
                    </div>

                    <div class="space-y-2">
                        @forelse ($activePublicLinks as $activeLink)
                            <div class="flex gap-3 rounded-lg border border-slate-200 bg-slate-50 p-2">
                                <input type="checkbox" name="selected_ids[]" value="{{ $activeLink->id }}" class="mt-1 rounded" data-bulk-select-row aria-label="Select one-time link for {{ $activeLink->project?->name ?? 'unknown project' }}" />
                                <div class="min-w-0">
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
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No active one-time links yet.</p>
                        @endforelse
                    </div>
                </form>
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
