<?php

namespace LogScope;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use LogScope\Auth\Authorizer;
use LogScope\Auth\EnvCredentialAuthorizer;
use LogScope\Console\InstallCommand;
use LogScope\Http\Middleware\EnsureFileOperationsAllowed;
use LogScope\Http\Middleware\EnsureLogScopeAccess;
use LogScope\Parsers\ParserManager;
use LogScope\Sources\LogFileRepository;
use LogScope\Sources\LogReader;
use LogScope\Sources\LogSearcher;
use LogScope\Support\LevelNormalizer;

class LogScopeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/logscope.php', 'logscope');

        $this->app->singleton(LogScope::class, fn () => new LogScope);

        // Default authorizer — env credentials. A host app overrides access
        // via LogScope::auth(...) or config('logscope.authorization.using')
        // without needing to rebind this.
        $this->app->bind(Authorizer::class, function (Application $app): Authorizer {
            $config = $app['config']['logscope.auth'] ?? [];

            return new EnvCredentialAuthorizer(
                $app,
                (bool) ($config['enabled'] ?? true),
                $config['user'] ?? null,
                $config['password'] ?? null,
            );
        });

        $this->app->singleton(LevelNormalizer::class, fn (Application $app) => new LevelNormalizer(
            (array) ($app['config']['logscope.levels'] ?? []),
            (array) ($app['config']['logscope.level_aliases'] ?? []),
        ));

        $this->app->singleton(ParserManager::class);
        $this->app->singleton(LogFileRepository::class);
        $this->app->singleton(LogReader::class);
        $this->app->singleton(LogSearcher::class);
    }

    public function boot(): void
    {
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'logscope');
        $this->registerPublishing();
        $this->registerCommands();
    }

    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware('logscope.access', EnsureLogScopeAccess::class);
        $this->app['router']->aliasMiddleware('logscope.file-ops', EnsureFileOperationsAllowed::class);
    }

    protected function registerRoutes(): void
    {
        $this->app['router']->group([
            'prefix' => config('logscope.route_prefix', 'logscope'),
            'middleware' => config('logscope.middleware', ['web']),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/logscope.php' => config_path('logscope.php'),
        ], 'logscope-config');

        $this->publishes([
            __DIR__.'/../resources/dist' => public_path('vendor/logscope'),
        ], 'logscope-assets');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
