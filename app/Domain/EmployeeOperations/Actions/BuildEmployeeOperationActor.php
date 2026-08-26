<?php

namespace App\Domain\EmployeeOperations\Actions;

use App\Domain\EmployeeOperations\Data\EmployeeOperationActor;

final class BuildEmployeeOperationActor
{
    public function fromCurrentAuth(): EmployeeOperationActor
    {
        $accountant = auth('accountant')->user();
        $user = auth()->user();

        return new EmployeeOperationActor(
            id: $accountant?->id ?? $user?->id,
            type: $accountant ? 'accountant' : ($user?->role ?? 'system'),
            name: $accountant?->name ?? $user?->name ?? 'النظام',
        );
    }
}
