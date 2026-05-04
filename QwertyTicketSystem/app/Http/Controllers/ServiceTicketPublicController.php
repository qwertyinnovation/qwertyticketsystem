<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ServiceDeskSetting;
use App\Models\ServiceTicket;
use App\Models\ServiceTicketPhoto;
use App\Models\ServiceTicketPublicLink;
use App\Services\ServiceTicketNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServiceTicketPublicController extends Controller
{
    public function __construct(private readonly ServiceTicketNotificationService $ticketNotifications)
    {
    }

    public function show(string $token): View|\Illuminate\Http\Response
    {
        $serviceTicket = ServiceTicket::query()
            ->where('public_tracking_token', $token)
            ->with(['project', 'submittedBy', 'photos', 'responses.respondedBy'])
            ->first();

        if (! $serviceTicket instanceof ServiceTicket) {
            return response()->view('service-tickets.public-invalid', [
                'message' => 'This public tracking link is not available.',
                'helpText' => 'Please request the latest tracking link from the service desk team.',
            ], 404);
        }

        return view('service-tickets.public-show', [
            'serviceTicket' => $serviceTicket,
            'requesterRoles' => ServiceTicket::requesterRoles(),
            'ticketFieldDefinitions' => ServiceDeskSetting::ticketFieldDefinitions(),
        ]);
    }

    public function create(ServiceTicketPublicLink $publicLink): View|\Illuminate\Http\Response
    {
        if (! $publicLink->isActive()) {
            return $this->invalidPublicLinkResponse($publicLink);
        }

        $publicLink->loadMissing('project');

        return view('service-tickets.public-create', [
            'publicLink' => $publicLink,
            'ticketSchemaByRole' => ServiceDeskSetting::ticketFormSchema(),
            'ticketFieldDefinitions' => ServiceDeskSetting::ticketFieldDefinitions(),
            'requesterRoles' => ServiceTicket::requesterRoles(),
            'selectedRequesterRole' => $publicLink->requester_role,
            'ticketTermsText' => ServiceDeskSetting::ticketTermsText(),
            'formAction' => route('service-tickets.public.store', $publicLink),
            'isPublicForm' => true,
        ]);
    }

    public function store(Request $request, ServiceTicketPublicLink $publicLink): View|\Illuminate\Http\Response
    {
        if (! $publicLink->isActive()) {
            return $this->invalidPublicLinkResponse($publicLink);
        }

        $publicLink->loadMissing('project');
        $schema = ServiceTicket::formSchemaForRole($publicLink->requester_role);
        $fieldDefinitions = ServiceDeskSetting::ticketFieldDefinitions();

        $rules = [];

        foreach ($this->ticketFieldRules($schema, $fieldDefinitions) as $key => $rule) {
            $rules[$key] = $rule;
        }

        $validated = $request->validate($rules);
        $this->validateTicketTerms($request, $publicLink->project);
        $customFieldValues = $this->extractCustomFieldValues($validated, $schema, $fieldDefinitions);

        $ticket = DB::transaction(function () use ($publicLink, $validated, $schema, $request, $customFieldValues): ?ServiceTicket {
            /** @var ServiceTicketPublicLink $lockedLink */
            $lockedLink = ServiceTicketPublicLink::query()
                ->whereKey($publicLink->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedLink->isActive()) {
                return null;
            }

            /** @var ServiceTicket $createdTicket */
            $createdTicket = ServiceTicket::query()->create([
                'project_id' => $lockedLink->project_id,
                'requester_role' => $lockedLink->requester_role,
                'submitted_by_user_id' => null,
                'title' => $this->resolveStringFieldValue($schema, $validated, 'title'),
                'description' => $this->resolveStringFieldValue($schema, $validated, 'description'),
                'custom_fields' => $customFieldValues !== [] ? $customFieldValues : null,
                'status' => ServiceTicket::statuses()[0],
                'public_tracking_token' => ServiceTicket::uniquePublicTrackingToken(),
            ]);

            if (($schema['photo']['enabled'] ?? false)) {
                $this->storeTicketPhotos($createdTicket, $request->file('photos', []));
            }

            $lockedLink->used_at = now();
            $lockedLink->used_by_ticket_id = $createdTicket->id;
            $lockedLink->save();

            return $createdTicket;
        });

        if (! $ticket instanceof ServiceTicket) {
            $latestLink = ServiceTicketPublicLink::query()->find($publicLink->id);

            if (! $latestLink instanceof ServiceTicketPublicLink) {
                return response()->view('service-tickets.public-invalid', [
                    'message' => 'This one-time link is no longer available.',
                ], 410);
            }

            return $this->invalidPublicLinkResponse($latestLink);
        }

        $this->ticketNotifications->ticketCreated($ticket);

        return view('service-tickets.public-success', [
            'ticket' => $ticket,
            'requesterRoleLabel' => ServiceTicket::requesterRoles()[$ticket->requester_role] ?? ucfirst($ticket->requester_role),
            'trackingLink' => route('service-tickets.public.track', $ticket->public_tracking_token),
        ]);
    }

    private function invalidPublicLinkResponse(ServiceTicketPublicLink $publicLink): \Illuminate\Http\Response
    {
        $message = $publicLink->isUsed()
            ? 'This one-time link has already been used.'
            : 'This one-time link is expired.';

        return response()->view('service-tickets.public-invalid', [
            'message' => $message,
        ], 410);
    }

    /**
     * @param  array{enabled: bool, required: bool}  $fieldConfig
     * @return array<int, string>
     */
    private function stringRules(array $fieldConfig, int $max): array
    {
        if (! $fieldConfig['enabled']) {
            return ['nullable'];
        }

        return [
            $fieldConfig['required'] ? 'required' : 'nullable',
            'string',
            'max:'.$max,
        ];
    }

    /**
     * @param  array{enabled: bool, required: bool}  $fieldConfig
     * @return array<int, string>
     */
    private function customFieldRules(array $fieldConfig, string $fieldType): array
    {
        if (! $fieldConfig['enabled']) {
            return ['nullable'];
        }

        $rules = [$fieldConfig['required'] ? 'required' : 'nullable'];

        if ($fieldType === 'number') {
            $rules[] = 'numeric';

            return $rules;
        }

        if ($fieldType === 'date') {
            $rules[] = 'date';

            return $rules;
        }

        $rules[] = 'string';
        $rules[] = $fieldType === 'textarea' ? 'max:5000' : 'max:255';

        return $rules;
    }

    /**
     * @param  array{enabled: bool, required: bool}  $fieldConfig
     * @return array<string, array<int, string>>
     */
    private function photoRules(array $fieldConfig): array
    {
        if (! $fieldConfig['enabled']) {
            return [
                'photos' => ['nullable'],
                'photos.*' => ['prohibited'],
            ];
        }

        $photoArrayRules = [
            $fieldConfig['required'] ? 'required' : 'nullable',
            'array',
        ];

        if ($fieldConfig['required']) {
            $photoArrayRules[] = 'min:1';
        }

        return [
            'photos' => $photoArrayRules,
            'photos.*' => [
                'file',
                'max:10240',
                'mimetypes:'.implode(',', $this->allowedTicketAttachmentMimeTypes()),
            ],
        ];
    }

    /**
     * @param  array<string, array{enabled: bool, required: bool}>  $schema
     * @param  array<string, array{label: string, type: string, builtin: bool}>  $fieldDefinitions
     * @return array<string, array<int, string>>
     */
    private function ticketFieldRules(array $schema, array $fieldDefinitions): array
    {
        $rules = [];

        foreach ($fieldDefinitions as $fieldKey => $fieldDefinition) {
            $fieldConfig = $schema[$fieldKey] ?? ['enabled' => false, 'required' => false];

            if ($fieldKey === 'title') {
                $rules['title'] = $this->stringRules($fieldConfig, 255);
                continue;
            }

            if ($fieldKey === 'description') {
                $rules['description'] = $this->stringRules($fieldConfig, 10000);
                continue;
            }

            if ($fieldKey === 'photo') {
                foreach ($this->photoRules($fieldConfig) as $ruleKey => $ruleValue) {
                    $rules[$ruleKey] = $ruleValue;
                }

                continue;
            }

            if ($fieldDefinition['builtin']) {
                continue;
            }

            if (! isset($rules['custom_fields'])) {
                $rules['custom_fields'] = ['nullable', 'array'];
            }

            $rules["custom_fields.{$fieldKey}"] = $this->customFieldRules($fieldConfig, $fieldDefinition['type']);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, array{enabled: bool, required: bool}>  $schema
     * @param  array<string, array{label: string, type: string, builtin: bool}>  $fieldDefinitions
     * @return array<string, mixed>
     */
    private function extractCustomFieldValues(array $validated, array $schema, array $fieldDefinitions): array
    {
        $source = is_array($validated['custom_fields'] ?? null)
            ? $validated['custom_fields']
            : [];

        $values = [];

        foreach ($fieldDefinitions as $fieldKey => $fieldDefinition) {
            if ($fieldDefinition['builtin']) {
                continue;
            }

            if (! ($schema[$fieldKey]['enabled'] ?? false)) {
                continue;
            }

            if (! array_key_exists($fieldKey, $source)) {
                continue;
            }

            $value = $source[$fieldKey];

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                continue;
            }

            $values[$fieldKey] = $value;
        }

        return $values;
    }

    /**
     * @param  array<string, array{enabled: bool, required: bool}>  $schema
     * @param  array<string, mixed>  $validated
     */
    private function resolveStringFieldValue(array $schema, array $validated, string $fieldKey): ?string
    {
        if (! ($schema[$fieldKey]['enabled'] ?? false)) {
            return null;
        }

        $value = $validated[$fieldKey] ?? null;

        return is_string($value) ? $value : null;
    }

    private function validateTicketTerms(Request $request, ?Project $project): void
    {
        if (! $project instanceof Project || ! $project->requiresTicketTermsAcceptance()) {
            return;
        }

        $request->validate([
            'on_call_terms_accepted' => ['accepted'],
        ], [
            'on_call_terms_accepted.accepted' => 'You must accept the Terms and Conditions before submitting this ticket.',
        ]);
    }

    /**
     * @param  array<int, UploadedFile>|UploadedFile|null  $uploadedPhotos
     */
    private function storeTicketPhotos(ServiceTicket $ticket, array|UploadedFile|null $uploadedPhotos): void
    {
        $files = is_array($uploadedPhotos) ? $uploadedPhotos : [];
        $firstStoredPath = null;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $storedPath = $file->store('ticket-photos', 'public');

            ServiceTicketPhoto::query()->create([
                'service_ticket_id' => $ticket->id,
                'photo_path' => $storedPath,
                'uploaded_by_user_id' => null,
            ]);

            if ($firstStoredPath === null) {
                $firstStoredPath = $storedPath;
            }
        }

        if ($firstStoredPath !== null && ! $ticket->screenshot_path) {
            $ticket->screenshot_path = $firstStoredPath;
            $ticket->save();
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedTicketAttachmentMimeTypes(): array
    {
        return [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
        ];
    }
}
