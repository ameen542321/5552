<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketEvent extends Model
{
    protected $fillable = ['support_ticket_id', 'event_type', 'actor_role', 'actor_id', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function getLabelAttribute(): string
    {
        return match ($this->event_type) {
            'created' => 'تم إنشاء التذكرة',
            'owner_message' => 'أضاف المالك رسالة',
            'support_message' => 'أضاف الدعم التقني ردًا',
            'session_started' => 'بدأ الدعم التقني جلسة دخول',
            'session_ended' => 'انتهت جلسة الدخول',
            'closed' => 'أُغلقت التذكرة',
            'reopened' => 'أُعيد فتح التذكرة',
            'deleted' => 'حُذفت التذكرة من قائمة الدعم',
            'cancelled' => 'أُلغي الطلب لانتهاء مهلة الرد',
            default => 'تحديث على التذكرة',
        };
    }
}
