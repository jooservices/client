<?php

declare(strict_types=1);

namespace JOOservices\Client\Transport;

use JOOservices\Client\Contracts\TransportCapabilities;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Support\RedirectHandler;
use JOOservices\Client\Transport\Guzzle\GuzzleSender;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final class GuzzleTransport implements TransportInterface
{
    private readonly RedirectHandler $redirects;

    private readonly GuzzleSender $sender;

    public function __construct(
        object $client,
        UriFactoryInterface $uriFactory,
        StreamFactoryInterface $streamFactory,
    ) {
        $this->redirects = new RedirectHandler($uriFactory, $streamFactory);
        $this->sender = new GuzzleSender($client);
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
        return $this->redirects->send(
            $request,
            $options,
            // Guzzle (or any injected PSR-18-shaped client) does its own DNS resolution with no
            // equivalent of curl's CURLOPT_RESOLVE available through this generic send() contract, so
            // the verified pin RedirectHandler offers here can't be honored — it's simply unused.
            fn(RequestInterface $current, RequestOptions $inner, ?array $pinnedAddresses): ResponseInterface => $this->sender->send($current, $inner),
        );
    }
}
