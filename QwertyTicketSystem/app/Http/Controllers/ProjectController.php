<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Project::categoriesWithHistorical();
        $serviceTypes = Project::serviceTypesWithHistorical();
        $priorities = Project::priorities();
        $statuses = Project::statusesWithHistorical();

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category' => (string) $request->query('category', ''),
            'service_type' => (string) $request->query('service_type', ''),
            'priority' => (string) $request->query('priority', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $projectsQuery = Project::query()->latest();

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $projectsQuery->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if ($filters['category'] !== '' && in_array($filters['category'], $categories, true)) {
            $projectsQuery->where('category', $filters['category']);
        }

        if ($filters['service_type'] !== '' && in_array($filters['service_type'], $serviceTypes, true)) {
            $projectsQuery->where('service_type', $filters['service_type']);
        }

        if ($filters['priority'] !== '' && in_array($filters['priority'], $priorities, true)) {
            $projectsQuery->where('priority', $filters['priority']);
        }

        if ($filters['status'] !== '' && in_array($filters['status'], $statuses, true)) {
            $projectsQuery->where('status', $filters['status']);
        }

        return view('projects.index', [
            'currentUser' => $request->user(),
            'projects' => $projectsQuery->paginate(10)->withQueryString(),
            'filters' => $filters,
            'categories' => $categories,
            'serviceTypes' => $serviceTypes,
            'priorities' => $priorities,
            'statuses' => $statuses,
        ]);
    }

    public function create(Request $request): View
    {
        return view('projects.create', [
            'currentUser' => $request->user(),
            'categories' => Project::categories(),
            'serviceTypes' => Project::serviceTypes(),
            'priorities' => Project::priorities(),
            'statuses' => Project::statuses(),
            'assignableUsersByRole' => $this->assignableUsersByRole(),
            'assignableRoleLabels' => $this->assignableRoleLabels(),
        ]);
    }

    public function show(Request $request, Project $project): View
    {
        $project->loadMissing('assignedUsers');

        return view('projects.show', [
            'currentUser' => $request->user(),
            'project' => $project,
            'assignableRoleLabels' => $this->assignableRoleLabels(),
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        $project->loadMissing('assignedUsers');

        return view('projects.edit', [
            'currentUser' => $request->user(),
            'project' => $project,
            'categories' => Project::categoriesForEdit($project),
            'serviceTypes' => Project::serviceTypesForEdit($project),
            'priorities' => Project::priorities(),
            'statuses' => Project::statusesForEdit($project),
            'assignableUsersByRole' => $this->assignableUsersByRole(),
            'assignableRoleLabels' => $this->assignableRoleLabels(),
            'selectedAssigneeIds' => $project->assignedUsers
                ->pluck('id')
                ->map(static fn ($value): int => (int) $value)
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProject($request);
        $assigneeUserIds = $validated['assignee_user_ids'] ?? [];
        unset($validated['assignee_user_ids']);

        $project = Project::create($validated);
        $this->syncProjectAssignments($project, $assigneeUserIds);

        return redirect()->route('projects.index')->with('status', 'Project created.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $this->validateProject($request, $project);
        $assigneeUserIds = $validated['assignee_user_ids'] ?? [];
        unset($validated['assignee_user_ids']);

        $project->update($validated);
        $this->syncProjectAssignments($project, $assigneeUserIds);

        return redirect()->route('projects.show', $project)->with('status', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();
        return redirect()->route('projects.index')->with('status', 'Project deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProject(Request $request, ?Project $project = null): array
    {
        $categories = $project ? Project::categoriesForEdit($project) : Project::categories();
        $serviceTypes = $project ? Project::serviceTypesForEdit($project) : Project::serviceTypes();
        $statuses = $project ? Project::statusesForEdit($project) : Project::statuses();
        $assignableUserIds = $this->assignableUserIds();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in($categories)],
            'service_type' => ['required', Rule::in($serviceTypes)],
            'priority' => ['required', Rule::in(Project::priorities())],
            'status' => ['required', Rule::in($statuses)],
            'description' => ['nullable', 'string'],
            'assignee_user_ids' => ['nullable', 'array'],
            'assignee_user_ids.*' => ['integer', Rule::in($assignableUserIds)],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function assignableRoleLabels(): array
    {
        $roleLabels = User::roles();
        $assignableRoleLabels = [];

        foreach (User::projectAssignableRoles() as $role) {
            $assignableRoleLabels[$role] = $roleLabels[$role] ?? ucfirst($role);
        }

        return $assignableRoleLabels;
    }

    /**
     * @return array<string, array<int, User>>
     */
    private function assignableUsersByRole(): array
    {
        $users = User::query()
            ->whereIn('role', User::projectAssignableRoles())
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        $grouped = [];

        foreach (User::projectAssignableRoles() as $role) {
            $grouped[$role] = $users
                ->where('role', $role)
                ->values()
                ->all();
        }

        return $grouped;
    }

    /**
     * @return array<int, int>
     */
    private function assignableUserIds(): array
    {
        return User::query()
            ->whereIn('role', User::projectAssignableRoles())
            ->pluck('id')
            ->map(static fn ($value): int => (int) $value)
            ->all();
    }

    /**
     * @param  array<int, mixed>  $assigneeUserIds
     */
    private function syncProjectAssignments(Project $project, array $assigneeUserIds): void
    {
        $validAssigneeIds = [];

        foreach ($assigneeUserIds as $assigneeUserId) {
            $id = (int) $assigneeUserId;

            if ($id < 1 || in_array($id, $validAssigneeIds, true)) {
                continue;
            }

            $validAssigneeIds[] = $id;
        }

        $project->assignedUsers()->sync($validAssigneeIds);
    }
}
