<?php

declare(strict_types=1);

namespace JOOservices\Client\Client;

use JOOservices\Client\Support\HeaderValidator;
use JOOservices\Client\Support\UriResolver;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final readonly class HttpClientSupport
{
    public function __construct(
        public RequestFactoryInterface $requests,
        public StreamFactoryInterface $streams,
        public UriFactoryInterface $uris,
        public HeaderValidator $headers = new HeaderValidator(),
        public UriResolver $resolver = new UriResolver(),
        public RequestOptionsAssembler $options = new RequestOptionsAssembler(),
    ) {
    }
}
