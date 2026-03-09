<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MongoDB\Client as MongoClient;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Helper to get temporary directory for tests
 */
function getTmpDir(): string
{
    // unique per test run
    return __DIR__ . '/tmp';
}

/**
 * Helper to clear temporary directory
 */
function clearTmpDir(): void
{
    $fs = new Filesystem();
    if ($fs->exists(getTmpDir())) {
        $fs->remove(getTmpDir());
    }
}

/**
 * Helper to make a sub-directory in tmp
 */
function makeTmpDir(string $subdir): string
{
    $fs = new Filesystem();
    $path = Path::join(getTmpDir(), $subdir);
    $fs->mkdir($path);

    return $path;
}

/**
 * Helper to capture options passed to Guzzle.
 *
 * @param array $capturedOptions Reference to array where options will be stored
 * @return HandlerStack
 */
function captureGuzzleOptions(array &$capturedOptions): HandlerStack
{
    $mock = new MockHandler([new Response(200)]);
    $stack = HandlerStack::create($mock);
    $stack->push(function (callable $handler) use (&$capturedOptions) {
        return function (RequestInterface $request, array $options) use ($handler, &$capturedOptions) {
            // Merge captured options
            $capturedOptions = array_merge($capturedOptions, $options);

            // Capture headers from request as they might be consumed from options
            foreach ($request->getHeaders() as $name => $values) {
                // Normalize to single value for test compatibility if needed,
                // or just Capture raw. Tests expect 'Value' string.
                $capturedOptions['headers'][$name] = implode(', ', $values);
            }

            return $handler($request, $options);
        };
    });
    return $stack;
}

/**
 * Check MongoDB availability for integration tests.
 */
function mongoAvailabilityError(): ?string
{
    if (!class_exists(MongoClient::class)) {
        return 'mongodb/mongodb client class not available.';
    }

    $uri = getenv('JOOCLIENT_MONGO_URI');
    if (!is_string($uri) || $uri === '') {
        $uri = 'mongodb://127.0.0.1:27017/?serverSelectionTimeoutMS=1200';
    }

    try {
        $client = new MongoClient($uri);
        $client->selectDatabase('admin')->command(['ping' => 1]);
    } catch (Throwable $e) {
        return 'MongoDB is not reachable on host: ' . $e->getMessage();
    }

    return null;
}
