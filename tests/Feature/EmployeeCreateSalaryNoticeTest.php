<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class EmployeeCreateSalaryNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_create_page_explains_when_salary_calculation_starts(): void
    {
        $owner = User::factory()->create([
            'plan_id' => null,
            'welcome_shown' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('user.employees.create'))
            ->assertOk()
            ->assertSee('يبدأ احتساب راتب الموظف الجديد من بداية الشهر الحالي')
            ->assertSee('وليس من تاريخ إضافته خلال الشهر');
    }
}
