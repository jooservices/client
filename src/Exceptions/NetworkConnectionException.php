<?php

declare(strict_types=1);

namespace JOOservices\Client\Exceptions;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

class NetworkConnectionException extends ClientException implements NetworkExceptionInterface
{
    public function __construct(private readonly RequestInterface $request, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
