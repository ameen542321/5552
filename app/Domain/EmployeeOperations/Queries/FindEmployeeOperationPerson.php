<?php

namespace App\Domain\EmployeeOperations\Queries;

use App\Models\Accountant;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

final class FindEmployeeOperationPerson
{
    public function findOrFail(int|string $personId): Model
    {
        return Employee::find($personId) ?? Accountant::findOrFail($personId);
    }
}
