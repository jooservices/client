<?php

declare(strict_types=1);

namespace JOOservices\Client\Transport\Guzzle;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\RequestException;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class GuzzleSender
{
    public function __construct(private readonly object $client)
    {
    }

    public function send(RequestInterface $request, RequestOptions $options): ResponseInterface
    {
        $sender = [$this->client, 'send'];
        if (! is_callable($sender)) {
            throw new InvalidConfigurationException('GuzzleTransport requires an object with send().');
        }

        try {
            $response = $sender($request, array_filter([
                'timeout' => $options->timeout,
                'connect_timeout' => $options->connectTimeout,
                'proxy' => $options->proxy,
                'verify' => $options->verifySsl,
                'allow_redirects' => false,
                'http_errors' => false,
            ], static fn(mixed $value): bool => $value !== null));
        } catch (NetworkExceptionInterface $error) {
            // Normalize into this library's own exception types: FailoverTransport's catch clauses
            // don't recognize a raw Guzzle/PSR-18 exception, so without this a failing Guzzle client
            // never triggers failover to the next configured transport.
            throw new NetworkConnectionException($request, $error->getMessage(), $error);
        } catch (RequestExceptionInterface $error) {
            throw new RequestException($request, $error->getMessage(), $error);
        } catch (Throwable $error) {
            throw new NetworkConnectionException($request, $error->getMessage(), $error);
        }

        if (! $response instanceof ResponseInterface) {
            throw new InvalidConfigurationException('GuzzleTransport send() must return a PSR-7 response.');
        }

        return $response;
    }
}
