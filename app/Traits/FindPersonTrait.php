<?php

namespace App\Traits;

use App\Models\Employee;
use App\Models\Accountant;

trait FindPersonTrait
{
    /**
     * ----------------------------------------------------------------------
     * البحث عن الشخص (موظف / محاسب)
     * ----------------------------------------------------------------------
     * - يدعم المحذوفين (withTrashed)
     * - قابل للتوسع مستقبلًا لإضافة كيانات أخرى
     * ----------------------------------------------------------------------
     */
    private function findPerson($id)
    {
        // قائمة الكيانات التي يمكن البحث فيها
        $models = [
            Employee::class,
            Accountant::class,
        ];

        foreach ($models as $model) {
            $record = $model::withTrashed()->find($id);
            if ($record) {
                return $record;
            }
        }

        abort(404, 'الشخص غير موجود');
    }
}
