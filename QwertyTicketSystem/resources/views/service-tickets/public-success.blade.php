<x-layouts.app :title="'Ticket Submitted | Qwerty Ticket System'">
    <main class="mx-auto w-full max-w-xl p-4">
        <article class="panel rounded-2xl border bg-white p-5 text-center">
            <h1 class="text-2xl font-extrabold tracking-tight">Ticket Submitted</h1>
            <p class="mt-2 text-sm text-slate-600">
                Your {{ strtolower($requesterRoleLabel) }} ticket has been submitted successfully.
            </p>
            <p class="mt-2 text-xs text-slate-500">
                Ticket ID: #{{ $ticket->id }}. This one-time link is now closed.
            </p>

            <div class="mt-4 rounded-xl border border-cyan-200 bg-cyan-50 p-4 text-left">
                <p class="text-xs font-bold uppercase text-cyan-800">Public Tracker Link</p>
                <p class="mt-1 text-sm text-cyan-900">
                    Use this read-only link to check the service ticket status and response history later.
                </p>

                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                    <input
                        id="trackingLinkInput"
                        type="text"
                        readonly
                        value="{{ $trackingLink }}"
                        class="w-full rounded-lg border border-cyan-200 bg-white px-3 py-2 text-xs text-cyan-900"
                    />
                    <button type="button" id="copyTrackingLinkBtn" class="btn rounded-lg border border-cyan-300 bg-cyan-100 px-3 py-2 text-xs font-bold text-cyan-800 sm:w-auto">
                        Copy
                    </button>
                </div>

                <a href="{{ $trackingLink }}" class="mt-3 inline-flex text-sm font-semibold text-cyan-700 underline">
                    Open Public Tracker
                </a>
            </div>
        </article>
    </main>

    <script>
        const copyButton = document.getElementById('copyTrackingLinkBtn');
        const linkInput = document.getElementById('trackingLinkInput');

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
