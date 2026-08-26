<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetPasswordSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_token_resets_the_password_once_and_invalidates_remember_me_sessions(): void
    {
        $user = $this->createUser();
        $token = 'valid-reset-token';
        $this->storeResetToken($user, $token);

        $response = $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertNull($user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_resets', ['email' => $user->email]);
    }

    public function test_an_incorrect_token_cannot_change_the_password(): void
    {
        $user = $this->createUser();
        $this->storeResetToken($user, 'actual-reset-token');

        $response = $this->from(route('password.request'))->post(route('password.update'), [
            'email' => $user->email,
            'token' => 'forged-reset-token',
            'password' => 'attacker-password',
            'password_confirmation' => 'attacker-password',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
        $this->assertDatabaseHas('password_resets', ['email' => $user->email]);
    }

    public function test_an_expired_token_cannot_change_the_password(): void
    {
        $user = $this->createUser();
        $this->storeResetToken($user, 'expired-reset-token', now()->subMinutes(61));

        $response = $this->from(route('password.request'))->post(route('password.update'), [
            'email' => $user->email,
            'token' => 'expired-reset-token',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
    }

    private function createUser(): User
    {
        return User::factory()->create([
            'plan_id' => null,
            'password' => Hash::make('original-password'),
            'remember_token' => 'existing-remember-token',
        ]);
    }

    private function storeResetToken(User $user, string $token, $createdAt = null): void
    {
        // قاعدة البيانات لا تحتفظ بالرمز الخام؛ الاختبار يحاكي طريقة الإنشاء الفعلية باستخدام hash.
        DB::table('password_resets')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => $createdAt ?? now(),
        ]);
    }
}
