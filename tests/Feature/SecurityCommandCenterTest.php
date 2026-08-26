<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\SecurityEventService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityCommandCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_security_command_center(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->saveQuietly();

        $this->get(route('admin.security.index'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('admin.security.index'))->assertRedirect(route('no.access'));
        $this->actingAs($admin)
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('مركز القيادة الأمنية')
            ->assertSee('مركز الأمن')
            ->assertSee('href="'.route('admin.security.index').'"', false);
    }

    public function test_service_groups_events_and_redacts_secrets(): void
    {
        $service = app(SecurityEventService::class);
        $first = $service->record('TEST.EVENT', 'testing', 'low', 'بلاغ اختباري', [
            'source_ip' => '192.0.2.5',
            'subject' => 'same',
            'evidence' => ['password' => 'never-store-me', 'safe' => 'visible'],
        ]);
        $second = $service->record('TEST.EVENT', 'testing', 'high', 'بلاغ اختباري', [
            'source_ip' => '192.0.2.5',
            'subject' => 'same',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(2, $second->occurrences);
        $this->assertSame('high', $second->severity);
        $this->assertSame('[REDACTED]', $second->evidence['password']);
        $this->assertStringNotContainsString('never-store-me', (string) $second->getRawOriginal('evidence'));
    }

    public function test_admin_action_is_validated_and_audited(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->saveQuietly();
        $event = app(SecurityEventService::class)->record('TEST.ACTION', 'testing', 'medium', 'بلاغ يحتاج أمرًا');

        $this->actingAs($admin)->patch(route('admin.security.action', $event), [
            'action' => 'contain',
            'note' => 'تم احتواء السبب ويجري الآن التحقق من النتيجة.',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.security.action', $event), [
            'action' => 'verify_resolve',
            'note' => 'تم التحقق من توقف السبب وعدم استمرار الأثر.',
        ])->assertRedirect();

        $this->assertDatabaseHas('security_events', ['id' => $event->id, 'status' => 'resolved', 'verified_by' => $admin->id]);
        $this->assertDatabaseHas('security_event_activities', ['security_event_id' => $event->id, 'user_id' => $admin->id, 'action' => 'verify_resolve']);
    }

    public function test_admin_can_assign_incident_and_add_investigation_note(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->saveQuietly();
        $assignee = User::factory()->create();
        $assignee->forceFill(['role' => 'admin'])->saveQuietly();
        $event = app(SecurityEventService::class)->record('TEST.ASSIGN', 'testing', 'medium', 'بلاغ للتعيين');

        $this->actingAs($admin)->patch(route('admin.security.action', $event), [
            'action' => 'assign',
            'assigned_to' => $assignee->id,
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.security.action', $event), [
            'action' => 'add_note',
            'note' => 'تمت مراجعة الأدلة ويحتاج البلاغ إلى متابعة إضافية.',
        ])->assertRedirect();

        $this->assertDatabaseHas('security_events', ['id' => $event->id, 'assigned_to' => $assignee->id]);
        $this->assertDatabaseHas('security_event_activities', ['security_event_id' => $event->id, 'action' => 'add_note']);
    }

    public function test_failed_logins_are_limited_without_suspending_victim(): void
    {
        $user = User::factory()->create(['email' => 'victim@example.test', 'password' => Hash::make('correct-password')]);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }

        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'تم تجاوز عدد المحاولات المسموح. حاول مرة أخرى لاحقًا.']);

        $this->assertSame('active', $user->refresh()->status);
        $this->assertDatabaseHas('security_events', ['event_code' => 'AUTH.LOGIN_FAILED', 'occurrences' => 5]);
        $this->assertDatabaseHas('security_events', ['event_code' => 'AUTH.RATE_LIMITED']);
    }

    public function test_resolved_incident_can_be_reopened_as_a_rollback(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->saveQuietly();
        $event = app(SecurityEventService::class)->record('TEST.ROLLBACK', 'testing', 'high', 'بلاغ لاختبار الرجوع');

        $this->actingAs($admin)->patch(route('admin.security.action', $event), ['action' => 'contain', 'note' => 'تم احتواء البلاغ مؤقتًا.']);
        $this->actingAs($admin)->patch(route('admin.security.action', $event), ['action' => 'verify_resolve', 'note' => 'نجح فحص التحقق الأول.']);
        $this->actingAs($admin)->patch(route('admin.security.action', $event), ['action' => 'reopen', 'note' => 'عاد الأثر ووجب التراجع عن الإغلاق.'])->assertRedirect();

        $event->refresh();
        $this->assertSame('investigating', $event->status);
        $this->assertNull($event->verified_at);
        $this->assertDatabaseHas('security_event_activities', ['security_event_id' => $event->id, 'action' => 'reopen']);
    }

    public function test_incident_cannot_be_closed_without_containment_and_verification(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->saveQuietly();
        $event = app(SecurityEventService::class)->record('TEST.VERIFY', 'testing', 'medium', 'بلاغ يحتاج تحققًا');

        $this->actingAs($admin)->patch(route('admin.security.action', $event), [
            'action' => 'verify_resolve',
            'note' => 'محاولة إغلاق دون احتواء سابق.',
        ])->assertSessionHasErrors(['action' => 'يجب احتواء البلاغ قبل التحقق من الحل.']);

        $this->assertSame('new', $event->refresh()->status);
        $this->assertNull($event->verified_at);
    }

    public function test_password_reset_response_can_be_applied_and_rolled_back(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->saveQuietly();
        $target = User::factory()->create();
        $event = app(SecurityEventService::class)->record('TEST.ACCOUNT', 'authentication', 'high', 'بلاغ مرتبط بحساب', ['target' => $target]);

        $this->actingAs($admin)->patch(route('admin.security.action', $event), [
            'action' => 'require_password_reset',
            'note' => 'رصد دخول غير معتاد ويجب تأمين الحساب.',
        ])->assertRedirect();
        $this->assertTrue($target->refresh()->must_reset_password);

        $this->actingAs($admin)->patch(route('admin.security.action', $event), [
            'action' => 'cancel_password_reset',
            'note' => 'ثبت أن البلاغ إنذار كاذب وتم التراجع.',
        ])->assertRedirect();
        $this->assertFalse($target->refresh()->must_reset_password);
    }

    public function test_active_admin_check_is_throttled_to_protect_hosting_resources(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->saveQuietly();
        Cache::forget('security:maintenance:health-lock');
        Artisan::shouldReceive('call')->once()->with('security:health-check')->andReturn(0);

        $this->actingAs($admin)->postJson(route('admin.security.maintenance.check'), ['automatic' => true])
            ->assertOk()
            ->assertJsonPath('status', 'completed');
        $this->actingAs($admin)->postJson(route('admin.security.maintenance.check'), ['automatic' => true])
            ->assertOk()
            ->assertJsonPath('status', 'skipped');
    }
}
