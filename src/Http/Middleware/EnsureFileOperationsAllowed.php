<?php

namespace LogScope\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the download / clear / delete endpoints behind the master
 * `allow_file_operations` switch. When it is false those routes respond 403
 * regardless of who is authenticated — file mutation is opt-in.
 */
class EnsureFileOperationsAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            (bool) config('logscope.allow_file_operations', false),
            403,
            'Log file operations are disabled.',
        );

        return $next($request);
    }
}
