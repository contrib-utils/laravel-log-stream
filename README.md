# LogScope

View, search, and filter your Laravel application logs through a fast Vue dashboard and a JSON API — without ever loading a multi-gigabyte file into memory.

![LogScope dashboard](https://raw.githubusercontent.com/contrib-utils/laravel-log-stream/main/art/screenshot.jpeg)

> **Status:** Milestones 1–4 complete. Log discovery, byte-offset tailing, parsers for Laravel/Monolog plus nginx, Apache, Redis, PostgreSQL, supervisor and a generic fallback, the entries + search API, and the full SPA (list, detail, level filter, single-file and cross-file search, live tail, deep links, session login/logout) are all in place. Gated file operations, execution correlation, and mail previews are planned for later milestones.

## Features

- **Reads huge logs cheaply.** A byte-offset reader pages newest-first and tails in `O(window)`, never `O(file)`, so rotated multi-GB logs open instantly.
- **Many log formats.** A first-class Laravel/Monolog parser (line format, multi-line stack traces, trailing JSON context/extra, and the JSON formatter), plus parsers for **nginx**, **Apache**, **Redis**, **PostgreSQL**, and **supervisor**, and a **generic** fallback. Malformed lines are surfaced, never dropped.
- **Level filtering** across the eight PSR-3 levels (plus `unknown`), with per-level counts on the loaded view. Source-specific severity tokens (nginx `emerg`, Postgres `LOG`, supervisor `ERRO`, …) are normalised via configurable aliases.
- **Search.** Substring search within a file, or **cross-file search** across every (rotated) file in a source, with a composite cursor that pages seamlessly across file boundaries.
- **Live tail.** Toggle live mode to follow a file; new entries arrive inline when you're at the top, or stack behind a "N new entries" pill when you've scrolled away.
- **Deep links.** Every entry has a permalink. Opening it lands in the SPA with the entry pinned above the stream and its file selected for context.
- **Polished UI.** Light/dark theme, responsive layout, keyboard shortcuts, accessible controls, and reduced-motion support.
- **Secure by default.** An access gate protects the dashboard and API, with a session login/logout flow; opaque file ids are re-validated through a path-traversal guard on every read.

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

Publish `config/logscope.php` to customise the route prefix, the access middleware, log **sources** (glob patterns + parser), level labels/colours and aliases, and the live-tail poll interval (`LOGSCOPE_POLL_MS`, default 3000ms).

Beyond `laravel`, the config ships sources for `horizon`, `nginx`, `apache`, `redis`, `postgres`, `supervisor`, and `php-fpm` — all **disabled by default**, since their log locations vary by host. Enable the ones you have (set `enabled => true` and adjust `paths`). Available `parser` values: `laravel`, `nginx`, `apache`, `redis`, `postgres`, `supervisor`, `generic`.

### Custom authorization (production path)

Register a callback from a service provider's `boot()` — it takes precedence over the env credentials:

```php
use LogScope\Facades\LogScope;

LogScope::auth(fn (\Illuminate\Http\Request $r) => $r->user()?->isAdmin() === true);
```

Or point `config('logscope.authorization.using')` at an invokable class.

## JSON API

All endpoints sit under the configured prefix (`/logscope` by default) and require access. Responses are `{ "data": [...], "meta": { "next_cursor": ... } }`; pass the returned `next_cursor` back as `?cursor=` to page.

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/sources` | Enabled log sources. |
| `GET` | `/api/files?source=` | Files for a source, newest first. |
| `GET` | `/api/files/{fileId}/entries` | Entries for a file. Params: `cursor`, `per_page`, `direction` (`backward`\|`forward`), `level` (comma-separated), `q`. |
| `GET` | `/api/search?source=&q=` | Cross-file search within a source. Params: `cursor`, `per_page`, `level`. |
| `GET` | `/api/entries/{entryId}` | A single entry, for share / deep links. |

## Keyboard shortcuts

| Key | Action |
| --- | --- |
| `/` | Focus search |
| `r` | Refresh |
| `l` | Toggle live tail |
| `Esc` | Clear / blur search |

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

## Credits

Created and maintained by [Heinrich Smith Ondeyo](https://github.com/Heinirich).

## License

MIT
