<x-layouts.app :title="'Public Service Ticket Form | Qwerty Ticket System'">
    <main class="mx-auto w-full max-w-3xl p-4">
        @include('partials.alerts')

        <article class="panel rounded-2xl border bg-white p-4">
            <h1 class="text-2xl font-extrabold tracking-tight">Service Ticket Public Form</h1>
            <p class="mt-1 text-sm text-slate-600">This is a one-time submission link. After successful submit, this link becomes invalid.</p>

            @include('service-tickets.partials.submit-form', [
                'formAction' => $formAction,
                'isPublicForm' => $isPublicForm,
                'projects' => collect(),
                'requesterRoles' => $requesterRoles,
                'selectedRequesterRole' => $selectedRequesterRole,
                'ticketSchemaByRole' => $ticketSchemaByRole,
                'ticketFieldDefinitions' => $ticketFieldDefinitions,
                'ticketTermsText' => $ticketTermsText,
                'canSelectRequesterRole' => false,
                'publicLink' => $publicLink,
            ])
        </article>
    </main>
</x-layouts.app>
