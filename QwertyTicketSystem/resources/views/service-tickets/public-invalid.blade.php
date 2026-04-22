<x-layouts.app :title="'Public Link Invalid | Qwerty Ticket System'">
    <main class="mx-auto w-full max-w-xl p-4">
        <article class="panel rounded-2xl border bg-white p-5 text-center">
            <h1 class="text-2xl font-extrabold tracking-tight">Link Not Available</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $message }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ $helpText ?? 'Please request a new one-time link from the service desk team.' }}</p>
        </article>
    </main>
</x-layouts.app>
