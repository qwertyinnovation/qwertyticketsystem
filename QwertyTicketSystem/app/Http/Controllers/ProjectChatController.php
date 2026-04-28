<?php

namespace App\Http\Controllers;

use App\Events\ProjectMessageCreated;
use App\Events\ProjectMessageDeleted;
use App\Events\ProjectMessageUpdated;
use App\Models\GeneralChatMember;
use App\Models\GeneralChatMessage;
use App\Models\GeneralChatRead;
use App\Models\Project;
use App\Models\ProjectChatRead;
use App\Models\ProjectMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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

        $unreadCounts = ProjectMessage::query()
            ->selectRaw('project_messages.project_id as project_id, COUNT(*) as unread_count')
            ->leftJoin('project_chat_reads', function ($join) use ($user): void {
                $join->on('project_messages.project_id', '=', 'project_chat_reads.project_id')
                    ->where('project_chat_reads.user_id', '=', $user->id);
            })
            ->whereIn('project_messages.project_id', $projects->pluck('id'))
            ->where('project_messages.user_id', '!=', $user->id)
            ->where(function ($query): void {
                $query->whereNull('project_chat_reads.last_read_message_id')
                    ->orWhereColumn('project_messages.id', '>', 'project_chat_reads.last_read_message_id');
            })
            ->groupBy('project_messages.project_id')
            ->pluck('unread_count', 'project_id');

        $projects->each(function (Project $project) use ($unreadCounts): void {
            $project->setAttribute('unread_messages_count', (int) ($unreadCounts[$project->id] ?? 0));
        });

        return view('project-chat.index', [
            'currentUser' => $user,
            'projects' => $projects,
            'generalChat' => $this->generalChatLobbyState($user),
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

        $this->markProjectAsRead($project, $user, (int) $project->messages->max('id'));

        return view('project-chat.show', [
            'currentUser' => $user,
            'project' => $project,
            'messages' => $project->messages,
            'participants' => $project->chatParticipants(),
            'roleLabels' => User::roles(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessProjectChat($user, $project);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $projectMessage = ProjectMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'message' => trim($validated['message']),
        ]);

        $projectMessage->loadMissing('author:id,name,role');

        $event = new ProjectMessageCreated($projectMessage);
        $event->dontBroadcastToCurrentUser();
        event($event);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->messagePayload($projectMessage),
            ], 201);
        }

        return back();
    }

    public function update(Request $request, Project $project, ProjectMessage $projectMessage): RedirectResponse|JsonResponse
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

        $projectMessage->loadMissing('author:id,name,role');

        $event = new ProjectMessageUpdated($projectMessage);
        $event->dontBroadcastToCurrentUser();
        event($event);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->messagePayload($projectMessage),
            ]);
        }

        return redirect()->to(route('project-chat.show', $project).'#message-'.$projectMessage->id);
    }

    public function destroy(Request $request, Project $project, ProjectMessage $projectMessage): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessProjectChat($user, $project);
        $this->assertProjectMessageBelongsToProject($project, $projectMessage);
        $this->assertCanManageMessage($user, $projectMessage);

        $messageId = (int) $projectMessage->id;
        $projectId = (int) $project->id;
        $projectMessage->delete();

        $event = new ProjectMessageDeleted($projectId, $messageId);
        $event->dontBroadcastToCurrentUser();
        event($event);

        if ($request->expectsJson()) {
            return response()->json([
                'message_id' => $messageId,
            ]);
        }

        return redirect()->route('project-chat.show', $project);
    }

    public function markRead(Request $request, Project $project): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessProjectChat($user, $project);

        $validated = $request->validate([
            'message_id' => ['nullable', 'integer'],
        ]);

        $messageId = (int) ($validated['message_id'] ?? 0);

        if ($messageId > 0) {
            $messageId = (int) $project->messages()
                ->whereKey($messageId)
                ->value('id');
        }

        $readMessageId = $this->markProjectAsRead($project, $user, $messageId);

        return response()->json([
            'read_message_id' => $readMessageId,
        ]);
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

    /**
     * @return array<string, bool|int|string|null|GeneralChatMember|GeneralChatMessage>
     */
    private function generalChatLobbyState(User $user): array
    {
        $membership = GeneralChatMember::query()
            ->where('user_id', $user->id)
            ->first();
        $canAccess = GeneralChatMember::userCanAccess($user);
        $latestMessage = GeneralChatMessage::query()
            ->with('author:id,name,role')
            ->latest('id')
            ->first();
        $messagesCount = GeneralChatMessage::query()->count();
        $unreadCount = 0;

        if ($canAccess) {
            $lastReadMessageId = (int) (GeneralChatRead::query()
                ->where('user_id', $user->id)
                ->value('last_read_message_id') ?? 0);

            $unreadCount = GeneralChatMessage::query()
                ->where('user_id', '!=', $user->id)
                ->when($lastReadMessageId > 0, function (Builder $query) use ($lastReadMessageId): void {
                    $query->where('id', '>', $lastReadMessageId);
                })
                ->count();
        }

        $status = $canAccess
            ? GeneralChatMember::STATUS_APPROVED
            : (string) ($membership?->status ?? 'not_joined');

        $statusLabel = match ($status) {
            GeneralChatMember::STATUS_APPROVED => 'Approved',
            GeneralChatMember::STATUS_PENDING => 'Pending Approval',
            GeneralChatMember::STATUS_REJECTED => 'Rejected',
            GeneralChatMember::STATUS_KICKED => 'Kicked',
            default => 'Request Required',
        };

        return [
            'membership' => $membership,
            'can_access' => $canAccess,
            'status' => $status,
            'status_label' => $statusLabel,
            'latest_message' => $latestMessage,
            'messages_count' => $messagesCount,
            'unread_count' => $unreadCount,
        ];
    }

    private function markProjectAsRead(Project $project, User $user, int $messageId = 0): int
    {
        $latestProjectMessageId = $messageId > 0
            ? $messageId
            : (int) $project->messages()->max('id');

        if ($latestProjectMessageId < 1) {
            return 0;
        }

        $chatRead = ProjectChatRead::query()->firstOrNew([
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);

        $chatRead->forceFill([
            'last_read_message_id' => max((int) ($chatRead->last_read_message_id ?? 0), $latestProjectMessageId),
            'read_at' => now(),
        ])->save();

        return (int) $chatRead->last_read_message_id;
    }

    /**
     * @return array<string, int|string|null>
     */
    private function messagePayload(ProjectMessage $projectMessage): array
    {
        $projectMessage->loadMissing('author:id,name,role');
        $author = $projectMessage->author;
        $role = (string) ($author?->role ?? '');

        return [
            'id' => (int) $projectMessage->id,
            'project_id' => (int) $projectMessage->project_id,
            'user_id' => (int) ($projectMessage->user_id ?? 0),
            'message' => (string) $projectMessage->message,
            'created_at' => $projectMessage->created_at?->toIso8601String(),
            'created_at_label' => $projectMessage->created_at?->format('M d, H:i'),
            'author_name' => $author?->name ?? 'Unknown User',
            'author_role' => $role,
            'author_role_label' => User::roles()[$role] ?? ucfirst($role),
        ];
    }
}
