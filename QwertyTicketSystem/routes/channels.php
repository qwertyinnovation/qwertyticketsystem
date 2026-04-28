<?php

use App\Models\GeneralChatMember;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('projects.{projectId}', function (User $user, int $projectId): bool {
    $project = Project::query()->find($projectId);

    return $project instanceof Project && $project->hasChatParticipant($user);
});

Broadcast::channel('projects.presence.{projectId}', function (User $user, int $projectId): array|bool {
    $project = Project::query()->find($projectId);

    if (! $project instanceof Project || ! $project->hasChatParticipant($user)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role,
    ];
});

Broadcast::channel('general-chat', function (User $user): bool {
    return GeneralChatMember::userCanAccess($user);
});

Broadcast::channel('general-chat.presence', function (User $user): array|bool {
    if (! GeneralChatMember::userCanAccess($user)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role,
    ];
});
