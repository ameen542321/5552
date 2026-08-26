<?php

namespace App\Traits;

use App\Services\LogService;
use Illuminate\Database\Eloquent\Model;

trait HasLogs
{
    protected function addLog(
        string $action,
        string $description,
        ?Model $model = null,
        array $details = []
    ): void {
        app(LogService::class)->add(
            $action,
            $description,
            $model,
            $details
        );
    }
}
