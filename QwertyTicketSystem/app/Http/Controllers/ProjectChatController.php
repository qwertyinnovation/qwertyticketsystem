<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectChatController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $projects = $this->accessibleProjectsQuery($user)
            ->withCount('messages')
            ->with(['latestMessage.author:id,name,role'])
            ->get(['id', 'name', 'status', 'updated_at']);

        return view('project-chat.index', [
            'currentUser' => $user,
            'projects' => $projects,
        ]);
    }

    public function show(Request $request, Project $project): View
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessProjectChat($user, $project);

        $project->load([
            'messages.author:id,name,role',
            'assignedUsers:id,name,email,role',
        ]);

        return view('project-chat.show', [
            'currentUser' => $user,
            'project' => $project,
            'messages' => $project->messages,
            'participants' => $project->chatParticipants(),
            'roleLabels' => User::roles(),
            'editingMessageId' => $this->editingMessageId($request, $project),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessProjectChat($user, $project);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'message' => trim($validated['message']),
        ]);

        return back();
    }

    public function update(Request $request, Project $project, ProjectMessage $projectMessage): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessProjectChat($user, $project);
        $this->assertProjectMessageBelongsToProject($project, $projectMessage);
        $this->assertCanManageMessage($user, $projectMessage);

        $validated = $request->validate([
            'editing_message_id' => ['required', 'integer'],
            'update_message' => ['required', 'string', 'max:5000'],
        ]);

        $projectMessage->update([
            'message' => trim($validated['update_message']),
        ]);

        return redirect()->to(route('project-chat.show', $project).'#message-'.$projectMessage->id);
    }

    public function destroy(Request $request, Project $project, ProjectMessage $projectMessage): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessProjectChat($user, $project);
        $this->assertProjectMessageBelongsToProject($project, $projectMessage);
        $this->assertCanManageMessage($user, $projectMessage);

        $projectMessage->delete();

        return redirect()->route('project-chat.show', $project);
    }

    private function assertCanAccessProjectChat(User $user, Project $project): void
    {
        if (! $project->hasChatParticipant($user)) {
            abort(403);
        }
    }

    private function assertProjectMessageBelongsToProject(Project $project, ProjectMessage $projectMessage): void
    {
        if ((int) $projectMessage->project_id !== (int) $project->id) {
            abort(404);
        }
    }

    private function assertCanManageMessage(User $user, ProjectMessage $projectMessage): void
    {
        if ($this->canManageAllProjectChatMessages($user)) {
            return;
        }

        if ((int) $projectMessage->user_id !== (int) $user->id) {
            abort(403);
        }
    }

    private function accessibleProjectsQuery(User $user): Builder
    {
        $projectsQuery = Project::query()->orderBy('name');

        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_PM], true)) {
            return $projectsQuery;
        }

        $assignedProjectIds = $user->assignedProjectIds();

        if ($assignedProjectIds === []) {
            return $projectsQuery->whereRaw('1 = 0');
        }

        return $projectsQuery->whereIn('id', $assignedProjectIds);
    }

    private function canManageAllProjectChatMessages(User $user): bool
    {
        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_PM], true);
    }

    private function editingMessageId(Request $request, Project $project): ?int
    {
        $rawEditingMessageId = $request->old('editing_message_id', $request->query('edit_message'));

        if (! is_numeric($rawEditingMessageId)) {
            return null;
        }

        $editingMessageId = (int) $rawEditingMessageId;

        if ($editingMessageId < 1) {
            return null;
        }

        return $project->messages->contains('id', $editingMessageId) ? $editingMessageId : null;
    }
}
