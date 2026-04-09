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
        </article>
    </main>
</x-layouts.app>
