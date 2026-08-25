<?php

declare(strict_types=1);

namespace JOOservices\Client\Exceptions;

use Psr\Http\Message\ResponseInterface;

final class HttpResponseException extends ClientException
{
    public function __construct(private readonly ResponseInterface $response)
    {
        parent::__construct(sprintf('HTTP request failed with status %d.', $response->getStatusCode()));
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
