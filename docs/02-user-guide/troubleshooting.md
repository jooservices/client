# Troubleshooting

## Common Issues

1. MongoDB logger cannot connect
- Verify `MONGODB_URI` and `MONGODB_DATABASE`.
- Ensure MongoDB service is running in Docker or local environment.

2. Live network tests are skipped
- Set `JOOCLIENT_RUN_LIVE_NETWORK_TESTS=1` when running test command.

3. Coverage gate fails
- Ensure unit/integration tests cover changed paths before running `composer test`.
