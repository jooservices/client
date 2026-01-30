# Troubleshooting

Common issues and solutions when using JOOClient.

## Contents

- **[Migration Guide](migration.md)** - Upgrading from older versions

## Common Issues

### Logs Not Appearing

1. Check logging is enabled in config
2. Verify database connection
3. Call `flushLogger()` if batch mode is enabled
4. Check fallback logs: `/tmp/jooclient_*_failures.log`

### Memory Issues

1. Enable batch mode
2. Increase PHP memory limit
3. Disable body logging for large responses

### Connection Errors

1. Verify services are running (MySQL, MongoDB, Redis)
2. Check credentials in `.env`
3. Verify firewall settings

## See Also

- **[Getting Started](../getting-started/)** - Installation
- **[Guides](../guides/)** - Feature guides

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
