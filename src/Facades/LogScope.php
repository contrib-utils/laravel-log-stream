<?php

namespace LogScope\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \LogScope\LogScope auth(callable|string $callback)
 * @method static bool hasCustomAuth()
 * @method static bool runCustomAuth(\Illuminate\Http\Request $request)
 *
 * @see \LogScope\LogScope
 */
class LogScope extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LogScope\LogScope::class;
    }
}
