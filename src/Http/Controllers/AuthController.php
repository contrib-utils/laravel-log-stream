<?php

namespace LogScope\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use LogScope\Auth\Authorizer;
use LogScope\Auth\EnvCredentialAuthorizer;

/**
 * Interactive login for the env-credential auth path. Custom authorizers
 * (LogScope::auth / authorization.using) bypass these routes entirely.
 */
class AuthController
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        // Already authorized? Skip the form.
        if (app(Authorizer::class)->authorize($request)) {
            return redirect($this->home());
        }

        return view('logscope::login', [
            'action' => route('logscope.login.attempt'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $authorizer = app(Authorizer::class);

        abort_unless(
            $authorizer instanceof EnvCredentialAuthorizer,
            404,
        );

        $this->ensureNotRateLimited($request);

        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! $authorizer->attempt($credentials['username'], $credentials['password'])) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'username' => __('These credentials do not match our records.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();
        $request->session()->put(EnvCredentialAuthorizer::SESSION_KEY, true);

        return redirect()->intended($this->home());
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(EnvCredentialAuthorizer::SESSION_KEY);

        return redirect(route('logscope.login'));
    }

    protected function ensureNotRateLimited(Request $request): void
    {
        $max = (int) config('logscope.auth.rate_limit', 5);

        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), $max)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'username' => __('Too many login attempts. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]),
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return 'logscope-login|'.$request->ip();
    }

    protected function home(): string
    {
        return '/'.trim(config('logscope.route_prefix'), '/');
    }
}
