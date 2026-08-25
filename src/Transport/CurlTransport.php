<?php

declare(strict_types=1);

namespace JOOservices\Client\Transport;

use JOOservices\Client\Contracts\TransportCapabilities;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Support\RedirectHandler;
use JOOservices\Client\Transport\Curl\CurlExchange;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final class CurlTransport implements TransportInterface
{
    private readonly RedirectHandler $redirects;

    private readonly CurlExchange $exchange;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        UriFactoryInterface $uriFactory,
    ) {
        $this->redirects = new RedirectHandler($uriFactory, $streamFactory);
        $this->exchange = new CurlExchange($responseFactory, $streamFactory);
    }

    public function capabilities(): TransportCapabilities
    {
        return new TransportCapabilities([
            'timeout' => true,
            'connectTimeout' => true,
            'proxy' => true,
            'verifySsl' => true,
            'allowRedirects' => true,
        ]);
    }

    public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
    {
        if (! extension_loaded('curl')) {
            throw new InvalidConfigurationException('ext-curl is required for CurlTransport; enable it or configure withPsr18().');
        }

        $scheme = strtolower($request->getUri()->getScheme());
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new RequestException($request, 'CurlTransport only sends HTTP and HTTPS requests.');
        }

        return $this->redirects->send(
            $request,
            $options,
            fn(RequestInterface $current, RequestOptions $inner, ?array $pinnedAddresses): ResponseInterface => $this->exchange->send($current, $inner, $pinnedAddresses),
        );
    }

    public function __destruct()
    {
        $this->exchange->close();
    }
}
