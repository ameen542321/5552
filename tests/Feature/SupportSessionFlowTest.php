<?php

namespace Tests\Feature;

use App\Models\Accountant;
use App\Models\Plan;
use App\Models\SupportSession;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportSessionService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SupportSessionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enter_and_leave_an_owner_support_session_without_credentials(): void
    {
        [$admin, $owner] = $this->accounts();
        $originalPassword = $owner->password;
        $originalEmail = $owner->email;

        $response = $this->actingAs($admin, 'web')->post(route('admin.support.owner.start', $owner), [
            'reason' => 'مراجعة طلب استعادة منتج مؤرشف',
            'ticket_reference' => 'TICKET-1001',
        ]);

        $response->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticatedAs($owner, 'web');
        $this->assertDatabaseHas('support_sessions', [
            'admin_id' => $admin->id,
            'target_type' => User::class,
            'target_id' => $owner->id,
            'ticket_reference' => 'TICKET-1001',
            'ended_at' => null,
        ]);
        $this->assertSame($originalEmail, $owner->fresh()->email);
        $this->assertSame($originalPassword, $owner->fresh()->password);

        $this->post(route('admin.support.stop'))->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertNotNull(SupportSession::firstOrFail()->ended_at);
        $this->assertNull(session(SupportSessionService::SESSION_KEY));
    }

    public function test_admin_can_enter_and_leave_an_accountant_support_session(): void
    {
        [$admin, $owner] = $this->accounts();
        $accountant = Accountant::create([
            'user_id' => $owner->id,
            'store_id' => $owner->stores()->firstOrFail()->id,
            'name' => 'محاسب الاختبار',
            'email' => 'accountant@example.test',
            'password' => 'password',
            'status' => 'suspended',
        ]);

        $this->actingAs($admin, 'web')->post(route('admin.support.accountant.start', $accountant), [
            'reason' => 'مراجعة عمليات المحاسب الموقوف',
        ])->assertRedirect(route('accountant.dashboard'));

        $this->assertAuthenticatedAs($accountant, 'accountant');
        $this->post(route('admin.support.stop'))->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertGuest('accountant');
    }

    public function test_non_admin_cannot_start_support_session(): void
    {
        [, $owner] = $this->accounts();
        $otherOwner = User::create($this->userPayload('other@example.test'));

        $this->actingAs($owner, 'web')->post(route('admin.support.owner.start', $otherOwner), [
            'reason' => 'محاولة غير مصرح بها',
        ])->assertRedirect(route('no.access'));

        $this->assertDatabaseCount('support_sessions', 0);
    }

    public function test_ticket_cannot_be_used_to_enter_a_different_target_account(): void
    {
        [$admin, $owner] = $this->accounts();
        $otherOwner = User::create($this->userPayload('ticket-other@example.test'));
        $ticket = SupportTicket::create([
            'owner_id' => $owner->id,
            'requested_role' => 'owner',
            'subject' => 'طلب خاص بالمالك الأول',
            'description' => 'يجب ألا تستخدم هذه التذكرة لحساب مختلف.',
            'status' => 'waiting_support',
        ]);

        $this->actingAs($admin, 'web')->post(route('admin.support.owner.start', $otherOwner), [
            'reason' => 'محاولة استخدام تذكرة حساب آخر',
            'ticket_reference' => $ticket->reference,
        ])->assertSessionHasErrors('ticket_reference');

        $this->assertDatabaseCount('support_sessions', 0);
        $this->assertAuthenticatedAs($admin, 'web');
    }

    private function accounts(): array
    {
        $plan = Plan::create([
            'name' => 'خطة الاختبار',
            'allowed_stores' => 3,
            'allowed_accountants' => 3,
            'price' => 0,
        ]);

        $admin = User::create(array_merge($this->userPayload('admin@example.test'), [
            'name' => 'مدير الاختبار',
            'role' => User::ROLE_ADMIN,
            'plan_id' => $plan->id,
        ]));
        $owner = User::create(array_merge($this->userPayload('owner@example.test'), [
            'name' => 'مالك الاختبار',
            'plan_id' => $plan->id,
        ]));

        return [$admin, $owner];
    }

    private function userPayload(string $email): array
    {
        return [
            'name' => 'مستخدم اختبار',
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 'active',
            'role' => User::ROLE_USER,
            'welcome_shown' => true,
            'subscription_end_at' => now()->addYear(),
            'allowed_stores' => 3,
            'allowed_accountants' => 3,
        ];
    }
}
