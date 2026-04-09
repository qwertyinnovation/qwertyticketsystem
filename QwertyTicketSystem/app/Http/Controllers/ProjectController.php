<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Project::categories();
        $serviceTypes = Project::serviceTypes();
        $priorities = Project::priorities();
        $statuses = Project::statuses();

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
        ]);
    }

    public function show(Request $request, Project $project): View
    {
        return view('projects.show', [
            'currentUser' => $request->user(),
            'project' => $project,
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        return view('projects.edit', [
            'currentUser' => $request->user(),
            'project' => $project,
            'categories' => Project::categories(),
            'serviceTypes' => Project::serviceTypes(),
            'priorities' => Project::priorities(),
            'statuses' => Project::statuses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProject($request);
        Project::create($validated);

        return redirect()->route('projects.index')->with('status', 'Project created.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $this->validateProject($request);
        $project->update($validated);

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
    private function validateProject(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(Project::categories())],
            'service_type' => ['required', Rule::in(Project::serviceTypes())],
            'priority' => ['required', Rule::in(Project::priorities())],
            'status' => ['required', Rule::in(Project::statuses())],
            'description' => ['nullable', 'string'],
        ]);
    }
}
