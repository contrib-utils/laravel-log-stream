<?php

namespace LogScope\Auth;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

/**
 * The zero-config default authorizer.
 *
 * Access rules (in order):
 *   1. No credentials configured + local environment  -> open (dev convenience).
 *   2. No credentials configured + any other env       -> locked down (403).
 *   3. Auth explicitly disabled (enabled=false) + creds set -> open.
 *   4. Otherwise authorize when the session has been authenticated via the
 *      login form, OR when HTTP Basic credentials match (used by the API).
 *
 * All credential comparisons use hash_equals to avoid timing leaks.
 */
class EnvCredentialAuthorizer implements Authorizer
{
    public const SESSION_KEY = 'logscope_authenticated';

    public function __construct(
        protected Application $app,
        protected bool $enabled,
        protected ?string $user,
        protected ?string $password,
    ) {}

    public function authorize(Request $request): bool
    {
        if (! $this->hasCredentials()) {
            // Never expose an open dashboard outside local, even with auth "disabled".
            return $this->app->environment('local');
        }

        if (! $this->enabled) {
            return true;
        }

        return $this->sessionAuthenticated($request)
            || $this->basicMatches($request);
    }

    /**
     * Verify a username/password pair against the configured credentials.
     * Used by the login controller.
     */
    public function attempt(string $user, string $password): bool
    {
        return $this->hasCredentials()
            && $this->safeEquals($this->user, $user)
            && $this->safeEquals($this->password, $password);
    }

    public function hasCredentials(): bool
    {
        return filled($this->user) && filled($this->password);
    }

    protected function sessionAuthenticated(Request $request): bool
    {
        return $request->hasSession()
            && $request->session()->get(self::SESSION_KEY) === true;
    }

    protected function basicMatches(Request $request): bool
    {
        $user = $request->getUser();
        $password = $request->getPassword();

        if ($user === null || $password === null) {
            return false;
        }

        return $this->attempt($user, $password);
    }

    protected function safeEquals(?string $known, string $given): bool
    {
        return $known !== null && hash_equals($known, $given);
    }
}
