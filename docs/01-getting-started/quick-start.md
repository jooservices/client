# Quick Start

```php
use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->withBaseUri('https://api.example.com')
    ->withTimeout(5)
    ->build();

$response = $client->get('/health');
```

## Next Steps

- See `../02-user-guide/api-reference.md` for API surface
- See `../03-examples/` for runnable examples
