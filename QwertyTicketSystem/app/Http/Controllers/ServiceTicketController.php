<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ServiceDeskSetting;
use App\Models\ServiceTicket;
use App\Models\ServiceTicketPhoto;
use App\Models\ServiceTicketPublicLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceTicketController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $canManageAll = $this->canManageAllTickets($user);
        $canGeneratePublicLink = $user->hasPermission(User::PERMISSION_GENERATE_LINKS);
        $projects = Project::query()->orderBy('name')->get(['id', 'name']);
        $projectIds = $projects->pluck('id')->map(static fn ($value): int => (int) $value)->all();
        $requesterRoles = ServiceTicket::requesterRoles();
        $statuses = ServiceTicket::statuses();

        $rawProjectId = $request->query('project_id');
        $projectId = is_numeric($rawProjectId) ? (int) $rawProjectId : null;

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'project_id' => $projectId !== null ? $projectId : null,
            'requester_role' => (string) $request->query('requester_role', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $ticketsQuery = ServiceTicket::query()
            ->with(['project', 'submittedBy'])
            ->latest();

        if (! $canManageAll) {
            $ticketsQuery->where('submitted_by_user_id', $user->id);
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

        if ($filters['requester_role'] !== '' && array_key_exists($filters['requester_role'], $requesterRoles)) {
            $ticketsQuery->where('requester_role', $filters['requester_role']);
        }

        if ($filters['status'] !== '' && in_array($filters['status'], $statuses, true)) {
            $ticketsQuery->where('status', $filters['status']);
        }

        return view('service-tickets.index', [
            'currentUser' => $user,
            'tickets' => $ticketsQuery->paginate(10)->withQueryString(),
            'projects' => $projects,
            'requesterRoles' => $requesterRoles,
            'statuses' => $statuses,
            'filters' => $filters,
            'canManageAllTickets' => $canManageAll,
            'canGeneratePublicLink' => $canGeneratePublicLink,
        ]);
    }

    public function publicLinks(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasPermission(User::PERMISSION_GENERATE_LINKS)) {
            abort(403);
        }

        $requesterRoles = ServiceTicket::requesterRoles();

        $activePublicLinks = ServiceTicketPublicLink::query()
            ->where('created_by_user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('project:id,name')
            ->latest()
            ->limit(10)
            ->get();

        return view('service-tickets.public-links', [
            'currentUser' => $user,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
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
        $requesterRoles = ServiceTicket::requesterRoles();
        $defaultRole = $this->defaultRequesterRole($user);
        $selectedRole = old('requester_role', $canSelectRequesterRole ? (string) $request->query('requester_role', $defaultRole) : $defaultRole);

        if (! array_key_exists($selectedRole, $requesterRoles)) {
            $selectedRole = $defaultRole;
        }

        return view('service-tickets.create', [
            'currentUser' => $user,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'requesterRoles' => $requesterRoles,
            'selectedRequesterRole' => $selectedRole,
            'ticketSchemaByRole' => ServiceDeskSetting::ticketFormSchema(),
            'ticketFieldDefinitions' => ServiceDeskSetting::ticketFieldDefinitions(),
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

        $serviceTicket->loadMissing(['project', 'submittedBy', 'photos']);

        return view('service-tickets.show', [
            'currentUser' => $user,
            'serviceTicket' => $serviceTicket,
            'requesterRoles' => ServiceTicket::requesterRoles(),
            'statuses' => ServiceTicket::statuses(),
            'ticketFieldDefinitions' => ServiceDeskSetting::ticketFieldDefinitions(),
            'canManageTicket' => $this->canManageAllTickets($user),
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

        $rules = [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
        ];

        foreach ($this->ticketFieldRules($schema, $fieldDefinitions) as $key => $rule) {
            $rules[$key] = $rule;
        }

        if ($canSelectRequesterRole) {
            $rules['requester_role'] = ['required', Rule::in(array_keys($requesterRoles))];
        }

        $validated = $request->validate($rules);
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
        ]);

        if (($schema['photo']['enabled'] ?? false)) {
            $this->storeTicketPhotos($ticket, $request->file('photos', []), $user->id);
        }

        return redirect()->route('service-tickets.show', $ticket)->with('status', 'Service ticket submitted.');
    }

    public function storePublicLink(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasPermission(User::PERMISSION_GENERATE_LINKS)) {
            abort(403);
        }

        $validated = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
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

    public function updateResponse(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanManageTicket($user, $serviceTicket);

        $validated = $request->validate([
            'status' => ['required', Rule::in(ServiceTicket::statuses())],
            'response_description' => ['nullable', 'string', 'max:10000'],
            'response_photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $payload = [
            'status' => $validated['status'],
            'response_description' => $validated['response_description'] ?? null,
        ];

        if ($request->hasFile('response_photo')) {
            if ($serviceTicket->response_photo_path) {
                Storage::disk('public')->delete($serviceTicket->response_photo_path);
            }

            $payload['response_photo_path'] = $request->file('response_photo')->store('ticket-responses', 'public');
        }

        $serviceTicket->update($payload);

        return back()->with('status', 'Ticket response updated.');
    }

    public function destroy(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->canManageAllTickets($user) && (int) $serviceTicket->submitted_by_user_id !== (int) $user->id) {
            abort(403);
        }

        $serviceTicket->loadMissing('photos');

        $photoPaths = $serviceTicket->photos
            ->pluck('photo_path')
            ->filter()
            ->values()
            ->all();

        if ($serviceTicket->screenshot_path) {
            $photoPaths[] = $serviceTicket->screenshot_path;
        }

        if ($serviceTicket->response_photo_path) {
            $photoPaths[] = $serviceTicket->response_photo_path;
        }

        Storage::disk('public')->delete(array_values(array_unique($photoPaths)));

        $serviceTicket->delete();

        return redirect()->route('service-tickets.index')->with('status', 'Service ticket deleted.');
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
            'photos.*' => ['image', 'max:10240'],
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

        if ((int) $ticket->submitted_by_user_id !== (int) $user->id) {
            abort(403);
        }
    }

    private function assertCanManageTicket(User $user, ServiceTicket $ticket): void
    {
        if (! $this->canManageAllTickets($user)) {
            abort(403);
        }

        $this->assertCanViewTicket($user, $ticket);
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
}
