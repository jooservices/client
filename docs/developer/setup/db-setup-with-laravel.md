# DB Setup With Laravel

## Goal
Use MySQL logging inside a Laravel app, following Laravel conventions (service provider, config publish, migrations).

## 1) Install and publish
```bash
composer require jooservices/jooclient
php artisan vendor:publish --provider="JOOservices\Client\Providers\JooclientServiceProvider" --tag=config
php artisan vendor:publish --provider="JOOservices\Client\Providers\JooclientServiceProvider" --tag=migrations
php artisan migrate
```

## 2) Configure via .env (Laravel-standard)
```env
JOOCLIENT_LOGGING_ENABLED=true
JOOCLIENT_LOGGING_DRIVER=mysql
# uses Laravel database.php defaults unless overridden
JOOCLIENT_DB_HOST=127.0.0.1
JOOCLIENT_DB_PORT=3306
JOOCLIENT_DB_DATABASE=jooclient
JOOCLIENT_DB_USERNAME=root
JOOCLIENT_DB_PASSWORD=secret
JOOCLIENT_DB_TABLE=client_request_logs
JOOCLIENT_DB_BATCH=true        # buffer writes
JOOCLIENT_DB_FALLBACK=error_log # error_log|throw|silent
```
`config/jooclient.php` will read these and fall back to `config('database.connections.mysql.*')` when individual values are absent.

## 3) Use in code
```php
use JOOservices\Client\Factory\Factory;

// auto-resolved via container (preferred)
public function __construct(private Factory $jooclient) {}

public function handle()
{
    $client = $this->jooclient->enableLogging()->make();
    $response = $client->get('https://example.com');
    $client->flushLogger(); // important if batch=true
}
```

## 4) Table and rotation
- Migrations publish `client_request_logs`—run `php artisan migrate` to create it.
- Add pruning/rotation to scheduler:
```php
// app/Console/Kernel.php
$schedule->command('jooclient:prune --days=30 --force')->dailyAt('02:00');
```

## 5) Troubleshooting (Laravel)
- Missing table: rerun `php artisan migrate` or check connection in `.env`.
- No logs: ensure `JOOCLIENT_LOGGING_ENABLED=true` and driver is `mysql`; call `flushLogger()` if batching.
- Fallback path: set `JOOCLIENT_DB_FALLBACK=throw` to fail fast in lower envs.
- Conflicts with app DB: override `JOOCLIENT_DB_*` to point to the correct connection; otherwise defaults to the primary Laravel mysql connection.
