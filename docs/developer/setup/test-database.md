# Optional Test Database (only for integration-style checks)

## Do you need this?
No for the default suite. All core tests run with Guzzle mocks and in-memory doubles. Set up a database only if you want to exercise real persistence for logging drivers.

## Supported backends
- **SQLite (default fallback):** Used automatically; no setup required.
- **MySQL (optional):** Only if you want to observe real writes. Requires local MySQL and PDO MySQL extension.

## Minimal MySQL steps (optional)
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS jooclient;"
mysql -u root -p jooclient < database/create_client_request_logs_table.sql
```
Set env vars if you deviate from defaults:
```bash
export JOOCLIENT_TEST_MYSQL_DSN="mysql:host=127.0.0.1;port=3306"
export JOOCLIENT_TEST_MYSQL_USER="root"
export JOOCLIENT_TEST_MYSQL_PASS="root"
export JOOCLIENT_TEST_MYSQL_DB="jooclient"
```

## Running tests
- Mock-first (no DB): `composer test`
- With MySQL enabled: ensure the DB is reachable, then rerun `composer test`; DB-only cases will execute instead of skipping.

## Troubleshooting (optional use only)
- Connection refused: verify MySQL is running and DSN/port match.
- Skipped MySQL tests: expected when MySQL is unavailable—use SQLite/mock path instead.
