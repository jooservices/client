<?php

declare(strict_types=1);

namespace JOOservices\Client\Testing;

use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;

final readonly class RecordedRequest
{
    public function __construct(public RequestInterface $request, public RequestOptions $options)
    {
    }
}
