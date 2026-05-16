<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ServiceDeskSetting;
use App\Models\ServiceTicket;
use App\Models\ServiceTicketPhoto;
use App\Models\ServiceTicketPublicLink;
use App\Models\ServiceTicketResponse;
use App\Models\User;
use App\Services\ServiceTicketNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceTicketController extends Controller
{
    public function __construct(private readonly ServiceTicketNotificationService $ticketNotifications)
    {
    }

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $canManageAll = $this->canManageAllTickets($user);
        $canGeneratePublicLink = $user->hasPermission(User::PERMISSION_GENERATE_LINKS);
        $accessibleProjectIds = $canManageAll ? [] : $this->accessibleProjectIds($user);

        $projectsQuery = Project::query()->orderBy('name');

        if (! $canManageAll) {
            if ($accessibleProjectIds === []) {
                $projectsQuery->whereRaw('1 = 0');
            } else {
                $projectsQuery->whereIn('id', $accessibleProjectIds);
            }
        }

        $projects = $projectsQuery->get(['id', 'name', 'service_type']);
        $projectIds = $projects->pluck('id')->map(static fn ($value): int => (int) $value)->all();
        $serviceTypes = $projects->pluck('service_type')
            ->filter(static fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value): string => trim($value))
            ->unique()
            ->sort()
            ->values()
            ->all();
        $requesterRoles = ServiceTicket::requesterRoles();
        $statuses = ServiceTicket::statuses();
        $selectedStatuses = $this->normalizeStatusFilters($request, $statuses);

        $rawProjectId = $request->query('project_id');
        $projectId = is_numeric($rawProjectId) ? (int) $rawProjectId : null;
        [$createdDateFrom, $createdDateTo] = $this->normalizeDateRange(
            (string) $request->query('created_date_from', ''),
            (string) $request->query('created_date_to', '')
        );
        [$responseDateFrom, $responseDateTo] = $this->normalizeDateRange(
            (string) $request->query('response_date_from', ''),
            (string) $request->query('response_date_to', '')
        );

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'project_id' => $projectId !== null ? $projectId : null,
            'service_type' => trim((string) $request->query('service_type', '')),
            'requester_role' => (string) $request->query('requester_role', ''),
            'statuses' => $selectedStatuses,
            'created_date_from' => $createdDateFrom,
            'created_date_to' => $createdDateTo,
            'response_date_from' => $responseDateFrom,
            'response_date_to' => $responseDateTo,
        ];

        $ticketsQuery = ServiceTicket::query()
            ->with(['project', 'submittedBy', 'latestResponse'])
            ->latest();

        if (! $canManageAll) {
            if ($accessibleProjectIds === []) {
                $ticketsQuery->whereRaw('1 = 0');
            } else {
                $ticketsQuery->whereIn('project_id', $accessibleProjectIds);
            }
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $ticketsQuery->where(function ($query) use ($search): void {
                if (ctype_digit($search)) {
                    $query->where('id', (int) $search)
                        ->orWhere('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');

                    return;
                }

                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if ($filters['project_id'] !== null && in_array($filters['project_id'], $projectIds, true)) {
            $ticketsQuery->where('project_id', $filters['project_id']);
        }

        if ($filters['service_type'] !== '' && in_array($filters['service_type'], $serviceTypes, true)) {
            $ticketsQuery->whereHas('project', function ($query) use ($filters): void {
                $query->where('service_type', $filters['service_type']);
            });
        }

        if ($filters['requester_role'] !== '' && array_key_exists($filters['requester_role'], $requesterRoles)) {
            $ticketsQuery->where('requester_role', $filters['requester_role']);
        }

        if ($filters['statuses'] !== []) {
            $ticketsQuery->whereIn('status', $filters['statuses']);
        }

        if ($filters['created_date_from'] !== '') {
            $ticketsQuery->whereDate('created_at', '>=', $filters['created_date_from']);
        }

        if ($filters['created_date_to'] !== '') {
            $ticketsQuery->whereDate('created_at', '<=', $filters['created_date_to']);
        }

        if ($filters['response_date_from'] !== '' || $filters['response_date_to'] !== '') {
            $ticketsQuery->whereHas('latestResponse', function ($query) use ($filters): void {
                if ($filters['response_date_from'] !== '') {
                    $query->whereDate('created_at', '>=', $filters['response_date_from']);
                }

                if ($filters['response_date_to'] !== '') {
                    $query->whereDate('created_at', '<=', $filters['response_date_to']);
                }
            });
        }

        return view('service-tickets.index', [
            'currentUser' => $user,
            'tickets' => $ticketsQuery->paginate(10)->withQueryString(),
            'projects' => $projects,
            'serviceTypes' => $serviceTypes,
            'requesterRoles' => $requesterRoles,
            'statuses' => $statuses,
            'filters' => $filters,
            'canManageAllTickets' => $canManageAll,
            'canGeneratePublicLink' => $canGeneratePublicLink,
        ]);
    }

    /**
     * @param array<int, string> $allowedStatuses
     * @return array<int, string>
     */
    private function normalizeStatusFilters(Request $request, array $allowedStatuses): array
    {
        $rawStatusFilter = $request->query('status');
        $requestedStatuses = [];

        if (is_array($rawStatusFilter)) {
            foreach ($rawStatusFilter as $statusValue) {
                if (! is_string($statusValue)) {
                    continue;
                }

                $trimmedValue = trim($statusValue);

                if ($trimmedValue !== '') {
                    $requestedStatuses[] = $trimmedValue;
                }
            }
        } elseif (is_string($rawStatusFilter)) {
            $trimmedValue = trim($rawStatusFilter);

            if ($trimmedValue !== '') {
                $requestedStatuses[] = $trimmedValue;
            }
        }

        if ($requestedStatuses === []) {
            return [];
        }

        $requestedStatusLookup = array_fill_keys(array_values(array_unique($requestedStatuses)), true);
        $normalizedStatuses = [];

        foreach ($allowedStatuses as $allowedStatus) {
            if (isset($requestedStatusLookup[$allowedStatus])) {
                $normalizedStatuses[] = $allowedStatus;
            }
        }

        return $normalizedStatuses;
    }

    public function publicLinks(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasPermission(User::PERMISSION_GENERATE_LINKS)) {
            abort(403);
        }

        $requesterRoles = ServiceTicket::requesterRoles();
        $canManageAll = $this->canManageAllTickets($user);
        $accessibleProjectIds = $canManageAll ? [] : $this->accessibleProjectIds($user);

        $activePublicLinks = ServiceTicketPublicLink::query()
            ->where('created_by_user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('project:id,name')
            ->latest()
            ->limit(10)
            ->get();

        $projectsQuery = Project::query()->orderBy('name');

        if (! $canManageAll) {
            if ($accessibleProjectIds === []) {
                $projectsQuery->whereRaw('1 = 0');
            } else {
                $projectsQuery->whereIn('id', $accessibleProjectIds);
            }
        }

        return view('service-tickets.public-links', [
            'currentUser' => $user,
            'projects' => $projectsQuery->get(['id', 'name']),
            'requesterRoles' => $requesterRoles,
            'activePublicLinks' => $activePublicLinks,
            'generatedPublicLink' => session('generated_public_link'),
            'publicLinkExpiryMinutes' => ServiceDeskSetting::publicLinkExpiryMinutes(),
        ]);
    }

    public function create(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $canSelectRequesterRole = $this->canManageAllTickets($user);
        $accessibleProjectIds = $canSelectRequesterRole ? [] : $this->accessibleProjectIds($user);
        $requesterRoles = ServiceTicket::requesterRoles();
        $defaultRole = $this->defaultRequesterRole($user);
        $selectedRole = old('requester_role', $canSelectRequesterRole ? (string) $request->query('requester_role', $defaultRole) : $defaultRole);

        if (! array_key_exists($selectedRole, $requesterRoles)) {
            $selectedRole = $defaultRole;
        }

        $projectsQuery = Project::query()->orderBy('name');

        if (! $canSelectRequesterRole) {
            if ($accessibleProjectIds === []) {
                $projectsQuery->whereRaw('1 = 0');
            } else {
                $projectsQuery->whereIn('id', $accessibleProjectIds);
            }
        }

        return view('service-tickets.create', [
            'currentUser' => $user,
            'projects' => $projectsQuery->get(['id', 'name', 'service_type', 'status']),
            'requesterRoles' => $requesterRoles,
            'selectedRequesterRole' => $selectedRole,
            'ticketSchemaByRole' => ServiceDeskSetting::ticketFormSchema(),
            'ticketFieldDefinitions' => ServiceDeskSetting::ticketFieldDefinitions(),
            'ticketTermsText' => ServiceDeskSetting::ticketTermsText(),
            'canSelectRequesterRole' => $canSelectRequesterRole,
            'formAction' => route('service-tickets.store'),
            'isPublicForm' => false,
            'publicLink' => null,
        ]);
    }

    public function show(Request $request, ServiceTicket $serviceTicket): View
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanViewTicket($user, $serviceTicket);
        $serviceTicket->ensurePublicTrackingToken();

        $serviceTicket->loadMissing(['project', 'submittedBy', 'photos', 'responses.respondedBy']);

        return view('service-tickets.show', [
            'currentUser' => $user,
            'serviceTicket' => $serviceTicket,
            'requesterRoles' => ServiceTicket::requesterRoles(),
            'statuses' => ServiceTicket::statuses(),
            'ticketFieldDefinitions' => ServiceDeskSetting::ticketFieldDefinitions(),
            'trackingLink' => route('service-tickets.public.track', $serviceTicket->public_tracking_token),
            'canManageTicket' => $this->canManageTicketResponse($user, $serviceTicket),
            'canDeleteTicket' => $this->canManageAllTickets($user)
                || (int) $serviceTicket->submitted_by_user_id === (int) $user->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $canSelectRequesterRole = $this->canManageAllTickets($user);
        $requesterRoles = ServiceTicket::requesterRoles();

        $requesterRole = $canSelectRequesterRole
            ? (string) $request->input('requester_role', $this->defaultRequesterRole($user))
            : $this->defaultRequesterRole($user);

        if (! array_key_exists($requesterRole, $requesterRoles)) {
            $requesterRole = $this->defaultRequesterRole($user);
        }

        $schema = ServiceTicket::formSchemaForRole($requesterRole);
        $fieldDefinitions = ServiceDeskSetting::ticketFieldDefinitions();
        $accessibleProjectIds = $canSelectRequesterRole ? [] : $this->accessibleProjectIds($user);

        $rules = [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
        ];

        if (! $canSelectRequesterRole) {
            $rules['project_id'] = ['required', 'integer', Rule::in($accessibleProjectIds)];
        }

        foreach ($this->ticketFieldRules($schema, $fieldDefinitions) as $key => $rule) {
            $rules[$key] = $rule;
        }

        if ($canSelectRequesterRole) {
            $rules['requester_role'] = ['required', Rule::in(array_keys($requesterRoles))];
        }

        $validated = $request->validate($rules);
        $project = Project::query()->findOrFail((int) $validated['project_id']);
        $this->validateTicketTerms($request, $project);
        $customFieldValues = $this->extractCustomFieldValues($validated, $schema, $fieldDefinitions);

        /** @var ServiceTicket $ticket */
        $ticket = ServiceTicket::query()->create([
            'project_id' => (int) $validated['project_id'],
            'requester_role' => $requesterRole,
            'submitted_by_user_id' => $user->id,
            'title' => $this->resolveStringFieldValue($schema, $validated, 'title'),
            'description' => $this->resolveStringFieldValue($schema, $validated, 'description'),
            'custom_fields' => $customFieldValues !== [] ? $customFieldValues : null,
            'status' => ServiceTicket::statuses()[0],
            'public_tracking_token' => ServiceTicket::uniquePublicTrackingToken(),
        ]);

        if (($schema['photo']['enabled'] ?? false)) {
            $this->storeTicketPhotos($ticket, $request->file('photos', []), $user->id);
        }

        $this->ticketNotifications->ticketCreated($ticket, $user);

        return redirect()->route('service-tickets.show', $ticket)->with('status', 'Service ticket submitted.');
    }

    public function storePublicLink(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasPermission(User::PERMISSION_GENERATE_LINKS)) {
            abort(403);
        }

        $canManageAll = $this->canManageAllTickets($user);
        $accessibleProjectIds = $canManageAll ? [] : $this->accessibleProjectIds($user);

        $projectRule = ['required', 'integer', Rule::exists('projects', 'id')];

        if (! $canManageAll) {
            $projectRule = ['required', 'integer', Rule::in($accessibleProjectIds)];
        }

        $validated = $request->validate([
            'project_id' => $projectRule,
            'requester_role' => ['required', Rule::in(array_keys(ServiceTicket::requesterRoles()))],
        ]);

        do {
            $token = ServiceTicketPublicLink::generateToken();
        } while (ServiceTicketPublicLink::query()->where('token', $token)->exists());

        $publicLink = ServiceTicketPublicLink::query()->create([
            'token' => $token,
            'project_id' => (int) $validated['project_id'],
            'requester_role' => $validated['requester_role'],
            'created_by_user_id' => $user->id,
            'expires_at' => now()->addMinutes(ServiceDeskSetting::publicLinkExpiryMinutes()),
        ]);

        return back()
            ->with('status', 'One-time ticket link generated.')
            ->with('generated_public_link', route('service-tickets.public.create', $publicLink));
    }

    public function bulkDestroyPublicLinks(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasPermission(User::PERMISSION_GENERATE_LINKS)) {
            abort(403);
        }

        $validated = $request->validate([
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'distinct', 'exists:service_ticket_public_links,id'],
        ]);

        $selectedIds = array_map('intval', $validated['selected_ids']);
        $links = ServiceTicketPublicLink::query()
            ->whereIn('id', $selectedIds)
            ->get();

        if ($links->count() !== count($selectedIds) || $links->contains(fn (ServiceTicketPublicLink $link): bool => (int) $link->created_by_user_id !== (int) $user->id)) {
            abort(403);
        }

        foreach ($links as $link) {
            $link->delete();
        }

        $deletedCount = $links->count();

        return back()->with('status', $deletedCount.' '.Str::plural('one-time link', $deletedCount).' deleted.');
    }

    public function updateResponse(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanManageTicket($user, $serviceTicket);
        $previousStatus = $serviceTicket->status;

        $validated = $request->validate([
            'status' => ['required', Rule::in(ServiceTicket::statuses())],
            'response_message' => ['nullable', 'string', 'max:10000'],
            'response_attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimetypes:'.implode(',', $this->allowedResponseAttachmentMimeTypes()),
            ],
        ]);

        $attachmentPath = null;
        $attachmentOriginalName = null;
        $attachmentMimeType = null;
        $isImageAttachment = false;

        if ($request->hasFile('response_attachment')) {
            $responseAttachment = $request->file('response_attachment');

            if ($responseAttachment instanceof UploadedFile) {
                $attachmentPath = $responseAttachment->store('ticket-responses', 'public');
                $attachmentOriginalName = $responseAttachment->getClientOriginalName();
                $attachmentMimeType = $responseAttachment->getClientMimeType();
                $isImageAttachment = is_string($attachmentMimeType) && str_starts_with($attachmentMimeType, 'image/');
            }
        }

        ServiceTicketResponse::query()->create([
            'service_ticket_id' => $serviceTicket->id,
            'responded_by_user_id' => $user->id,
            'status' => $validated['status'],
            'response_message' => $validated['response_message'] ?? null,
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => $attachmentOriginalName,
            'attachment_mime_type' => $attachmentMimeType,
        ]);

        $serviceTicket->status = $validated['status'];
        $serviceTicket->response_description = $validated['response_message'] ?? null;
        $serviceTicket->response_photo_path = $isImageAttachment ? $attachmentPath : null;
        $serviceTicket->save();

        $serviceTicket->refresh();
        $this->ticketNotifications->ticketResponded(
            $serviceTicket,
            $user,
            $previousStatus,
            $validated['response_message'] ?? null,
        );

        return back()->with('status', 'Ticket response added.');
    }

    public function destroy(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanViewTicket($user, $serviceTicket);

        if (! $this->canManageAllTickets($user) && (int) $serviceTicket->submitted_by_user_id !== (int) $user->id) {
            abort(403);
        }

        $this->deleteServiceTicketWithFiles($serviceTicket);
        $this->ticketNotifications->ticketDeleted($serviceTicket, $user);

        return redirect()->route('service-tickets.index')->with('status', 'Service ticket deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'distinct', 'exists:service_tickets,id'],
        ]);

        $tickets = ServiceTicket::query()
            ->whereIn('id', array_map('intval', $validated['selected_ids']))
            ->with(['photos', 'responses'])
            ->get();

        foreach ($tickets as $ticket) {
            $this->assertCanViewTicket($user, $ticket);

            if (! $this->canManageAllTickets($user) && (int) $ticket->submitted_by_user_id !== (int) $user->id) {
                abort(403);
            }
        }

        foreach ($tickets as $ticket) {
            $this->deleteServiceTicketWithFiles($ticket);
            $this->ticketNotifications->ticketDeleted($ticket, $user);
        }

        $deletedCount = $tickets->count();

        return back()->with('status', $deletedCount.' '.Str::plural('service ticket', $deletedCount).' deleted.');
    }

    public function bulkClearPhotos(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'distinct', 'exists:service_tickets,id'],
        ]);

        $tickets = ServiceTicket::query()
            ->whereIn('id', array_map('intval', $validated['selected_ids']))
            ->with('photos')
            ->get();

        foreach ($tickets as $ticket) {
            $this->assertCanViewTicket($user, $ticket);

            if (! $this->canManageAllTickets($user) && (int) $ticket->submitted_by_user_id !== (int) $user->id) {
                abort(403);
            }
        }

        foreach ($tickets as $ticket) {
            $this->clearTicketPhotos($ticket);
        }

        $updatedCount = $tickets->count();

        return back()->with('status', 'Photos cleared from '.$updatedCount.' '.Str::plural('service ticket', $updatedCount).'.');
    }

    private function deleteServiceTicketWithFiles(ServiceTicket $serviceTicket): void
    {
        $serviceTicket->loadMissing(['photos', 'responses']);

        $responseAttachmentPaths = $serviceTicket->responses
            ->pluck('attachment_path')
            ->filter()
            ->values()
            ->all();

        $pathsToDelete = $this->ticketPhotoPaths($serviceTicket);

        if ($serviceTicket->response_photo_path) {
            $responseAttachmentPaths[] = $serviceTicket->response_photo_path;
        }

        foreach ($responseAttachmentPaths as $attachmentPath) {
            $pathsToDelete[] = $attachmentPath;
        }

        Storage::disk('public')->delete(array_values(array_unique($pathsToDelete)));

        $serviceTicket->delete();
    }

    private function clearTicketPhotos(ServiceTicket $serviceTicket): void
    {
        $serviceTicket->loadMissing(['photos', 'responses']);

        $pathsToDelete = $this->ticketPhotoPaths($serviceTicket);
        $responseAttachmentPaths = $serviceTicket->responses
            ->pluck('attachment_path')
            ->filter()
            ->values()
            ->all();

        if ($serviceTicket->response_photo_path) {
            $responseAttachmentPaths[] = $serviceTicket->response_photo_path;
        }

        foreach ($responseAttachmentPaths as $attachmentPath) {
            $pathsToDelete[] = $attachmentPath;
        }

        $pathsToDelete = array_values(array_unique($pathsToDelete));

        if ($pathsToDelete !== []) {
            Storage::disk('public')->delete($pathsToDelete);
        }

        $serviceTicket->photos()->delete();
        $serviceTicket->responses()->update([
            'attachment_path' => null,
            'attachment_original_name' => null,
            'attachment_mime_type' => null,
        ]);

        $serviceTicket->screenshot_path = null;
        $serviceTicket->response_photo_path = null;
        $serviceTicket->save();
    }

    /**
     * @return array<int, string>
     */
    private function ticketPhotoPaths(ServiceTicket $serviceTicket): array
    {
        $photoPaths = $serviceTicket->photos
            ->pluck('photo_path')
            ->filter()
            ->values()
            ->all();

        if ($serviceTicket->screenshot_path) {
            $photoPaths[] = $serviceTicket->screenshot_path;
        }

        return array_values(array_unique($photoPaths));
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

    private function validateTicketTerms(Request $request, Project $project): void
    {
        if (! $project->requiresTicketTermsAcceptance()) {
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
    private function storeTicketPhotos(ServiceTicket $ticket, array|UploadedFile|null $uploadedPhotos, ?int $uploadedByUserId): void
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
                'uploaded_by_user_id' => $uploadedByUserId,
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

    private function assertCanViewTicket(User $user, ServiceTicket $ticket): void
    {
        if ($this->canManageAllTickets($user)) {
            return;
        }

        if (! in_array((int) $ticket->project_id, $this->accessibleProjectIds($user), true)) {
            abort(403);
        }
    }

    private function assertCanManageTicket(User $user, ServiceTicket $ticket): void
    {
        if (! $this->canManageTicketResponse($user, $ticket)) {
            abort(403);
        }
    }

    private function canManageAllTickets(User $user): bool
    {
        return $user->role === User::ROLE_ADMIN
            || $user->role === User::ROLE_PM
            || $user->hasPermission(User::PERMISSION_MANAGE_PROJECTS)
            || $user->hasPermission(User::PERMISSION_MANAGE_SETTINGS);
    }

    private function defaultRequesterRole(User $user): string
    {
        $requesterRoles = ServiceTicket::requesterRoles();

        if (array_key_exists($user->role, $requesterRoles)) {
            return $user->role;
        }

        return User::ROLE_CLIENT;
    }

    /**
     * @return array<int, int>
     */
    private function accessibleProjectIds(User $user): array
    {
        return $user->assignedProjectIds();
    }

    private function canManageTicketResponse(User $user, ServiceTicket $ticket): bool
    {
        if ($this->canManageAllTickets($user)) {
            return true;
        }

        if (! $this->canRespondToAssignedProjectTickets($user)) {
            return false;
        }

        return in_array((int) $ticket->project_id, $this->accessibleProjectIds($user), true);
    }

    private function canRespondToAssignedProjectTickets(User $user): bool
    {
        return in_array($user->role, [User::ROLE_INTERNAL, User::ROLE_VENDOR], true);
    }

    /**
     * @return array<int, string>
     */
    private function allowedResponseAttachmentMimeTypes(): array
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

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizeDateRange(string $from, string $to): array
    {
        $normalizedFrom = $this->normalizeDateFilter($from);
        $normalizedTo = $this->normalizeDateFilter($to);

        if ($normalizedFrom !== '' && $normalizedTo !== '' && $normalizedFrom > $normalizedTo) {
            return [$normalizedTo, $normalizedFrom];
        }

        return [$normalizedFrom, $normalizedTo];
    }

    private function normalizeDateFilter(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $normalized);
        } catch (\Throwable) {
            return '';
        }

        if ($date->format('Y-m-d') !== $normalized) {
            return '';
        }

        return $normalized;
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
