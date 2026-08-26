<?php

namespace Tests\Unit;

use App\Mail\ResetPasswordMail;
use Tests\TestCase;

class ResetPasswordMailTest extends TestCase
{
    public function test_it_renders_the_reset_link_recipient_and_expiration_notice(): void
    {
        $resetUrl = 'https://carled.example/reset-password/token?email=owner%40example.com';
        $mail = new ResetPasswordMail($resetUrl, 'مالك المتجر', 60);

        $html = $mail->render();

        $this->assertStringContainsString('مرحبًا مالك المتجر', $html);
        $this->assertStringContainsString('صلاحية هذا الرابط', $html);
        $this->assertStringContainsString('60 دقيقة', $html);
        $this->assertStringContainsString('لم تطلب تغيير كلمة المرور؟', $html);
        $this->assertStringContainsString($resetUrl, html_entity_decode($html));
    }

    public function test_it_has_a_plain_text_alternative(): void
    {
        $mail = new ResetPasswordMail('https://carled.example/reset-password/token', null, 60);

        $mail->build();

        $this->assertSame('emails.reset-text', $mail->textView);
    }
}
