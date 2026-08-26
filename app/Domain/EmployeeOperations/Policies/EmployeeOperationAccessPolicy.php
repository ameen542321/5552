<?php

namespace App\Domain\EmployeeOperations\Policies;

use Illuminate\Database\Eloquent\Model;

final class EmployeeOperationAccessPolicy
{
    public function authorizePerson(Model $person): void
    {
        $user = auth()->user();

        if (auth('web')->check() && $user?->role === 'user') {
            if (! $user->stores()->where('id', $person->store_id)->exists()) {
                abort(403, 'هذا الموظف لا ينتمي لمتاجرك');
            }

            return;
        }

        if (auth('accountant')->check()) {
            if ((int) $person->store_id !== (int) auth('accountant')->user()->store_id) {
                abort(403, 'لا يمكنك إدارة موظفين خارج متجرك');
            }

            return;
        }

        if ($user && $user->role === 'admin') {
            return;
        }

        abort(403, 'غير مسموح');
    }
}
