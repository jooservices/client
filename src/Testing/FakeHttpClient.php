<?php

declare(strict_types=1);

namespace JOOservices\Client\Testing;

use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FakeHttpClient implements ClientInterface
{
    public function __construct(private readonly HttpFakeRegistry $registry)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->registry->handle($request, new RequestOptions());
    }
}
