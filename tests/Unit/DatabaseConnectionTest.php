<?php

namespace Tests\Unit;

use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function test_uses_sqlite_in_memory(): void
    {
        $connection = config('database.default');
        $database = config('database.connections.' . $connection . '.database');
        
        $this->assertEquals('sqlite', $connection, 'Tests should use SQLite');
        $this->assertEquals(':memory:', $database, 'Tests should use in-memory database');
    }
}
