<?php

declare(strict_types=1);

namespace JOOservices\Client\Testing;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class HttpFakeRegistry
{
    /** @var list<HttpFake> */
    private array $fakes = [];

    /** @var list<RecordedRequest> */
    private array $recorded = [];

    private bool $preventStray = true;

    public function respond(string $method, string $pattern, TestResponseSequence $responses): self
    {
        $this->fakes[] = new HttpFake($method, $pattern, $responses);

        return $this;
    }

    public function preventStrayRequests(bool $prevent = true): self
    {
        $this->preventStray = $prevent;

        return $this;
    }

    public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
    {
        $this->recorded[] = new RecordedRequest($request, $options);
        foreach ($this->fakes as $fake) {
            if (! $fake->isEmpty() && $fake->matches($request)) {
                $next = $fake->next();
                if ($next instanceof \Throwable) {
                    throw $next;
                }

                return $next;
            }
        }

        if ($this->preventStray) {
            throw new NetworkConnectionException($request, 'No HTTP fake matched the request.');
        }

        return new Response(404);
    }

    /** @return list<RecordedRequest> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function clear(): void
    {
        $this->fakes = [];
        $this->recorded = [];
        $this->preventStray = true;
    }
}
