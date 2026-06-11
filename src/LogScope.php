<?php

namespace LogScope;

use Illuminate\Http\Request;

/**
 * Central registry for runtime extension points (auth hook, etc.).
 *
 * Bound as a singleton so a host app can configure it from a service
 * provider's boot() method, e.g.:
 *
 *   LogScope::auth(fn (Request $r) => $r->user()?->isAdmin() === true);
 */
class LogScope
{
    /**
     * Custom authorization callback. When set it takes precedence over the
     * env-credential default and the config 'authorization.using' class.
     *
     * @var (callable(Request): bool)|null
     */
    protected $authCallback = null;

    /**
     * Register a custom authorization callback. Receives the request and
     * returns true when access is permitted.
     *
     * @param  (callable(Request): bool)|class-string  $callback
     */
    public function auth(callable|string $callback): static
    {
        if (is_string($callback)) {
            $callback = function (Request $request) use ($callback): bool {
                return (bool) app()->make($callback)($request);
            };
        }

        $this->authCallback = $callback;

        return $this;
    }

    /**
     * Whether a custom authorization callback has been registered.
     */
    public function hasCustomAuth(): bool
    {
        return $this->authCallback !== null;
    }

    /**
     * Run the registered custom authorization callback.
     */
    public function runCustomAuth(Request $request): bool
    {
        return $this->authCallback !== null
            && (bool) call_user_func($this->authCallback, $request);
    }
}
