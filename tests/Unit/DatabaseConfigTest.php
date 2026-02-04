<?php

namespace Tests\Unit;

use Tests\TestCase;

class DatabaseConfigTest extends TestCase
{
    public function test_database_configuration_is_sqlite(): void
    {
        // Check that the test environment is using SQLite
        $this->assertEquals('sqlite', config('database.default'));
        $this->assertEquals(':memory:', config('database.connections.sqlite.database'));
        
        echo "\nDefault connection: " . config('database.default');
        echo "\nSQLite database: " . config('database.connections.sqlite.database');
        echo "\nMySQL database: " . config('database.connections.mysql.database');
        echo "\n";
    }
}
