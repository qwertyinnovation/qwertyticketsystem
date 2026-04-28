<x-layouts.app :title="'Settings | Qwerty Ticket System'">
    <div class="app-shell app-shell-dashboard">
        @include('partials.service-desk-sidebar', ['activeMenu' => 'settings', 'currentUser' => $currentUser])

        <section class="grid gap-4">
            @include('partials.alerts')

            <header class="panel rounded-2xl border p-4">
                <h1 class="text-2xl font-extrabold tracking-tight">Service Desk Settings</h1>
                <p class="mt-1 text-sm text-slate-600">Admin/PM can customize project dropdown values and requester form fields.</p>
            </header>

            <form method="POST" action="{{ route('settings.update') }}" class="grid gap-4">
                @csrf
                @method('PUT')

                <article class="panel rounded-2xl border bg-white p-4">
                    <h2 class="text-lg font-bold">Project Form Options</h2>
                    <p class="mt-1 text-sm text-slate-600">Dropdown values for Project forms. One value per line.</p>

                    <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        Blank lines are ignored and duplicate values are removed automatically when saving.
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                        <label class="grid gap-2 text-sm font-semibold">
                            <div class="flex items-center justify-between gap-2">
                                <span>Categories</span>
                                <span class="badge rounded-full px-2 py-1 text-xs" data-count-for="project_categories">0 items</span>
                            </div>
                            <textarea
                                name="project_categories"
                                rows="9"
                                class="rounded-lg px-3 py-2 text-sm"
                                placeholder="IT Support&#10;Software Development&#10;Operations"
                                data-line-counter="project_categories"
                                required
                            >{{ old('project_categories', implode("\n", $projectOptions['categories'])) }}</textarea>
                            <p class="text-xs font-normal text-slate-500">Used in the Category dropdown.</p>
                            @error('project_categories')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid gap-2 text-sm font-semibold">
                            <div class="flex items-center justify-between gap-2">
                                <span>Service Types</span>
                                <span class="badge rounded-full px-2 py-1 text-xs" data-count-for="project_service_types">0 items</span>
                            </div>
                            <textarea
                                name="project_service_types"
                                rows="9"
                                class="rounded-lg px-3 py-2 text-sm"
                                placeholder="Onsite&#10;Remote&#10;Managed Service"
                                data-line-counter="project_service_types"
                                required
                            >{{ old('project_service_types', implode("\n", $projectOptions['service_types'])) }}</textarea>
                            <p class="text-xs font-normal text-slate-500">Used in the Service Type dropdown.</p>
                            @error('project_service_types')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid gap-2 text-sm font-semibold">
                            <div class="flex items-center justify-between gap-2">
                                <span>Statuses</span>
                                <span class="badge rounded-full px-2 py-1 text-xs" data-count-for="project_statuses">0 items</span>
                            </div>
                            <textarea
                                name="project_statuses"
                                rows="9"
                                class="rounded-lg px-3 py-2 text-sm"
                                placeholder="Active&#10;Pending&#10;Closed"
                                data-line-counter="project_statuses"
                                required
                            >{{ old('project_statuses', implode("\n", $projectOptions['statuses'])) }}</textarea>
                            <p class="text-xs font-normal text-slate-500">Used in the Status dropdown.</p>
                            @error('project_statuses')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>
                </article>

                <article class="panel rounded-2xl border bg-white p-4">
                    <h2 class="text-lg font-bold">One-Time Link Expiry</h2>
                    <p class="mt-1 text-sm text-slate-600">Set how many minutes public one-time submit links remain valid.</p>

                    <label class="mt-3 grid max-w-sm gap-1 text-sm font-semibold">
                        Expiry Minutes
                        <input
                            type="number"
                            name="public_link_expiry_minutes"
                            min="1"
                            max="10080"
                            value="{{ old('public_link_expiry_minutes', $publicLinkExpiryMinutes) }}"
                            class="rounded-lg px-3 py-2 text-sm"
                            required
                        />
                        @error('public_link_expiry_minutes')
                            <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                </article>

                <article class="panel rounded-2xl border bg-white p-4">
                    <h2 class="text-lg font-bold">Terms and Conditions</h2>
                    <p class="mt-1 text-sm text-slate-600">Choose which project service types require a terms checkbox and customize the message shown in the ticket form.</p>

                    <label class="mt-3 grid gap-2 text-sm font-semibold">
                        Service Types Requiring Terms Acceptance
                        <textarea
                            name="ticket_terms_trigger_service_types"
                            rows="4"
                            class="rounded-lg px-3 py-2 text-sm"
                            placeholder="On Call&#10;Billable Support&#10;Emergency Visit"
                        >{{ old('ticket_terms_trigger_service_types', implode("\n", $ticketTermsTriggerServiceTypes)) }}</textarea>
                        <p class="text-xs font-normal text-slate-500">One project service type per line. When a project uses one of these service types, users must accept the terms before submitting a ticket.</p>
                        @error('ticket_terms_trigger_service_types')
                            <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="mt-3 grid gap-2 text-sm font-semibold">
                        Agreement Text
                        <textarea
                            name="ticket_terms_text"
                            rows="4"
                            class="rounded-lg px-3 py-2 text-sm"
                            required
                        >{{ old('ticket_terms_text', $ticketTermsText) }}</textarea>
                        <p class="text-xs font-normal text-slate-500">This text appears beside the required checkbox in the ticket form.</p>
                        @error('ticket_terms_text')
                            <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                </article>

                <article class="panel rounded-2xl border bg-white p-4">
                    <h2 class="text-lg font-bold">Ticket Request Form Builder</h2>
                    <p class="mt-1 text-sm text-slate-600">Configure built-in and custom fields for each requester type.</p>

                    @php
                        $editableCustomFields = old('custom_fields', $ticketCustomFields);
                        $editableCustomFields = is_array($editableCustomFields) ? $editableCustomFields : [];
                    @endphp

                    <section class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-bold">Custom Fields</h3>
                                <p class="text-xs text-slate-600">Add extra fields that should appear in the ticket request form.</p>
                            </div>
                            <button
                                type="button"
                                id="addCustomFieldButton"
                                class="btn rounded-lg border border-cyan-300 bg-cyan-50 px-3 py-2 text-xs font-bold text-cyan-700"
                            >
                                Add Field
                            </button>
                        </div>

                        <div id="customFieldList" class="mt-3 grid gap-2" data-next-index="{{ count($editableCustomFields) }}">
                            @foreach ($editableCustomFields as $index => $customField)
                                @php
                                    $customField = is_array($customField) ? $customField : [];
                                    $customFieldKey = (string) ($customField['key'] ?? '');
                                    $customFieldLabel = (string) ($customField['label'] ?? '');
                                    $customFieldType = (string) ($customField['type'] ?? 'text');
                                @endphp
                                <div class="rounded-lg border border-slate-200 bg-white p-3" data-custom-field-row>
                                    <input type="hidden" name="custom_fields[{{ $index }}][key]" value="{{ $customFieldKey }}" data-custom-field-key />

                                    <div class="grid gap-2 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
                                        <label class="grid gap-1 text-xs font-semibold">
                                            Label
                                            <input
                                                type="text"
                                                name="custom_fields[{{ $index }}][label]"
                                                value="{{ $customFieldLabel }}"
                                                class="rounded-lg px-3 py-2 text-sm"
                                                placeholder="Example: Contact Phone"
                                            />
                                        </label>

                                        <label class="grid gap-1 text-xs font-semibold">
                                            Field Type
                                            <select name="custom_fields[{{ $index }}][type]" class="rounded-lg px-3 py-2 text-sm">
                                                @foreach ($ticketCustomFieldTypeLabels as $typeKey => $typeLabel)
                                                    <option value="{{ $typeKey }}" @selected($customFieldType === $typeKey)>{{ $typeLabel }}</option>
                                                @endforeach
                                            </select>
                                        </label>

                                        <button
                                            type="button"
                                            class="btn self-end rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700"
                                            data-remove-custom-field
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <p id="customFieldEmptyState" class="mt-2 text-xs text-slate-500 @if (count($editableCustomFields) > 0) hidden @endif">
                            No custom fields yet. Click "Add Field" to create one.
                        </p>

                        @error('custom_fields')
                            <span class="mt-2 block text-xs font-medium text-red-600">{{ $message }}</span>
                        @enderror
                    </section>

                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        @foreach ($roleLabels as $roleKey => $roleLabel)
                            <section class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <h3 class="text-sm font-bold">{{ $roleLabel }}</h3>
                                <div class="mt-2 grid gap-3">
                                    @foreach ($ticketFieldDefinitions as $fieldKey => $fieldDefinition)
                                        @php
                                            $defaultEnabled = (bool) ($ticketSchema[$roleKey][$fieldKey]['enabled'] ?? false);
                                            $defaultRequired = (bool) ($ticketSchema[$roleKey][$fieldKey]['required'] ?? false);
                                            $enabled = old("schema.{$roleKey}.{$fieldKey}.enabled") !== null
                                                ? (bool) old("schema.{$roleKey}.{$fieldKey}.enabled")
                                                : $defaultEnabled;
                                            $required = old("schema.{$roleKey}.{$fieldKey}.required") !== null
                                                ? (bool) old("schema.{$roleKey}.{$fieldKey}.required")
                                                : $defaultRequired;
                                        @endphp
                                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                                            <p class="text-sm font-semibold">{{ $fieldDefinition['label'] }}</p>
                                            <p class="text-xs text-slate-500">
                                                {{ ($fieldDefinition['builtin'] ?? false) ? 'Built-in field' : 'Custom field' }}
                                                ({{ $ticketCustomFieldTypeLabels[$fieldDefinition['type']] ?? ucfirst($fieldDefinition['type']) }})
                                            </p>

                                            <label class="mt-2 flex items-center gap-2 text-sm">
                                                <input
                                                    type="hidden"
                                                    name="schema[{{ $roleKey }}][{{ $fieldKey }}][enabled]"
                                                    value="0"
                                                />
                                                <input
                                                    type="checkbox"
                                                    class="rounded schema-enabled-checkbox"
                                                    name="schema[{{ $roleKey }}][{{ $fieldKey }}][enabled]"
                                                    value="1"
                                                    @checked($enabled)
                                                />
                                                Enable field
                                            </label>

                                            <label class="mt-1 flex items-center gap-2 text-sm">
                                                <input
                                                    type="hidden"
                                                    name="schema[{{ $roleKey }}][{{ $fieldKey }}][required]"
                                                    value="0"
                                                />
                                                <input
                                                    type="checkbox"
                                                    class="rounded schema-required-checkbox"
                                                    name="schema[{{ $roleKey }}][{{ $fieldKey }}][required]"
                                                    value="1"
                                                    @checked($required)
                                                />
                                                Make required
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </article>

                <div>
                    <button type="submit" class="btn btn-primary rounded-lg px-4 py-2 text-sm font-bold text-white">Save Settings</button>
                </div>
            </form>

            <article class="panel rounded-2xl border p-4">
                <h2 class="text-xl font-extrabold">Usage</h2>
                <ul class="mt-2 list-disc space-y-2 pl-5 text-sm text-slate-600">
                    <li>Project dropdowns immediately use these settings values.</li>
                    <li>Ticket form auto-adjusts for Client, Internal Staff, and Third-Party Provider.</li>
                    <li>If a field is disabled, users in that requester type won’t see it.</li>
                    <li>One-time link expiry controls how long public form links stay valid.</li>
                </ul>
            </article>
        </section>
    </div>

    <template id="customFieldTemplate">
        <div class="rounded-lg border border-slate-200 bg-white p-3" data-custom-field-row>
            <input type="hidden" name="custom_fields[__INDEX__][key]" value="" data-custom-field-key />

            <div class="grid gap-2 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
                <label class="grid gap-1 text-xs font-semibold">
                    Label
                    <input
                        type="text"
                        name="custom_fields[__INDEX__][label]"
                        class="rounded-lg px-3 py-2 text-sm"
                        placeholder="Example: Contact Phone"
                    />
                </label>

                <label class="grid gap-1 text-xs font-semibold">
                    Field Type
                    <select name="custom_fields[__INDEX__][type]" class="rounded-lg px-3 py-2 text-sm">
                        @foreach ($ticketCustomFieldTypeLabels as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </label>

                <button
                    type="button"
                    class="btn self-end rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700"
                    data-remove-custom-field
                >
                    Remove
                </button>
            </div>
        </div>
    </template>

    <script>
        document.querySelectorAll('[data-line-counter]').forEach((textarea) => {
            const countTarget = document.querySelector(`[data-count-for="${textarea.name}"]`);

            if (!countTarget) {
                return;
            }

            const updateCount = () => {
                const uniqueValues = new Set(
                    textarea.value
                        .split(/\r\n|\r|\n/)
                        .map((line) => line.trim())
                        .filter((line) => line !== '')
                );

                const count = uniqueValues.size;
                countTarget.textContent = `${count} item${count === 1 ? '' : 's'}`;
            };

            textarea.addEventListener('input', updateCount);
            updateCount();
        });

        document.querySelectorAll('.schema-enabled-checkbox').forEach((enabledCheckbox) => {
            const container = enabledCheckbox.closest('.rounded-lg');
            const requiredCheckbox = container?.querySelector('.schema-required-checkbox');

            if (!requiredCheckbox) {
                return;
            }

            const applyState = () => {
                requiredCheckbox.disabled = !enabledCheckbox.checked;

                if (!enabledCheckbox.checked) {
                    requiredCheckbox.checked = false;
                }
            };

            enabledCheckbox.addEventListener('change', applyState);
            applyState();
        });

        const customFieldList = document.getElementById('customFieldList');
        const customFieldTemplate = document.getElementById('customFieldTemplate');
        const addCustomFieldButton = document.getElementById('addCustomFieldButton');
        const customFieldEmptyState = document.getElementById('customFieldEmptyState');
        let nextCustomFieldIndex = Number(customFieldList?.dataset.nextIndex || 0);

        const updateCustomFieldEmptyState = () => {
            if (!customFieldList || !customFieldEmptyState) {
                return;
            }

            const hasRows = customFieldList.querySelector('[data-custom-field-row]') !== null;
            customFieldEmptyState.classList.toggle('hidden', hasRows);
        };

        const bindRemoveButton = (row) => {
            const removeButton = row.querySelector('[data-remove-custom-field]');

            if (!removeButton) {
                return;
            }

            removeButton.addEventListener('click', () => {
                row.remove();
                updateCustomFieldEmptyState();
            });
        };

        if (customFieldList) {
            customFieldList.querySelectorAll('[data-custom-field-row]').forEach((row) => {
                bindRemoveButton(row);
            });
        }

        if (addCustomFieldButton && customFieldList && customFieldTemplate) {
            addCustomFieldButton.addEventListener('click', () => {
                const html = customFieldTemplate.innerHTML.replace(/__INDEX__/g, String(nextCustomFieldIndex));
                nextCustomFieldIndex += 1;
                customFieldList.insertAdjacentHTML('beforeend', html);

                const newRow = customFieldList.lastElementChild;

                if (newRow) {
                    bindRemoveButton(newRow);
                    const firstInput = newRow.querySelector('input[type="text"]');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }

                updateCustomFieldEmptyState();
            });
        }

        updateCustomFieldEmptyState();
    </script>
</x-layouts.app>
