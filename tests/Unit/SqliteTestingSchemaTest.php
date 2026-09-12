<?php

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

class SqliteTestingSchemaTest extends TestCase
{
    public function test_consolidated_schema_builds_an_empty_current_database(): void
    {
        $database = new PDO('sqlite::memory:');
        $schema = file_get_contents(__DIR__.'/../../database/testing/sqlite-schema.sql');

        $this->assertNotFalse($schema);
        $database->exec($schema);

        $tables = $database
            ->query("SELECT name FROM sqlite_master WHERE type = 'table'")
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('users', $tables);
        $this->assertContains('stores', $tables);
        $this->assertContains('onesignal_settings', $tables);
        $this->assertContains('security_events', $tables);
        $this->assertContains('security_event_activities', $tables);
        $this->assertNotContains('migrations', $tables);

        $userColumns = $database->query('PRAGMA table_info("users")')->fetchAll(PDO::FETCH_ASSOC);
        $securityColumns = $database->query('PRAGMA table_info("security_events")')->fetchAll(PDO::FETCH_ASSOC);
        $productColumns = $database->query('PRAGMA table_info("products")')->fetchAll(PDO::FETCH_ASSOC);
        $creditSaleColumns = $database->query('PRAGMA table_info("credit_sales")')->fetchAll(PDO::FETCH_ASSOC);
        $saleColumns = $database->query('PRAGMA table_info("sales")')->fetchAll(PDO::FETCH_ASSOC);
        $accountantColumns = $database->query('PRAGMA table_info("accountants")')->fetchAll(PDO::FETCH_ASSOC);

        $this->assertContains('must_reset_password', array_column($userColumns, 'name'));
        $this->assertContains('verification_note', array_column($securityColumns, 'name'));
        $this->assertContains('response_action', array_column($securityColumns, 'name'));
        $this->assertContains('response_expires_at', array_column($securityColumns, 'name'));
        $this->assertContains('piece_price', array_column($productColumns, 'name'));
        $this->assertContains('added_by', array_column($creditSaleColumns, 'name'));

        $saleAccountantColumn = current(array_filter(
            $saleColumns,
            static fn (array $column): bool => $column['name'] === 'accountant_id'
        ));
        $this->assertIsArray($saleAccountantColumn);
        $this->assertSame(1, (int) $saleAccountantColumn['notnull']);

        $accountantEmployeeColumn = current(array_filter(
            $accountantColumns,
            static fn (array $column): bool => $column['name'] === 'employee_id'
        ));
        $this->assertIsArray($accountantEmployeeColumn);
        $this->assertSame(1, (int) $accountantEmployeeColumn['notnull']);
        $this->assertSame(0, (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }
}
