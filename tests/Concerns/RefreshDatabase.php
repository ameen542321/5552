<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase as LaravelRefreshDatabase;

trait RefreshDatabase
{
    use LaravelRefreshDatabase;

    /**
     * Use the consolidated SQLite schema instead of the production migration history.
     */
    protected function migrateFreshUsing()
    {
        return [
            '--path' => database_path('migrations/testing'),
            '--realpath' => true,
        ];
    }
}
