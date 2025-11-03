<?php

namespace Test\Unit;

use PHPUnit\Framework\TestCase;
use Bencode\SnowflakeOdbc\SnowflakeOdbcDriver;
use ReflectionClass;

class SnowflakeReloginTest extends TestCase
{
    /** @test */
    public function it_performs_relogin_on_every_query_call()
    {
        $driver = new SnowflakeOdbcDriver();
        $reflection = new ReflectionClass($driver);
        
        // Track how many times withConnection is called
        $withConnectionCallCount = 0;
        
        // Replace withConnection method to track calls
        $withConnectionMethod = $reflection->getMethod('withConnection');
        $withConnectionMethod->setAccessible(true);
        
        $mockWithConnection = function($callback, $config = []) use (&$withConnectionCallCount) {
            $withConnectionCallCount++;
            
            $mockConn = "mock_connection_{$withConnectionCallCount}";
            
            return $callback($mockConn);
        };
        
        $withConnectionMethod->invoke($driver, $mockWithConnection);
        
        $config = [
            'dsn' => 'ExampleDSN',
            'username' => 'example',
            'password' => 'example',
        ];

        $driver->execute('SELECT 1', function() { return ['data']; }, $config);
        $driver->execute('SELECT 2', function() { return ['data']; }, $config);
        $driver->execute('SELECT 3', function() { return ['data']; }, $config);

        $this->assertEquals(3, $withConnectionCallCount);
    }
}