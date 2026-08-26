<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Accountant;

class EmployeeLogService
{
    /**
     * تسجيل سجل جديد لأي كيان (موظف، محاسب…)
     */
    public static function add($person, $actionName, $description, $amount = null, $meta = [])
{
    if (is_numeric($person)) {
        $person = Employee::withTrashed()->find($person)
            ?? Accountant::withTrashed()->find($person);
    }

    if (!$person || !($person instanceof Employee || $person instanceof Accountant)) {
        return;
    }

    if (! is_array($meta)) {
        $meta = ['type' => $meta];
    }

    $meta = app(OperationActorContext::class)->auditMetadata($meta);

    return $person->logs()->create([
        'store_id'    => $person->store_id,
        'action_name' => $actionName,   // ← هذا هو الصحيح الآن
        'amount'      => $amount,
        'description' => $description,
        'meta'        => $meta,
    ]);
}

}
