<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class TestingDatabaseIsolationTest extends TestCase
{
    public function test_tests_use_isolated_in_memory_sqlite_database(): void
    {
        $this->assertTrue(
            app()->environment('testing')
        );

        $this->assertSame(
            'sqlite',
            config('database.default')
        );

        $this->assertSame(
            ':memory:',
            config('database.connections.sqlite.database')
        );
    }
}