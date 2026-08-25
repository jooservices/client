<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final readonly class Psr17Bundle
{
    public function __construct(
        public RequestFactoryInterface $requests,
        public StreamFactoryInterface $streams,
        public UriFactoryInterface $uris,
        public ?ResponseFactoryInterface $responses = null,
    ) {
    }
}
