# LogScope

View, search, filter, and correlate Laravel application logs through a web UI and a JSON API.

> **Status:** Milestone 1 complete — package scaffold, env-credential access gate, route group, and the Vue SPA shell. Log discovery, parsing, the entries API, and the UI list/detail land in Milestone 2.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13

## Installation

The package is developed inside this repo via a Composer **path repository** (see the root `composer.json`). In a real consuming app you would instead:

```bash
composer require logscope/logscope
php artisan logscope:install
```

`logscope:install` publishes the config and the compiled assets, and prints the env keys to set.

## Configuration

Set credentials to protect the dashboard:

```dotenv
LOGSCOPE_AUTH_USER=admin
LOGSCOPE_AUTH_PASSWORD=change-me
```

Then visit `/logscope`.

- In **local** environments with no credentials set, the dashboard is open for convenience.
- In **any other** environment, missing credentials lock the dashboard down (403) — it is never silently open.

### Custom authorization (production path)

Register a callback from a service provider's `boot()` — it takes precedence over the env credentials:

```php
use LogScope\Facades\LogScope;

LogScope::auth(fn (\Illuminate\Http\Request $r) => $r->user()?->isAdmin() === true);
```

Or point `config('logscope.authorization.using')` at an invokable class.

## Developing the SPA

```bash
cd packages/logscope
npm install
npm run build          # compiles to resources/dist
php artisan vendor:publish --tag=logscope-assets --force   # from the app root
```

## Testing

```bash
cd packages/logscope
composer install
vendor/bin/phpunit
```

## License

MIT
