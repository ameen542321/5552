<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\Accountant;
use App\Models\ArchivedItem;
use App\Models\SupportSession;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SupportTicketFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_request_gets_ticket_and_support_can_reply_and_enter(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($owner, 'web')->post(route('user.support-tickets.store'), [
            'requested_role' => 'owner',
            'category' => 'restore',
            'priority' => 'high',
            'subject' => 'استعادة منتج محذوف',
            'description' => 'أحتاج مراجعة المنتج المحذوف وإعادته إلى المتجر.',
        ])->assertRedirect();

        $ticket = SupportTicket::firstOrFail();
        $this->assertStringStartsWith('SUP-', $ticket->reference);

        $this->actingAs($admin, 'web')->patch(route('admin.support-tickets.respond', $ticket), [
            'support_response' => 'تم استلام الطلب وستتم مراجعته.',
        ])->assertRedirect();
        $this->assertSame('waiting_owner', $ticket->fresh()->status);

        $this->actingAs($admin, 'web')->post(route('admin.support-tickets.start', $ticket))
            ->assertRedirect(route('user.dashboard'));
        $this->assertDatabaseHas('support_sessions', [
            'support_ticket_id' => $ticket->id,
            'ticket_reference' => $ticket->reference,
        ]);
    }

    public function test_direct_support_entry_generates_ticket_when_reference_is_missing(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin, 'web')->post(route('admin.support.owner.start', $owner), [
            'reason' => 'مراجعة فنية بطلب هاتفي من المالك',
        ])->assertRedirect(route('user.dashboard'));

        $ticket = SupportTicket::firstOrFail();
        $this->assertTrue($ticket->created_by_support);
        $this->assertStringStartsWith('SUP-', $ticket->reference);
        $this->assertDatabaseHas('support_sessions', ['ticket_reference' => $ticket->reference]);
    }

    public function test_ticket_conversation_tracks_unread_counts_and_hides_support_identity(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'اسم داخلي سري']);
        $ticket = SupportTicket::create([
            'owner_id' => $owner->id,
            'requested_role' => 'owner',
            'category' => 'general',
            'priority' => 'normal',
            'subject' => 'طلب متابعة المحادثة',
            'description' => 'وصف صالح لاختبار محادثة الدعم.',
            'status' => 'waiting_support',
        ]);

        $this->actingAs($admin, 'web')->patch(route('admin.support-tickets.respond', $ticket), [
            'support_response' => 'رسالة من الدعم لا تعرض اسم المنفذ.',
        ])->assertRedirect();

        $this->assertSame(1, $ticket->fresh()->owner_unread_count);
        $ownerView = $this->actingAs($owner, 'web')->get(route('user.support-tickets.show', $ticket));
        $ownerView->assertOk()->assertSee('الدعم التقني')->assertDontSee('اسم داخلي سري');
        $this->assertSame(0, $ticket->fresh()->owner_unread_count);

        $this->actingAs($owner, 'web')->post(route('user.support-tickets.messages.store', $ticket), [
            'message' => 'شكرًا، لدي إضافة جديدة على الطلب.',
        ])->assertRedirect();
        $this->assertSame(1, $ticket->fresh()->support_unread_count);
        $this->assertDatabaseHas('support_ticket_events', ['event_type' => 'owner_message']);

        $this->actingAs($owner, 'web')->patch(route('user.support-tickets.close', $ticket))->assertRedirect();
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->actingAs($owner, 'web')->patch(route('user.support-tickets.reopen', $ticket))->assertRedirect();
        $this->assertSame('waiting_support', $ticket->fresh()->status);
        $this->assertDatabaseHas('support_ticket_events', ['event_type' => 'reopened']);
    }

    public function test_owner_cannot_read_another_owners_ticket(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $otherOwner = User::factory()->create(['role' => User::ROLE_USER]);
        $ticket = SupportTicket::create([
            'owner_id' => $owner->id,
            'requested_role' => 'owner',
            'category' => 'general',
            'priority' => 'normal',
            'subject' => 'تذكرة خاصة',
            'description' => 'هذه التذكرة لا تخص المستخدم الآخر.',
        ]);

        $this->actingAs($otherOwner, 'web')->get(route('user.support-tickets.show', $ticket))->assertForbidden();
    }

    public function test_owner_can_have_one_owner_request_and_one_accountant_request_but_not_duplicates(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $accountant = Accountant::create([
            'user_id' => $owner->id,
            'store_id' => $owner->stores()->firstOrFail()->id,
            'name' => 'محاسب الدعم',
            'email' => 'ticket-accountant@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $payload = [
            'requested_role' => 'owner', 'category' => 'general', 'priority' => 'normal',
            'subject' => 'طلب دخول المالك', 'description' => 'هذا طلب صالح لاختبار منع التكرار.',
        ];

        $this->actingAs($owner, 'web')->post(route('user.support-tickets.store'), $payload)->assertRedirect();
        $this->post(route('user.support-tickets.store'), $payload)
            ->assertSessionHasErrors('requested_role');
        $this->post(route('user.support-tickets.store'), array_merge($payload, [
            'requested_role' => 'accountant',
            'accountant_id' => $accountant->id,
            'subject' => 'طلب دخول المحاسب',
        ]))->assertRedirect();

        $this->assertDatabaseCount('support_tickets', 2);
    }

    public function test_owner_cannot_follow_up_before_first_support_response(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $ticket = SupportTicket::create([
            'owner_id' => $owner->id, 'requested_role' => 'owner', 'category' => 'general',
            'priority' => 'normal', 'subject' => 'طلب بلا رد',
            'description' => 'لم يرد الدعم على هذا الطلب بعد.', 'status' => 'waiting_support',
        ]);

        $this->actingAs($owner, 'web')->post(route('user.support-tickets.messages.store', $ticket), [
            'message' => 'رسالة متابعة مبكرة',
        ])->assertStatus(422);
    }

    public function test_actions_page_cancels_unanswered_ticket_after_seven_days_without_scheduler(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $ticket = SupportTicket::create([
            'owner_id' => $owner->id, 'requested_role' => 'owner', 'category' => 'general',
            'priority' => 'normal', 'subject' => 'طلب منتهي المهلة',
            'description' => 'لم يحصل هذا الطلب على أي رد.', 'status' => 'waiting_support',
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin, 'web')->get(route('admin.support-actions.index'))->assertOk();
        $this->assertSame('cancelled', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->cancelled_at);
    }

    public function test_logout_ends_active_support_session_and_closes_ticket(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin, 'web')->post(route('admin.support.owner.start', $owner), [
            'reason' => 'اختبار إغلاق الجلسة عند تسجيل الخروج',
        ])->assertRedirect(route('user.dashboard'));
        $ticket = SupportTicket::firstOrFail();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertNotNull($ticket->sessions()->firstOrFail()->fresh()->ended_at);
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertGuest('web');
    }

    public function test_deleted_ticket_can_only_be_purged_after_ninety_days(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $ticket = SupportTicket::create([
            'owner_id' => $owner->id, 'requested_role' => 'owner', 'category' => 'general',
            'priority' => 'normal', 'subject' => 'تذكرة للاحتفاظ',
            'description' => 'تذكرة لاختبار سياسة الحذف النهائي.', 'status' => 'closed',
        ]);
        $ticket->delete();

        $this->actingAs($admin, 'web')
            ->delete(route('admin.support-actions.tickets.purge', $ticket->id))
            ->assertStatus(422);

        SupportTicket::onlyTrashed()->whereKey($ticket->id)->update(['deleted_at' => now()->subDays(91)]);
        $this->delete(route('admin.support-actions.tickets.purge', $ticket->id))->assertRedirect();
        $this->assertDatabaseMissing('support_tickets', ['id' => $ticket->id]);
    }

    public function test_support_session_expires_after_four_hours_and_returns_to_support_dashboard(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin, 'web')->post(route('admin.support.owner.start', $owner), [
            'reason' => 'اختبار الحد الأقصى لمدة جلسة الدعم',
        ])->assertRedirect(route('user.dashboard'));

        $this->travel(5)->hours();
        $this->get(route('user.dashboard'))->assertRedirect(route('admin.dashboard.index'));

        $this->assertNotNull(SupportTicket::firstOrFail()->sessions()->firstOrFail()->fresh()->ended_at);
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_actions_allow_manual_deletion_of_finished_ticket_but_reject_active_ticket(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $active = SupportTicket::create([
            'owner_id' => $owner->id, 'requested_role' => 'owner', 'category' => 'general',
            'priority' => 'normal', 'subject' => 'تذكرة نشطة',
            'description' => 'يجب ألا تُحذف هذه التذكرة قبل انتهائها.', 'status' => 'waiting_support',
        ]);
        $finished = SupportTicket::create([
            'owner_id' => $owner->id, 'requested_role' => 'owner', 'category' => 'general',
            'priority' => 'normal', 'subject' => 'تذكرة منتهية',
            'description' => 'هذه التذكرة منتهية وجاهزة للحذف اليدوي.', 'status' => 'closed',
            'closed_at' => now(),
        ]);

        $this->actingAs($admin, 'web')->delete(route('admin.support-tickets.destroy', $active))
            ->assertStatus(422);
        $this->delete(route('admin.support-tickets.destroy', $finished))->assertRedirect();

        $this->assertNotSoftDeleted($active);
        $this->assertSoftDeleted($finished);
    }

    public function test_actions_allow_manual_deletion_of_ended_session_only(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $attributes = [
            'admin_id' => $admin->id, 'target_type' => User::class, 'target_id' => $owner->id,
            'target_name' => $owner->name, 'target_role' => 'owner', 'reason' => 'مراجعة فنية',
            'ticket_reference' => 'SUP-MANUAL-SESSION', 'started_at' => now()->subHour(),
        ];
        $active = SupportSession::create($attributes);
        $ended = SupportSession::create(array_merge($attributes, [
            'ticket_reference' => 'SUP-ENDED-SESSION', 'ended_at' => now(),
        ]));

        $this->actingAs($admin, 'web')
            ->delete(route('admin.support-actions.sessions.destroy', $active))->assertStatus(422);
        $this->delete(route('admin.support-actions.sessions.destroy', $ended))->assertRedirect();

        $this->assertDatabaseHas('support_sessions', ['id' => $active->id]);
        $this->assertDatabaseMissing('support_sessions', ['id' => $ended->id]);
    }

    public function test_actions_allow_deleting_completed_deleted_item_record_only(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $attributes = [
            'owner_id' => $owner->id, 'archivable_type' => User::class,
            'archivable_id' => $owner->id, 'original_name' => 'سجل مكتمل',
            'reference' => 'DEL-COMPLETED-1', 'archived_at' => now()->subMonth(),
        ];
        $pending = ArchivedItem::create(array_merge($attributes, ['status' => 'archived']));
        $completed = ArchivedItem::create(array_merge($attributes, [
            'archivable_id' => $owner->id + 1, 'reference' => 'DEL-COMPLETED-2', 'status' => 'restored',
        ]));

        $this->actingAs($admin, 'web')
            ->delete(route('admin.support-actions.completed-items.destroy', $pending))->assertStatus(422);
        $this->delete(route('admin.support-actions.completed-items.destroy', $completed))->assertRedirect();

        $this->assertDatabaseHas('archived_items', ['id' => $pending->id]);
        $this->assertDatabaseMissing('archived_items', ['id' => $completed->id]);
    }

    public function test_reopening_cancelled_unanswered_ticket_grants_a_new_response_window(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $ticket = SupportTicket::create([
            'owner_id' => $owner->id, 'requested_role' => 'owner', 'category' => 'general',
            'priority' => 'normal', 'subject' => 'طلب أُلغي سابقًا',
            'description' => 'يجب أن يحصل الطلب على مهلة جديدة بعد إعادة فتحه.',
            'status' => 'cancelled', 'cancelled_at' => now(), 'closed_at' => now(),
            'cancel_reason' => 'انتهت مهلة الرد.', 'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($owner, 'web')
            ->patch(route('user.support-tickets.reopen', $ticket))
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('waiting_support', $ticket->status);
        $this->assertNull($ticket->cancelled_at);
        $this->assertNull($ticket->cancel_reason);
        $this->assertTrue($ticket->expires_at->isFuture());
    }
}
