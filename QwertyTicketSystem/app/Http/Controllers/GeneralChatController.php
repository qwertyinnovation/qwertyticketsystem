<?php

namespace App\Http\Controllers;

use App\Events\GeneralMessageCreated;
use App\Events\GeneralMessageDeleted;
use App\Events\GeneralMessageUpdated;
use App\Models\GeneralChatMember;
use App\Models\GeneralChatMessage;
use App\Models\GeneralChatRead;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GeneralChatController extends Controller
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $this->membershipFor($user);

        if ($this->canModerateGeneralChat($user)) {
            $membership = $this->ensureApprovedMembership($user, $user);
        }

        $canAccessRoom = $this->canAccessGeneralChat($user);
        $messages = collect();
        $participants = collect();
        $pendingMembers = collect();
        $inviteCandidates = collect();

        if ($canAccessRoom) {
            $messages = GeneralChatMessage::query()
                ->with('author:id,name,role')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $this->markGeneralChatAsRead($user, (int) $messages->max('id'));

            $participants = $this->approvedParticipants();
        }

        if ($this->canModerateGeneralChat($user)) {
            $pendingMembers = GeneralChatMember::query()
                ->pending()
                ->with('user:id,name,email,role')
                ->latest('requested_at')
                ->get();

            $inviteCandidates = $this->inviteCandidates($user);
        }

        return view('general-chat.show', [
            'currentUser' => $user,
            'membership' => $membership,
            'canAccessRoom' => $canAccessRoom,
            'canModerateRoom' => $this->canModerateGeneralChat($user),
            'messages' => $messages,
            'participants' => $participants,
            'pendingMembers' => $pendingMembers,
            'inviteCandidates' => $inviteCandidates,
            'roleLabels' => User::roles(),
        ]);
    }

    public function requestJoin(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->canModerateGeneralChat($user)) {
            $this->ensureApprovedMembership($user, $user);

            return redirect()
                ->route('general-chat.show')
                ->with('status', 'You already have access to General Chat.');
        }

        $membership = GeneralChatMember::query()->firstOrNew([
            'user_id' => $user->id,
        ]);

        if ($membership->status === GeneralChatMember::STATUS_APPROVED) {
            return redirect()->route('general-chat.show');
        }

        $membership->forceFill([
            'status' => GeneralChatMember::STATUS_PENDING,
            'approved_by' => null,
            'invited_by' => null,
            'kicked_by' => null,
            'kick_reason' => null,
            'requested_at' => now(),
            'approved_at' => null,
            'joined_at' => null,
            'kicked_at' => null,
        ])->save();

        return redirect()
            ->route('general-chat.show')
            ->with('status', 'Your General Chat request is waiting for admin approval.');
    }

    public function approve(Request $request, GeneralChatMember $generalChatMember): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanModerateGeneralChat($user);

        $this->approveMembership($generalChatMember, $user);

        return redirect()
            ->route('general-chat.show')
            ->with('status', $generalChatMember->user?->name.' was approved for General Chat.');
    }

    public function reject(Request $request, GeneralChatMember $generalChatMember): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanModerateGeneralChat($user);

        $generalChatMember->forceFill([
            'status' => GeneralChatMember::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'joined_at' => null,
        ])->save();

        return redirect()
            ->route('general-chat.show')
            ->with('status', $generalChatMember->user?->name.' request was rejected.');
    }

    public function invite(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanModerateGeneralChat($user);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $targetUser = User::query()->findOrFail($validated['user_id']);

        if ((int) $targetUser->id === (int) $user->id) {
            return redirect()
                ->route('general-chat.show')
                ->with('status', 'You already have access to General Chat.');
        }

        $membership = GeneralChatMember::query()->firstOrNew([
            'user_id' => $targetUser->id,
        ]);

        $membership->forceFill([
            'status' => GeneralChatMember::STATUS_APPROVED,
            'approved_by' => $user->id,
            'invited_by' => $user->id,
            'kicked_by' => null,
            'kick_reason' => null,
            'requested_at' => $membership->requested_at,
            'approved_at' => now(),
            'joined_at' => now(),
            'kicked_at' => null,
        ])->save();

        return redirect()
            ->route('general-chat.show')
            ->with('status', $targetUser->name.' was invited to General Chat.');
    }

    public function kick(Request $request, GeneralChatMember $generalChatMember): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanModerateGeneralChat($user);

        if ((int) $generalChatMember->user_id === (int) $user->id) {
            abort(422, 'You cannot kick yourself from General Chat.');
        }

        $validated = $request->validate([
            'kick_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $generalChatMember->forceFill([
            'status' => GeneralChatMember::STATUS_KICKED,
            'kicked_by' => $user->id,
            'kick_reason' => filled($validated['kick_reason'] ?? null) ? trim($validated['kick_reason']) : null,
            'kicked_at' => now(),
        ])->save();

        return redirect()
            ->route('general-chat.show')
            ->with('status', $generalChatMember->user?->name.' was removed from General Chat.');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessGeneralChat($user);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $generalChatMessage = GeneralChatMessage::query()->create([
            'user_id' => $user->id,
            'message' => trim($validated['message']),
        ]);

        $generalChatMessage->loadMissing('author:id,name,role');

        $event = new GeneralMessageCreated($generalChatMessage);
        $event->dontBroadcastToCurrentUser();
        event($event);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->messagePayload($generalChatMessage),
            ], 201);
        }

        return back();
    }

    public function update(Request $request, GeneralChatMessage $generalChatMessage): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessGeneralChat($user);
        $this->assertCanManageMessage($user, $generalChatMessage);

        $validated = $request->validate([
            'editing_message_id' => ['required', 'integer'],
            'update_message' => ['required', 'string', 'max:5000'],
        ]);

        $generalChatMessage->update([
            'message' => trim($validated['update_message']),
        ]);

        $generalChatMessage->loadMissing('author:id,name,role');

        $event = new GeneralMessageUpdated($generalChatMessage);
        $event->dontBroadcastToCurrentUser();
        event($event);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->messagePayload($generalChatMessage),
            ]);
        }

        return redirect()->to(route('general-chat.show').'#message-'.$generalChatMessage->id);
    }

    public function destroy(Request $request, GeneralChatMessage $generalChatMessage): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessGeneralChat($user);
        $this->assertCanManageMessage($user, $generalChatMessage);

        $messageId = (int) $generalChatMessage->id;
        $generalChatMessage->delete();

        $event = new GeneralMessageDeleted($messageId);
        $event->dontBroadcastToCurrentUser();
        event($event);

        if ($request->expectsJson()) {
            return response()->json([
                'message_id' => $messageId,
            ]);
        }

        return redirect()->route('general-chat.show');
    }

    public function markRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessGeneralChat($user);

        $validated = $request->validate([
            'message_id' => ['nullable', 'integer'],
        ]);

        $messageId = (int) ($validated['message_id'] ?? 0);

        if ($messageId > 0) {
            $messageId = (int) GeneralChatMessage::query()
                ->whereKey($messageId)
                ->value('id');
        }

        $readMessageId = $this->markGeneralChatAsRead($user, $messageId);

        return response()->json([
            'read_message_id' => $readMessageId,
        ]);
    }

    private function membershipFor(User $user): ?GeneralChatMember
    {
        return GeneralChatMember::query()
            ->where('user_id', $user->id)
            ->first();
    }

    private function ensureApprovedMembership(User $targetUser, User $actor): GeneralChatMember
    {
        $membership = GeneralChatMember::query()->firstOrNew([
            'user_id' => $targetUser->id,
        ]);

        if ($membership->status === GeneralChatMember::STATUS_APPROVED) {
            return $membership;
        }

        $membership->forceFill([
            'status' => GeneralChatMember::STATUS_APPROVED,
            'approved_by' => $actor->id,
            'requested_at' => $membership->requested_at ?? now(),
            'approved_at' => now(),
            'joined_at' => now(),
            'kicked_by' => null,
            'kick_reason' => null,
            'kicked_at' => null,
        ])->save();

        return $membership;
    }

    private function approveMembership(GeneralChatMember $membership, User $actor): void
    {
        $membership->forceFill([
            'status' => GeneralChatMember::STATUS_APPROVED,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'joined_at' => now(),
            'kicked_by' => null,
            'kick_reason' => null,
            'kicked_at' => null,
        ])->save();
    }

    private function assertCanAccessGeneralChat(User $user): void
    {
        if (! $this->canAccessGeneralChat($user)) {
            abort(403);
        }
    }

    private function canAccessGeneralChat(User $user): bool
    {
        return GeneralChatMember::userCanAccess($user);
    }

    private function assertCanModerateGeneralChat(User $user): void
    {
        if (! $this->canModerateGeneralChat($user)) {
            abort(403);
        }
    }

    private function canModerateGeneralChat(User $user): bool
    {
        return $user->role === User::ROLE_ADMIN;
    }

    private function assertCanManageMessage(User $user, GeneralChatMessage $generalChatMessage): void
    {
        if ((int) $generalChatMessage->user_id !== (int) $user->id) {
            abort(403);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function approvedParticipants(): Collection
    {
        return User::query()
            ->with('generalChatMembership')
            ->whereHas('generalChatMembership', function ($query): void {
                $query->where('status', GeneralChatMember::STATUS_APPROVED);
            })
            ->orderByRaw(
                "case role
                    when '".User::ROLE_ADMIN."' then 0
                    when '".User::ROLE_PM."' then 1
                    when '".User::ROLE_INTERNAL."' then 2
                    when '".User::ROLE_VENDOR."' then 3
                    when '".User::ROLE_CLIENT."' then 4
                    else 5
                end"
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);
    }

    /**
     * @return Collection<int, User>
     */
    private function inviteCandidates(User $currentUser): Collection
    {
        return User::query()
            ->whereKeyNot($currentUser->id)
            ->whereDoesntHave('generalChatMembership', function ($query): void {
                $query->where('status', GeneralChatMember::STATUS_APPROVED);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);
    }

    private function markGeneralChatAsRead(User $user, int $messageId = 0): int
    {
        $latestMessageId = $messageId > 0
            ? $messageId
            : (int) GeneralChatMessage::query()->max('id');

        if ($latestMessageId < 1) {
            return 0;
        }

        $chatRead = GeneralChatRead::query()->firstOrNew([
            'user_id' => $user->id,
        ]);

        $chatRead->forceFill([
            'last_read_message_id' => max((int) ($chatRead->last_read_message_id ?? 0), $latestMessageId),
            'read_at' => now(),
        ])->save();

        return (int) $chatRead->last_read_message_id;
    }

    /**
     * @return array<string, int|string|null>
     */
    private function messagePayload(GeneralChatMessage $generalChatMessage): array
    {
        $generalChatMessage->loadMissing('author:id,name,role');
        $author = $generalChatMessage->author;
        $role = (string) ($author?->role ?? '');

        return [
            'id' => (int) $generalChatMessage->id,
            'user_id' => (int) ($generalChatMessage->user_id ?? 0),
            'message' => (string) $generalChatMessage->message,
            'created_at' => $generalChatMessage->created_at?->toIso8601String(),
            'created_at_label' => $generalChatMessage->created_at?->format('M d, H:i'),
            'author_name' => $author?->name ?? 'Unknown User',
            'author_role' => $role,
            'author_role_label' => User::roles()[$role] ?? Str::of($role)->replace('_', ' ')->title()->value(),
        ];
    }
}
