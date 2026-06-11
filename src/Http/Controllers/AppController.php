<?php

namespace LogScope\Http\Controllers;

use Illuminate\Contracts\View\View;
use LogScope\Support\Assets;

/**
 * Serves the single-page Vue application shell. Any path under the route
 * prefix that is not the login screen or an API call lands here so the
 * client-side router can take over (enabling deep links / share links).
 */
class AppController
{
    public function __invoke(): View
    {
        return view('logscope::app', [
            'assets' => Assets::entry(),
            'assetsPublished' => Assets::published(),
            'config' => [
                'prefix' => '/'.trim(config('logscope.route_prefix'), '/'),
                'apiBase' => '/'.trim(config('logscope.route_prefix'), '/').'/api',
                'pollMs' => config('logscope.realtime.poll_ms'),
                'levels' => config('logscope.levels'),
                'allowFileOperations' => (bool) config('logscope.allow_file_operations'),
                'csrfToken' => csrf_token(),
            ],
        ]);
    }
}
