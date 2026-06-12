<?php

namespace LogScope\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use LogScope\Auth\EnvCredentialAuthorizer;
use LogScope\Support\Assets;

/**
 * Serves the single-page Vue application shell. Any path under the route
 * prefix that is not the login screen or an API call lands here so the
 * client-side router can take over (enabling deep links / share links).
 */
class AppController
{
    public function __invoke(Request $request): View
    {
        $prefix = '/'.trim(config('logscope.route_prefix'), '/');

        return view('logscope::app', [
            'assets' => Assets::entry(),
            'assetsPublished' => Assets::published(),
            'config' => [
                'prefix' => $prefix,
                'apiBase' => $prefix.'/api',
                'pollMs' => config('logscope.realtime.poll_ms'),
                'levels' => config('logscope.levels'),
                'allowFileOperations' => (bool) config('logscope.allow_file_operations'),
                // Logout only applies to the session-login path; it is a no-op
                // for an open dashboard or a custom authorizer (HTTP Basic / SSO).
                'canLogout' => $this->sessionAuthenticated($request),
                'logoutUrl' => $prefix.'/logout',
                'csrfToken' => csrf_token(),
            ],
        ]);
    }

    protected function sessionAuthenticated(Request $request): bool
    {
        return $request->hasSession()
            && $request->session()->get(EnvCredentialAuthorizer::SESSION_KEY) === true;
    }
}
