<?php

declare(strict_types=1);

namespace JOOservices\Client\Exceptions;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

final class CircuitOpenException extends ClientException implements NetworkExceptionInterface
{
    public function __construct(private readonly RequestInterface $request)
    {
        parent::__construct('The circuit is open.');
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
