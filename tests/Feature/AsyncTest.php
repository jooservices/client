<?php

declare(strict_types=1);

namespace Tests\Feature;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Contracts\AsyncHttpClientInterface;
use JOOservices\Client\Contracts\ResponseWrapperInterface;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('feature')]
class AsyncTest extends TestCase
{
    public function test_async_request_returns_a_promise_resolving_to_response_wrapper(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"data": "async"}'),
        ]);
        $handler = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->build();

        /** @var AsyncHttpClientInterface $client */
        $promise = $client->getAsync('/test');

        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = $promise->wait();
        $this->assertInstanceOf(ResponseWrapperInterface::class, $response);
        $this->assertSame(200, $response->status());
        $this->assertSame(['data' => 'async'], $response->json());
    }

    public function test_batch_executes_multiple_requests_concurrently(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"id": 1}'),
            new Response(200, [], '{"id": 2}'),
            new Response(200, [], '{"id": 3}'),
        ]);
        $handler = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->build();

        /** @var AsyncHttpClientInterface $client */
        $results = $client->batch([
            'r1' => fn () => $client->getAsync('/1'),
            'r2' => fn () => $client->getAsync('/2'),
            'r3' => fn () => $client->getAsync('/3'),
        ]);

        $this->assertCount(3, $results);
        $this->assertSame(['id' => 3], $results['r3']->json());
    }

    public function test_batch_executes_Request_objects_concurrently(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"id": 1}'),
            new Response(200, [], '{"id": 2}'),
        ]);
        $handler = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->build();

        /** @var AsyncHttpClientInterface $client */
        $results = $client->batch([
            'r1' => new \GuzzleHttp\Psr7\Request('GET', 'http://example.com/1'),
            'r2' => new \GuzzleHttp\Psr7\Request('GET', 'http://example.com/2'),
        ]);

        $this->assertCount(2, $results);
        $this->assertSame(['id' => 1], $results['r1']->json());
        $this->assertSame(['id' => 2], $results['r2']->json());
    }

    public function test_async_POST_request_returns_a_promise_with_posted_data(): void
    {
        $mock = new MockHandler([
            new Response(201, [], '{"created": true, "id": 123}'),
        ]);
        $handler = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->build();

        /** @var AsyncHttpClientInterface $client */
        $promise = $client->postAsync('/users', [
            'json' => ['name' => 'John Doe', 'email' => 'john@example.com'],
        ]);

        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = $promise->wait();
        $this->assertInstanceOf(ResponseWrapperInterface::class, $response);
        $this->assertSame(201, $response->status());
        $this->assertSame(['created' => true, 'id' => 123], $response->json());
    }

    public function test_async_PUT_request_returns_a_promise_with_updated_data(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"updated": true, "id": 456}'),
        ]);
        $handler = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->build();

        /** @var AsyncHttpClientInterface $client */
        $promise = $client->requestAsync('PUT', '/users/456', [
            'json' => ['name' => 'Jane Doe'],
        ]);

        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = $promise->wait();
        $this->assertInstanceOf(ResponseWrapperInterface::class, $response);
        $this->assertSame(200, $response->status());
        $this->assertSame(['updated' => true, 'id' => 456], $response->json());
    }

    public function test_async_PATCH_request_returns_a_promise_with_partial_update(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"patched": true, "field": "value"}'),
        ]);
        $handler = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->build();

        /** @var AsyncHttpClientInterface $client */
        $promise = $client->requestAsync('PATCH', '/resources/789', [
            'json' => ['status' => 'active'],
        ]);

        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = $promise->wait();
        $this->assertInstanceOf(ResponseWrapperInterface::class, $response);
        $this->assertSame(200, $response->status());
        $this->assertSame(['patched' => true, 'field' => 'value'], $response->json());
    }

    public function test_async_DELETE_request_returns_a_promise_with_deletion_confirmation(): void
    {
        $mock = new MockHandler([
            new Response(204, [], ''),
        ]);
        $handler = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->build();

        /** @var AsyncHttpClientInterface $client */
        $promise = $client->requestAsync('DELETE', '/resources/999');

        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = $promise->wait();
        $this->assertInstanceOf(ResponseWrapperInterface::class, $response);
        $this->assertSame(204, $response->status());
    }

    public function test_batch_executes_mixed_POST_and_GET_requests_concurrently(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"type": "get", "id": 1}'),
            new Response(201, [], '{"type": "post", "created": true}'),
            new Response(200, [], '{"type": "get", "id": 2}'),
        ]);
        $handler = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->build();

        /** @var AsyncHttpClientInterface $client */
        $results = $client->batch([
            'get1' => fn () => $client->getAsync('/item/1'),
            'post' => fn () => $client->postAsync('/items', ['json' => ['name' => 'New Item']]),
            'get2' => fn () => $client->getAsync('/item/2'),
        ]);

        $this->assertCount(3, $results);
        $this->assertSame(200, $results['get1']->status());
        $this->assertSame(201, $results['post']->status());
        $this->assertSame(200, $results['get2']->status());
    }
}
