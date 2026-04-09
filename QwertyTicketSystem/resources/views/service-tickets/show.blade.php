<x-layouts.app :title="'Service Ticket Details | Qwerty Ticket System'">
    @php
        $customFieldDefinitions = collect($ticketFieldDefinitions ?? [])->filter(
            fn (array $definition): bool => ! ($definition['builtin'] ?? false)
        );
        $customFieldValues = collect($serviceTicket->custom_fields ?? []);
    @endphp

    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'service-tickets', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <article class="panel rounded-2xl border bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight">Ticket #{{ $serviceTicket->id }}</h1>
                        <p class="mt-1 text-sm text-slate-600">{{ $serviceTicket->project?->name ?? 'Unknown Project' }} • {{ $requesterRoles[$serviceTicket->requester_role] ?? ucfirst($serviceTicket->requester_role) }}</p>
                    </div>
                    <span class="badge rounded-full px-2 py-1 text-xs">{{ $serviceTicket->status }}</span>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-bold uppercase text-slate-500">Submitted By</p>
                        <p class="mt-1 text-sm font-semibold">{{ $serviceTicket->submittedBy?->name ?? 'Unknown' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-bold uppercase text-slate-500">Created At</p>
                        <p class="mt-1 text-sm font-semibold">{{ $serviceTicket->created_at?->format('Y-m-d H:i') }}</p>
                    </div>
                </div>

                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-bold uppercase text-slate-500">Title</p>
                    <p class="mt-1 text-sm text-slate-700">{{ $serviceTicket->title ?: 'No title provided.' }}</p>
                </div>

                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-bold uppercase text-slate-500">Description</p>
                    <p class="mt-1 text-sm text-slate-700 whitespace-pre-line">{{ $serviceTicket->description ?: 'No description provided.' }}</p>
                </div>

                @if ($customFieldDefinitions->isNotEmpty())
                    <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-bold uppercase text-slate-500">Additional Fields</p>

                        @php
                            $filledCustomFields = $customFieldDefinitions->filter(function (array $definition, string $fieldKey) use ($customFieldValues): bool {
                                $value = $customFieldValues->get($fieldKey);

                                return $value !== null && $value !== '';
                            });
                        @endphp

                        @if ($filledCustomFields->isEmpty())
                            <p class="mt-1 text-sm text-slate-700">No additional fields submitted.</p>
                        @else
                            <div class="mt-2 grid gap-2 md:grid-cols-2">
                                @foreach ($filledCustomFields as $fieldKey => $definition)
                                    <div class="rounded-lg border border-slate-200 bg-white p-2">
                                        <p class="text-xs font-bold uppercase text-slate-500">{{ $definition['label'] }}</p>
                                        <p class="mt-1 text-sm text-slate-700 whitespace-pre-line">{{ $customFieldValues->get($fieldKey) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-bold uppercase text-slate-500">Photos</p>
                    @if ($serviceTicket->photos->isNotEmpty())
                        <div class="mt-2 grid grid-cols-2 gap-2 md:grid-cols-3">
                            @foreach ($serviceTicket->photos as $photo)
                                <a href="{{ asset('storage/'.$photo->photo_path) }}" target="_blank" rel="noreferrer" class="block rounded-lg border border-slate-200 bg-white p-1">
                                    <img src="{{ asset('storage/'.$photo->photo_path) }}" alt="Ticket photo" class="h-28 w-full rounded-md object-cover" />
                                </a>
                            @endforeach
                        </div>
                    @elseif ($serviceTicket->screenshot_path)
                        <a href="{{ asset('storage/'.$serviceTicket->screenshot_path) }}" target="_blank" rel="noreferrer" class="mt-2 inline-flex text-sm font-semibold text-cyan-700 underline">
                            Open Screenshot
                        </a>
                        <img src="{{ asset('storage/'.$serviceTicket->screenshot_path) }}" alt="Ticket screenshot" class="mt-3 max-h-72 rounded-lg border border-slate-200 object-contain" />
                    @else
                        <p class="mt-1 text-sm text-slate-700">No photos uploaded.</p>
                    @endif
                </div>

                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-bold uppercase text-slate-500">Response</p>
                    <p class="mt-1 text-sm text-slate-700 whitespace-pre-line">{{ $serviceTicket->response_description ?: 'No response yet.' }}</p>
                    @if ($serviceTicket->response_photo_path)
                        <a href="{{ asset('storage/'.$serviceTicket->response_photo_path) }}" target="_blank" rel="noreferrer" class="mt-2 inline-flex text-sm font-semibold text-cyan-700 underline">
                            Open Response Photo
                        </a>
                    @endif
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('service-tickets.index') }}" class="btn rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700">Back to Tickets</a>
                </div>
            </article>

            @if ($canManageTicket)
                <article class="panel rounded-2xl border bg-white p-4">
                    <h2 class="text-lg font-bold">Update Response</h2>
                    <p class="mt-1 text-sm text-slate-600">Admin/PM can update ticket status and response details.</p>

                    <form method="POST" action="{{ route('service-tickets.response.update', $serviceTicket) }}" enctype="multipart/form-data" class="mt-3 grid gap-3">
                        @csrf
                        @method('PUT')

                        <label class="grid gap-1 text-sm font-semibold">
                            Status
                            <select name="status" class="rounded-lg px-3 py-2 text-sm" required>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(old('status', $serviceTicket->status) === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold">
                            Response Description
                            <textarea name="response_description" rows="4" class="rounded-lg px-3 py-2 text-sm">{{ old('response_description', $serviceTicket->response_description) }}</textarea>
                            @error('response_description')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold">
                            Response Photo
                            <input type="file" name="response_photo" class="rounded-lg px-3 py-2 text-sm" accept="image/*" />
                            @error('response_photo')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <button type="submit" class="btn btn-primary rounded-lg px-3 py-2 text-sm font-bold text-white">Save Response</button>
                    </form>
                </article>
            @endif

            @if ($canDeleteTicket)
                <form method="POST" action="{{ route('service-tickets.destroy', $serviceTicket) }}">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="btn rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-bold text-red-700"
                        onclick="return confirm('Delete ticket #{{ $serviceTicket->id }}?');"
                    >
                        Delete Ticket
                    </button>
                </form>
            @endif
        </section>
    </div>
</x-layouts.app>
