@php
    $activeSchema = $ticketSchemaByRole[$selectedRequesterRole] ?? [];
    $selectedProjectId = (int) old('project_id');
    $selectedProject = $isPublicForm
        ? $publicLink->project
        : $projects->firstWhere('id', $selectedProjectId);
    $shouldShowTicketTerms = $selectedProject?->requiresTicketTermsAcceptance() ?? false;
@endphp

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="mt-3 grid gap-3">
    @csrf

    @if ($isPublicForm)
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
            <p><span class="font-bold">Project:</span> {{ $publicLink->project?->name ?? 'Unknown Project' }}</p>
            <p class="mt-1">
                <span class="font-bold">Requester Type:</span>
                {{ $requesterRoles[$selectedRequesterRole] ?? ucfirst($selectedRequesterRole) }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Link expires at: {{ $publicLink->expires_at?->format('Y-m-d H:i') }}
            </p>
        </div>
    @else
        <label class="grid gap-1 text-sm font-semibold">
            Project
            <select id="ticketProjectSelect" name="project_id" class="rounded-lg px-3 py-2 text-sm" required>
                <option value="">Select project</option>
                @foreach ($projects as $project)
                    <option
                        value="{{ $project->id }}"
                        data-project-requires-terms="{{ $project->requiresTicketTermsAcceptance() ? '1' : '0' }}"
                        @selected((int) old('project_id') === (int) $project->id)
                    >
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>
            @error('project_id')
                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
            @enderror
        </label>

        @if ($canSelectRequesterRole)
            <label class="grid gap-1 text-sm font-semibold">
                Requester Type
                <select id="requesterRoleSelect" name="requester_role" class="rounded-lg px-3 py-2 text-sm" required>
                    @foreach ($requesterRoles as $roleKey => $roleLabel)
                        <option value="{{ $roleKey }}" @selected(old('requester_role', $selectedRequesterRole) === $roleKey)>{{ $roleLabel }}</option>
                    @endforeach
                </select>
                @error('requester_role')
                    <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                @enderror
            </label>
        @else
            <input type="hidden" name="requester_role" value="{{ $selectedRequesterRole }}" />
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                Requester Type: <strong>{{ $requesterRoles[$selectedRequesterRole] ?? ucfirst($selectedRequesterRole) }}</strong>
            </div>
        @endif
    @endif

    @foreach ($ticketFieldDefinitions as $fieldKey => $fieldDefinition)
        @php
            $fieldConfig = $activeSchema[$fieldKey] ?? ['enabled' => false, 'required' => false];
            $isEnabled = (bool) ($fieldConfig['enabled'] ?? false);
            $isRequired = $isEnabled && (bool) ($fieldConfig['required'] ?? false);
            $fieldType = (string) ($fieldDefinition['type'] ?? 'text');
            $isBuiltIn = (bool) ($fieldDefinition['builtin'] ?? false);
            $fieldLabel = (string) ($fieldDefinition['label'] ?? ucfirst($fieldKey));
            $inputName = $isBuiltIn
                ? ($fieldKey === 'photo' ? 'photos[]' : $fieldKey)
                : "custom_fields[{$fieldKey}]";
            $errorKey = $isBuiltIn ? $fieldKey : "custom_fields.{$fieldKey}";
            $oldValue = old($errorKey);
            $inputId = $fieldKey === 'photo'
                ? 'ticketPhotosInput'
                : 'ticketFieldInput_'.preg_replace('/[^a-zA-Z0-9_]/', '_', $fieldKey);
        @endphp

        <label class="grid gap-1 text-sm font-semibold" data-ticket-field="{{ $fieldKey }}" @if (! $isEnabled) style="display:none" @endif>
            {{ $fieldLabel }}

            @if ($fieldType === 'photo')
                <input
                    type="file"
                    name="photos[]"
                    id="ticketPhotosInput"
                    data-ticket-input="{{ $fieldKey }}"
                    class="rounded-lg px-3 py-2 text-sm"
                    accept=".pdf,.xls,.xlsx,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp,.bmp"
                    multiple
                    @required($isRequired)
                />
                <span class="text-xs text-slate-500">Allowed files: PDF, Excel, Word, and photo files only.</span>
                @error('photos')
                    <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                @enderror
                @error('photos.*')
                    <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                @enderror

                <div id="ticketPhotoPreview" class="mt-2 grid grid-cols-2 gap-2 md:grid-cols-3"></div>
            @elseif ($fieldType === 'textarea')
                <textarea
                    name="{{ $inputName }}"
                    id="{{ $inputId }}"
                    data-ticket-input="{{ $fieldKey }}"
                    rows="4"
                    class="rounded-lg px-3 py-2 text-sm"
                    @required($isRequired)
                >{{ is_string($oldValue) ? $oldValue : '' }}</textarea>
                @error($errorKey)
                    <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                @enderror
            @else
                @php
                    $inputType = in_array($fieldType, ['number', 'date'], true) ? $fieldType : 'text';
                    $inputValue = is_scalar($oldValue) ? (string) $oldValue : '';
                @endphp
                <input
                    type="{{ $inputType }}"
                    name="{{ $inputName }}"
                    id="{{ $inputId }}"
                    data-ticket-input="{{ $fieldKey }}"
                    value="{{ $inputValue }}"
                    class="rounded-lg px-3 py-2 text-sm"
                    @if ($inputType === 'number') step="any" @endif
                    @required($isRequired)
                />
                @error($errorKey)
                    <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                @enderror
            @endif
        </label>
    @endforeach

    <div id="onCallTermsWrapper" class="rounded-xl border border-slate-300 bg-slate-50 p-4 text-slate-900 shadow-sm" @if (! $shouldShowTicketTerms) style="display:none" @endif>
        <label for="onCallTermsAccepted" class="flex items-start gap-3">
            <input
                type="checkbox"
                name="on_call_terms_accepted"
                id="onCallTermsAccepted"
                value="1"
                class="mt-1 h-5 w-5 rounded border-slate-400 bg-white text-cyan-600 focus:ring-cyan-500"
                @checked(old('on_call_terms_accepted'))
                @required($shouldShowTicketTerms)
            />
            <span class="grid gap-1.5">
                <span class="text-base font-bold tracking-tight text-slate-950">Terms and Conditions</span>
                <span class="text-sm font-normal leading-7 text-slate-800">
                    {{ $ticketTermsText }}
                </span>
            </span>
        </label>
        @error('on_call_terms_accepted')
            <span class="mt-2 block text-xs font-medium text-red-600">{{ $message }}</span>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary rounded-lg px-3 py-2 text-sm font-bold text-white">
        {{ $isPublicForm ? 'Submit via One-Time Link' : 'Submit Ticket' }}
    </button>
</form>

<script>
    (() => {
        const ticketSchemaByRole = @json($ticketSchemaByRole);
        const selectedRequesterRole = @json($selectedRequesterRole);
        const roleSelector = document.getElementById('requesterRoleSelect');
        const projectSelector = document.getElementById('ticketProjectSelect');
        const photosInput = document.getElementById('ticketPhotosInput');
        const previewContainer = document.getElementById('ticketPhotoPreview');
        const onCallTermsWrapper = document.getElementById('onCallTermsWrapper');
        const onCallTermsCheckbox = document.getElementById('onCallTermsAccepted');
        const fieldWrappers = Array.from(document.querySelectorAll('[data-ticket-field]'));
        const isPublicTermsProject = @json($shouldShowTicketTerms);

        const applySchema = (roleKey) => {
            const schema = ticketSchemaByRole[roleKey] || {};

            fieldWrappers.forEach((wrapper) => {
                const fieldKey = wrapper.getAttribute('data-ticket-field') || '';
                const enabled = !!schema[fieldKey]?.enabled;
                const required = enabled && !!schema[fieldKey]?.required;
                const input = wrapper.querySelector('[data-ticket-input]');

                if (!input) {
                    return;
                }

                wrapper.style.display = enabled ? '' : 'none';
                input.required = required;

                if (!enabled && input.type === 'file') {
                    input.value = '';
                    if (previewContainer) {
                        previewContainer.innerHTML = '';
                    }
                }
            });
        };

        const renderPhotoPreview = () => {
            if (!previewContainer || !photosInput) {
                return;
            }

            previewContainer.innerHTML = '';

            const files = Array.from(photosInput.files || []);

            files.forEach((file) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'rounded-lg border border-slate-200 bg-slate-50 p-1';

                const caption = document.createElement('p');
                caption.className = 'mt-1 truncate text-[11px] text-slate-600';
                caption.textContent = file.name;

                if (file.type.startsWith('image/')) {
                    const image = document.createElement('img');
                    image.className = 'h-24 w-full rounded-md object-cover';
                    image.alt = file.name;

                    const objectUrl = URL.createObjectURL(file);
                    image.src = objectUrl;
                    image.onload = () => URL.revokeObjectURL(objectUrl);

                    wrapper.appendChild(image);
                } else {
                    const badge = document.createElement('div');
                    badge.className = 'flex h-24 items-center justify-center rounded-md border border-slate-200 bg-white text-xs font-semibold text-slate-600';
                    badge.textContent = 'Document';
                    wrapper.appendChild(badge);
                }

                wrapper.appendChild(caption);
                previewContainer.appendChild(wrapper);
            });
        };

        const syncOnCallTerms = () => {
            if (!onCallTermsWrapper || !onCallTermsCheckbox) {
                return;
            }

            let shouldShow = isPublicTermsProject;

            if (projectSelector) {
                const selectedOption = projectSelector.options[projectSelector.selectedIndex];
                shouldShow = selectedOption?.dataset.projectRequiresTerms === '1';
            }

            onCallTermsWrapper.style.display = shouldShow ? '' : 'none';
            onCallTermsCheckbox.required = shouldShow;

            if (!shouldShow) {
                onCallTermsCheckbox.checked = false;
            }
        };

        if (roleSelector) {
            roleSelector.addEventListener('change', (event) => {
                applySchema(event.target.value);
            });
        }

        if (projectSelector) {
            projectSelector.addEventListener('change', syncOnCallTerms);
        }

        applySchema(roleSelector ? roleSelector.value : selectedRequesterRole);
        syncOnCallTerms();

        if (photosInput) {
            photosInput.addEventListener('change', renderPhotoPreview);
            renderPhotoPreview();
        }
    })();
</script>
