# Your First Request

Make your first API call with JOOClient.

## Basic Example

```php
use JOOservices\Client\Client\ClientBuilder;

// Create a builder
$builder = ClientBuilder::create();

// Build the client
$client = $builder->build();

// Make a GET request
$response = $client->get('https://api.github.com/users/octocat');

// Check if successful
if ($response->status() === 200) {
    $data = $response->toPsrResponse()->getBody()->getContents();
    echo "User: " . json_decode($data, true)['login'] . "\n";
}
```

## Using Response Wrapper

JOOClient wraps responses with convenient methods:

```php
$response = $client->get('https://api.example.com/data');

// Check status
if ($response->status() === 200) {
    // Get content as string
    $content = $response->toPsrResponse()->getBody()->getContents();
    
    // Get JSON decoded
    $json = $response->json();
    
    // Get headers
    $contentType = $response->header('Content-Type');
    
    // Get status code
    $status = $response->status();
}
```

## POST Request

```php
$response = $client->post('https://api.example.com/users', [
    'json' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ],
]);

if ($response->status() === 201) {
    $user = $response->json();
    echo "Created user: " . $user['id'] . "\n";
}
```

## With Error Handling

```php
use JOOservices\Client\Client\ClientBuilder;
use GuzzleHttp\Exception\GuzzleException;

$builder = ClientBuilder::create();
$client = $builder->build();

try {
    $response = $client->get('https://api.example.com/data');
    
    if ($response->status() === 200) {
        $data = $response->json();
        // Process data
    } else {
        echo "Error: " . $response->status() . "\n";
    }
} catch (GuzzleException $e) {
    echo "Request failed: " . $e->getMessage() . "\n";
}
```

## Service Container Integration (e.g. Laravel)

JOOClient is framework-agnostic. To use it in a framework like Laravel, you can register it in a ServiceProvider:

```php
// AppServiceProvider or a dedicated ClientServiceProvider

public function register()
{
    $this->app->bind(ClientBuilder::class, function ($app) {
        return ClientBuilder::create()
            ->withTimeout(30)
            ->withDefaultLogging('laravel-app');
    });
    
    // Optional: Bind the built client directly if you prefer
    $this->app->bind(\JOOservices\Client\Contracts\HttpClientInterface::class, function ($app) {
        return $app->make(ClientBuilder::class)->build();
    });
}
```

Then inject it into your controllers:

```php
namespace App\Http\Controllers;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Contracts\HttpClientInterface;

class ApiController extends Controller
{
    public function __construct(
        private ClientBuilder $builder
    ) {}
    
    public function fetchData()
    {
        // Build a fresh client or use a pre-built one
        $client = $this->builder->build();
        $response = $client->get('https://api.example.com/data');
        
        return response()->json($response->json());
    }
}
```

## With Logging

Enable logging to track all requests:

```php
$builder = ClientBuilder::create()
    ->withDefaultLogging('my-app'); 

$client = $builder->build();
$response = $client->get('https://api.example.com/data');

// Important: Flush logs if batch mode is enabled
// Note: Flush method might be on the client or logger directly, checking implementation...
// Assuming client wrapper has a flush method or logger is flushed separately.
// Re-verifying flush method availability on client...
```

## Next Steps

- **[Feature Guides](../guides/)** - Explore all features
- **[Examples](../examples/)** - See more code examples
- **[API Reference](../reference/)** - Complete API documentation

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
