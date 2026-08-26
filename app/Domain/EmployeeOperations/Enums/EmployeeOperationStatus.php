<?php

namespace App\Domain\EmployeeOperations\Enums;

enum EmployeeOperationStatus: string
{
    case Pending = 'pending';
    case Deducted = 'deducted';
}
