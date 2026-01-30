# DB Setup Without Laravel (Capsule, .env)

## Goal
Use MySQL logging without a Laravel app. Configure via `.env` (or array), bootstrap `Illuminate\Database\Capsule\Manager`, and ensure the logging table exists.

## 1) Configure environment
Create `.env` (or export vars) with connection + table + behavior:
```env
JOOCLIENT_LOGGING_ENABLED=true
JOOCLIENT_LOGGING_DRIVER=mysql
JOOCLIENT_DB_LOGGING=true
JOOCLIENT_DB_HOST=127.0.0.1
JOOCLIENT_DB_PORT=3306
JOOCLIENT_DB_DATABASE=jooclient
JOOCLIENT_DB_USERNAME=root
JOOCLIENT_DB_PASSWORD=secret
JOOCLIENT_DB_TABLE=client_request_logs
JOOCLIENT_DB_BATCH=false
JOOCLIENT_DB_FALLBACK=error_log   # error_log|throw|silent
```

## 2) Bootstrap Capsule (once, early)
```php
use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => getenv('JOOCLIENT_DB_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('JOOCLIENT_DB_PORT') ?: 3306),
    'database' => getenv('JOOCLIENT_DB_DATABASE') ?: 'jooclient',
    'username' => getenv('JOOCLIENT_DB_USERNAME') ?: 'root',
    'password' => getenv('JOOCLIENT_DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
```

## 3) Ensure the table exists (minimal SQL)
```sql
CREATE TABLE IF NOT EXISTS `client_request_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `level` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `context` json NULL,
  `request_method` varchar(10) NULL,
  `path` varchar(2048) NULL,
  `response_status` int NULL,
  `duration_ms` int NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 4) Enable logging in code
```php
use JOOservices\Client\Factory\Factory;

$config = [
    'logging' => [
        'enabled' => true,
        'driver' => 'mysql',
        'connection' => [
            'mysql' => [
                'enabled' => true,
                'host' => getenv('JOOCLIENT_DB_HOST'),
                'port' => getenv('JOOCLIENT_DB_PORT'),
                'database' => getenv('JOOCLIENT_DB_DATABASE'),
                'username' => getenv('JOOCLIENT_DB_USERNAME'),
                'password' => getenv('JOOCLIENT_DB_PASSWORD'),
                'table' => getenv('JOOCLIENT_DB_TABLE'),
                'batch' => getenv('JOOCLIENT_DB_BATCH') === 'true',
                'fallback' => getenv('JOOCLIENT_DB_FALLBACK') ?: 'error_log',
            ],
        ],
    ],
];

$factory = (new Factory())->enableLogging($config);
$client = $factory->make();
$response = $client->get('https://example.com');
$client->flushLogger(); // important when batch=true
```

## 5) Troubleshooting (non-Laravel)
- Connection refused: verify host/port/creds; test with `mysql -h 127.0.0.1 -P 3306 -u root -p`.
- Missing table: run the SQL above (or your own migration) before enabling logging.
- Fallback behavior: set `JOOCLIENT_DB_FALLBACK=throw` to fail fast during setup; default is `error_log`.
- Batch mode: remember to call `flushLogger()` in long-running scripts.
