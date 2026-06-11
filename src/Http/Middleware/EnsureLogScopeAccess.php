<?php

namespace LogScope\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LogScope\Auth\Authorizer;
use LogScope\Auth\EnvCredentialAuthorizer;
use LogScope\LogScope;

/**
 * Layers LogScope's own access gate on top of the configured middleware.
 *
 * Authorization precedence:
 *   1. A callback registered via LogScope::auth(...).
 *   2. The 'authorization.using' invokable class from config.
 *   3. The env-credential default authorizer.
 */
class EnsureLogScopeAccess
{
    public function handle(Request $request, Closure $next)
    {
        if ($this->passes($request)) {
            return $next($request);
        }

        if ($this->expectsJson($request)) {
            return response()->json(
                ['message' => 'Unauthorized.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        // Only the env-credential path offers an interactive login screen.
        if ($this->usesInteractiveLogin()) {
            return redirect()->guest(
                route('logscope.login', [], false) ?: '/'.config('logscope.route_prefix').'/login'
            );
        }

        abort(Response::HTTP_FORBIDDEN);
    }

    protected function passes(Request $request): bool
    {
        $logscope = app(LogScope::class);

        if ($logscope->hasCustomAuth()) {
            return $logscope->runCustomAuth($request);
        }

        if ($using = config('logscope.authorization.using')) {
            return (bool) app($using)($request);
        }

        return app(Authorizer::class)->authorize($request);
    }

    /**
     * True when no custom authorizer is configured and the env authorizer
     * actually has credentials to log in against.
     */
    protected function usesInteractiveLogin(): bool
    {
        if (app(LogScope::class)->hasCustomAuth()) {
            return false;
        }

        if (config('logscope.authorization.using')) {
            return false;
        }

        $authorizer = app(Authorizer::class);

        return $authorizer instanceof EnvCredentialAuthorizer
            && $authorizer->hasCredentials();
    }

    protected function expectsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->is(trim(config('logscope.route_prefix'), '/').'/api/*');
    }
}
