<?php

namespace Tests\Feature;

use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AccountantDashboardAlertsComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_reference_alert_displays_the_arabic_weekday_name(): void
    {
        $html = Blade::render(
            '<x-accountant-dashboard-alerts active-reference-date="2026-08-09" />'
        );

        $this->assertStringContainsString('يوم الأحد، تاريخ 2026-08-09', $html);
    }

    public function test_active_reference_banner_displays_weekday_next_to_date(): void
    {
        $html = Blade::render(
            '<x-accountant-reference-day-banner date="2026-08-06" />'
        );

        $this->assertStringContainsString('الخميس', $html);
        $this->assertStringContainsString('2026-08-06', $html);
        $this->assertStringContainsString('تأجيل', $html);
    }
}
