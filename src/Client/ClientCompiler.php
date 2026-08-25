<?php

declare(strict_types=1);

namespace JOOservices\Client\Client;

use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\ClientConfig;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Middleware\MiddlewarePipeline;
use JOOservices\Client\Support\Psr17Bundle;
use JOOservices\Client\Transport\CurlTransport;
use Nyholm\Psr7\Factory\Psr17Factory;

final class ClientCompiler
{
    public function compile(ClientConnection $connection, ClientWiring $wiring): HttpClient
    {
        $factory = new Psr17Factory();
        $bundle = $wiring->psr17;
        $requestFactory = $bundle instanceof Psr17Bundle ? $bundle->requests : $factory;
        $streamFactory = $bundle instanceof Psr17Bundle ? $bundle->streams : $factory;
        $uriFactory = $bundle instanceof Psr17Bundle ? $bundle->uris : $factory;
        $responseFactory = $bundle instanceof Psr17Bundle && $bundle->responses !== null ? $bundle->responses : $factory;
        $transport = $wiring->transport ?? new CurlTransport($responseFactory, $streamFactory, $uriFactory);
        $this->assertTransport($transport, $wiring);
        $config = new ClientConfig(
            $connection->baseUri,
            $connection->timeout,
            $connection->connectTimeout,
            $connection->headers,
            $connection->verifySsl,
            $connection->allowRedirects,
            $connection->proxy,
        );

        return new HttpClient(
            $transport,
            new MiddlewarePipeline(array_values($wiring->middlewares), $transport),
            $config,
            new HttpClientSupport($requestFactory, $streamFactory, $uriFactory),
        );
    }

    private function assertTransport(TransportInterface $transport, ClientWiring $wiring): void
    {
        $capabilities = $transport->capabilities();
        foreach ($wiring->explicitCapabilities as $property => $enabled) {
            unset($enabled);
            $supported = match ($property) {
                'timeout' => $capabilities->timeout,
                'connectTimeout' => $capabilities->connectTimeout,
                'proxy' => $capabilities->proxy,
                'verifySsl' => $capabilities->verifySsl,
                'allowRedirects' => $capabilities->allowRedirects,
                default => throw new InvalidConfigurationException(sprintf('Unknown capability "%s".', $property)),
            };
            if ($supported === false) {
                throw new InvalidConfigurationException(sprintf('The selected transport cannot honor explicit client option "%s".', $property));
            }
        }
    }
}
