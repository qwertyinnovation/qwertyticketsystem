<x-layouts.app :title="'Submit Service Ticket | Qwerty Ticket System'">
    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'service-tickets', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <article class="panel rounded-2xl border bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight">Submit Service Ticket</h1>
                        <p class="mt-1 text-sm text-slate-600">Fill required fields based on requester type and submit.</p>
                    </div>
                    <a href="{{ route('service-tickets.index') }}" class="btn rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700">
                        Back to Tickets
                    </a>
                </div>

                @include('service-tickets.partials.submit-form', [
                    'formAction' => $formAction,
                    'isPublicForm' => $isPublicForm,
                    'projects' => $projects,
                    'requesterRoles' => $requesterRoles,
                    'selectedRequesterRole' => $selectedRequesterRole,
                    'ticketSchemaByRole' => $ticketSchemaByRole,
                    'ticketFieldDefinitions' => $ticketFieldDefinitions,
                    'ticketTermsText' => $ticketTermsText,
                    'canSelectRequesterRole' => $canSelectRequesterRole,
                    'publicLink' => $publicLink,
                ])
            </article>
        </section>
    </div>
</x-layouts.app>
