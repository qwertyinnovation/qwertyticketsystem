<?php

namespace Tests\Feature;

use App\Events\GeneralMessageCreated;
use App\Models\GeneralChatMember;
use App\Models\GeneralChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GeneralChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_requests_general_chat_access_and_admin_approves(): void
    {
        $adminUser = $this->userWithRole(User::ROLE_ADMIN);
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);

        $this->actingAs($internalUser)
            ->get(route('general-chat.show'))
            ->assertOk()
            ->assertSeeText('Join General Chat');

        $this->actingAs($internalUser)
            ->post(route('general-chat.request'))
            ->assertRedirect(route('general-chat.show'));

        $this->assertDatabaseHas('general_chat_members', [
            'user_id' => $internalUser->id,
            'status' => GeneralChatMember::STATUS_PENDING,
        ]);

        $membership = GeneralChatMember::query()->where('user_id', $internalUser->id)->firstOrFail();

        $this->actingAs($adminUser)
            ->put(route('general-chat.members.approve', $membership))
            ->assertRedirect(route('general-chat.show'));

        $this->assertDatabaseHas('general_chat_members', [
            'user_id' => $internalUser->id,
            'status' => GeneralChatMember::STATUS_APPROVED,
            'approved_by' => $adminUser->id,
        ]);

        $this->actingAs($internalUser)
            ->get(route('general-chat.show'))
            ->assertOk()
            ->assertSeeText('General Chat')
            ->assertSeeText($internalUser->name);
    }

    public function test_unapproved_user_cannot_send_general_chat_messages(): void
    {
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);

        $this->actingAs($internalUser)
            ->post(route('general-chat.store'), [
                'message' => 'Let me post.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('general_chat_messages', [
            'message' => 'Let me post.',
        ]);
    }

    public function test_approved_user_can_send_general_chat_message_over_json(): void
    {
        Event::fake([GeneralMessageCreated::class]);

        $adminUser = $this->userWithRole(User::ROLE_ADMIN);
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $this->approveUser($internalUser, $adminUser);

        $this->actingAs($internalUser)
            ->postJson(route('general-chat.store'), [
                'message' => 'General update.',
            ])
            ->assertCreated()
            ->assertJsonPath('message.user_id', $internalUser->id)
            ->assertJsonPath('message.message', 'General update.')
            ->assertJsonPath('message.author_name', $internalUser->name);

        Event::assertDispatched(GeneralMessageCreated::class, function (GeneralMessageCreated $event) use ($internalUser): bool {
            return (int) $event->generalChatMessage->user_id === (int) $internalUser->id
                && $event->generalChatMessage->message === 'General update.';
        });
    }

    public function test_admin_can_invite_and_kick_general_chat_member(): void
    {
        $adminUser = $this->userWithRole(User::ROLE_ADMIN);
        $vendorUser = $this->userWithRole(User::ROLE_VENDOR);

        $this->actingAs($adminUser)
            ->post(route('general-chat.members.invite'), [
                'user_id' => $vendorUser->id,
            ])
            ->assertRedirect(route('general-chat.show'));

        $this->assertDatabaseHas('general_chat_members', [
            'user_id' => $vendorUser->id,
            'status' => GeneralChatMember::STATUS_APPROVED,
            'invited_by' => $adminUser->id,
        ]);

        $membership = GeneralChatMember::query()->where('user_id', $vendorUser->id)->firstOrFail();

        $this->actingAs($adminUser)
            ->put(route('general-chat.members.kick', $membership), [
                'kick_reason' => 'Access no longer needed.',
            ])
            ->assertRedirect(route('general-chat.show'));

        $this->assertDatabaseHas('general_chat_members', [
            'user_id' => $vendorUser->id,
            'status' => GeneralChatMember::STATUS_KICKED,
            'kicked_by' => $adminUser->id,
            'kick_reason' => 'Access no longer needed.',
        ]);

        $this->actingAs($vendorUser)
            ->post(route('general-chat.store'), [
                'message' => 'Still here?',
            ])
            ->assertForbidden();
    }

    public function test_general_chat_unread_count_and_read_sync_work(): void
    {
        $adminUser = $this->userWithRole(User::ROLE_ADMIN);
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $this->approveUser($internalUser, $adminUser);

        GeneralChatMessage::query()->create([
            'user_id' => $adminUser->id,
            'message' => 'First unread.',
        ]);

        $latestMessage = GeneralChatMessage::query()->create([
            'user_id' => $adminUser->id,
            'message' => 'Latest unread.',
        ]);

        $this->actingAs($internalUser)
            ->get(route('project-chat.index'))
            ->assertOk()
            ->assertSeeText('General Chat')
            ->assertSeeText('2 unread');

        $this->actingAs($internalUser)
            ->postJson(route('general-chat.read'), [
                'message_id' => $latestMessage->id,
            ])
            ->assertOk()
            ->assertJsonPath('read_message_id', $latestMessage->id);

        $this->assertDatabaseHas('general_chat_reads', [
            'user_id' => $internalUser->id,
            'last_read_message_id' => $latestMessage->id,
        ]);
    }

    public function test_only_message_author_can_edit_and_delete_general_chat_message(): void
    {
        $adminUser = $this->userWithRole(User::ROLE_ADMIN);
        $internalUser = $this->userWithRole(User::ROLE_INTERNAL);
        $vendorUser = $this->userWithRole(User::ROLE_VENDOR);
        $this->approveUser($internalUser, $adminUser);
        $this->approveUser($vendorUser, $adminUser);

        $message = GeneralChatMessage::query()->create([
            'user_id' => $internalUser->id,
            'message' => 'Original general message.',
        ]);

        $this->actingAs($vendorUser)
            ->put(route('general-chat.update', $message), [
                'editing_message_id' => $message->id,
                'update_message' => 'Unauthorized edit.',
            ])
            ->assertForbidden();

        $this->actingAs($internalUser)
            ->put(route('general-chat.update', $message), [
                'editing_message_id' => $message->id,
                'update_message' => 'Edited general message.',
            ])
            ->assertRedirect(route('general-chat.show').'#message-'.$message->id);

        $this->assertDatabaseHas('general_chat_messages', [
            'id' => $message->id,
            'message' => 'Edited general message.',
        ]);

        $this->actingAs($vendorUser)
            ->delete(route('general-chat.destroy', $message))
            ->assertForbidden();

        $this->actingAs($adminUser)
            ->delete(route('general-chat.destroy', $message))
            ->assertForbidden();

        $this->assertDatabaseHas('general_chat_messages', [
            'id' => $message->id,
            'message' => 'Edited general message.',
        ]);

        $this->actingAs($internalUser)
            ->delete(route('general-chat.destroy', $message))
            ->assertRedirect(route('general-chat.show'));

        $this->assertDatabaseMissing('general_chat_messages', [
            'id' => $message->id,
        ]);
    }

    private function approveUser(User $targetUser, User $adminUser): GeneralChatMember
    {
        return GeneralChatMember::query()->create([
            'user_id' => $targetUser->id,
            'status' => GeneralChatMember::STATUS_APPROVED,
            'approved_by' => $adminUser->id,
            'requested_at' => now(),
            'approved_at' => now(),
            'joined_at' => now(),
        ]);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'permissions' => User::defaultPermissionsForRole($role),
        ]);
    }
}
