<?php

namespace Tests\Unit;

use App\Models\Log;
use PHPUnit\Framework\TestCase;

class SupportSessionAuditLabelTest extends TestCase
{
    public function test_support_operation_displays_technical_support_and_ticket_without_personal_name(): void
    {
        $log = new Log([
            'details' => [
                'performed_by_technical_support' => true,
                'support_admin_name' => 'اسم داخلي لا يجب عرضه',
                'support_ticket_reference' => 'SUP-20260803-ABC123',
            ],
        ]);

        $this->assertSame('الدعم التقني — تذكرة SUP-20260803-ABC123', $log->actor_display_name);
    }

    public function test_support_session_actions_have_clear_arabic_labels(): void
    {
        $started = new Log(['action' => 'support_session_started']);
        $ended = new Log(['action' => 'support_session_ended']);

        $this->assertSame('بدء جلسة دعم تقني', $started->action_label);
        $this->assertSame('إنهاء جلسة دعم تقني', $ended->action_label);
    }
}
