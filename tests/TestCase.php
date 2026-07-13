<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'mysql' || $database !== 'uhlms_testing') {
            throw new \LogicException('Tests must use the isolated MySQL database "uhlms_testing", never the production database.');
        }
    }
}
