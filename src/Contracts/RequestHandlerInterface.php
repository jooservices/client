<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface RequestHandlerInterface
{
    public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface;
}
