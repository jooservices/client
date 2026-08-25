<?php

declare(strict_types=1);

namespace JOOservices\Client\Exceptions;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

final class BulkheadRejectedException extends ClientException implements NetworkExceptionInterface
{
    public function __construct(private readonly RequestInterface $request, string $message = 'The client-side bulkhead is full.')
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
