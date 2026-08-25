<?php

declare(strict_types=1);

namespace JOOservices\Client\Request;

use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;

final class PreparedRequest
{
    public function __construct(private readonly RequestInterface $request, private readonly RequestOptions $options)
    {
    }
    public function toPsr(): RequestInterface
    {
        return $this->request;
    }
    public function options(): RequestOptions
    {
        return $this->options;
    }
}
