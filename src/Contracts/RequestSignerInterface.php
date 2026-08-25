<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

use Psr\Http\Message\RequestInterface;

interface RequestSignerInterface
{
    public function sign(RequestInterface $request): RequestInterface;
}
