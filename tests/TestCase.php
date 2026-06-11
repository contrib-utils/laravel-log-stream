<?php

namespace LogScope\Tests;

use LogScope\LogScopeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LogScopeServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Sessions (web middleware) require an app key.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /**
     * Build the Basic-auth Authorization header for API requests.
     */
    protected function basic(string $user, string $password): array
    {
        return ['Authorization' => 'Basic '.base64_encode("$user:$password")];
    }
}
