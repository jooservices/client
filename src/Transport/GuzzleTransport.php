<?php

declare(strict_types=1);

namespace JOOservices\Client\Transport;

use JOOservices\Client\Contracts\TransportCapabilities;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\RequestException;
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
            'compression' => true,
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
            function (RequestInterface $current, RequestOptions $inner, ?array $pinnedAddresses): ResponseInterface {
                if ($pinnedAddresses !== null) {
                    throw new RequestException(
                        $current,
                        'GuzzleTransport cannot enforce verified DNS pinning for this cross-host redirect.',
                    );
                }

                return $this->sender->send($current, $inner);
            },
        );
    }
}
