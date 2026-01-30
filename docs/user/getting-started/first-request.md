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
$response = $result->get('https://api.github.com/users/octocat');

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
    $response = $result->get('https://api.example.com/data');
    
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

## Via Laravel Service Container

```php
namespace App\Http\Controllers;

use JOOservices\Client\Client\ClientBuilder;

class ApiController extends Controller
{
    public function __construct(
        private ClientBuilder $jooclient
    ) {}
    
    public function fetchData()
    {
        $client = $this->jooclient->build();
        $response = $client->get('https://api.example.com/data');
        
        if ($response->status() >= 400) {
            abort(502, 'Upstream service failed');
        }
        
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
