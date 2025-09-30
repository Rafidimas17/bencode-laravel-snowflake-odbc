<?php

namespace Bencode\SnowflakeOdbc;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Connection;

class SnowflakeOdbcServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app['db']->extend('snowflake-odbc', function ($config, $name) {
            $driver = new SnowflakeOdbcDriver();
            return $driver->connect($config);
        });
    }
}
