<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MongoDB\Laravel\MongoDBServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

abstract class TestCase extends BaseTestCase
{
    /**
     * Load package config so DB connection is env-driven (config/database.php).
     */
    protected function getEnvironmentSetUp($app): void
    {
        $dbConfig = require dirname(__DIR__) . '/config/database.php';
        $app['config']->set('database.default', $dbConfig['default']);
        $app['config']->set('database.connections.mongodb', $dbConfig['connections']['mongodb']);
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MongoDBServiceProvider::class];
    }

    protected static function getTmpDir(): string
    {
        return __DIR__ . '/tmp';
    }

    protected function clearTmpDir(): void
    {
        $fs = new Filesystem();
        if ($fs->exists(self::getTmpDir())) {
            $fs->remove(self::getTmpDir());
        }
    }

    protected function makeTmpDir(string $subdir): string
    {
        $fs = new Filesystem();
        $path = Path::join(self::getTmpDir(), $subdir);
        $fs->mkdir($path);

        return $path;
    }

    /**
     * @param array<string, mixed> $capturedOptions
     */
    protected function captureGuzzleOptions(array &$capturedOptions): HandlerStack
    {
        $mock = new MockHandler([new Response(200)]);
        $stack = HandlerStack::create($mock);
        $stack->push(function (callable $handler) use (&$capturedOptions) {
            return function (RequestInterface $request, array $options) use ($handler, &$capturedOptions) {
                $capturedOptions = array_merge($capturedOptions, $options);
                foreach ($request->getHeaders() as $name => $values) {
                    $capturedOptions['headers'][$name] = implode(', ', $values);
                }
                return $handler($request, $options);
            };
        });
        return $stack;
    }
}
