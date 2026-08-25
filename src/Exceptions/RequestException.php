<?php

declare(strict_types=1);

namespace JOOservices\Client\Exceptions;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;

final class RequestException extends ClientException implements RequestExceptionInterface
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
