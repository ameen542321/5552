<?php

namespace App\Notifications;

use App\Mail\ResetPasswordMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public string $token;
    public string $email;

    public function __construct(string $token, string $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        // نستخدم مدة الصلاحية المركزية نفسها التي يتحقق منها مسار إعادة التعيين، حتى لا تعرض الرسالة مدة مختلفة عن السلوك الفعلي.
        $expiresInMinutes = (int) config('auth.passwords.users.expire', 60);
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $this->email,
        ], false));

        return (new ResetPasswordMail(
            $resetUrl,
            $notifiable->name ?? null,
            $expiresInMinutes
        ))
            ->to($this->email);
    }
}
