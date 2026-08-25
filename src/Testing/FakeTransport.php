<?php

declare(strict_types=1);

namespace JOOservices\Client\Testing;

use JOOservices\Client\Contracts\TransportCapabilities;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FakeTransport implements TransportInterface
{
    /** @var list<ResponseInterface|\Throwable> */ private array $queue = [];
    /** @var list<array{request: RequestInterface, options: RequestOptions}> */ private array $recorded = [];
    public function capabilities(): TransportCapabilities
    {
        return new TransportCapabilities([
            'timeout' => true,
            'connectTimeout' => true,
            'proxy' => true,
            'verifySsl' => true,
            'allowRedirects' => true,
        ]);
    }
    public function push(ResponseInterface|\Throwable $response): self
    {
        $this->queue[] = $response;
        return $this;
    }
    /** @return list<array{request: RequestInterface, options: RequestOptions}> */ public function recorded(): array
    {
        return $this->recorded;
    }
    public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
    {
        $this->recorded[] = ['request' => $request, 'options' => $options];
        $next = array_shift($this->queue);
        if ($next instanceof \Throwable) {
            throw $next;
        }
        if ($next instanceof ResponseInterface) {
            return $next;
        }
        throw new NetworkConnectionException($request, 'The fake transport has no queued response.');
    }
}
