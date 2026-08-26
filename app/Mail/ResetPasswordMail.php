<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;
    public ?string $recipientName;
    public int $expiresInMinutes;

    public function __construct(string $resetUrl, ?string $recipientName = null, int $expiresInMinutes = 60)
    {
        $this->resetUrl = $resetUrl;
        $this->recipientName = $recipientName;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    public function build()
    {
        // النسخة النصية ضرورية لعملاء البريد الذين يعطلون HTML، وتبقي رابط الاستعادة متاحًا للمستخدم.
        return $this->subject('إعادة تعيين كلمة المرور - CARLED')
                    ->view('emails.reset')
                    ->text('emails.reset-text')
                    ->with([
                        'resetUrl' => $this->resetUrl,
                        'recipientName' => $this->recipientName,
                        'expiresInMinutes' => $this->expiresInMinutes,
                    ]);
    }
}
