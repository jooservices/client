<?php

declare(strict_types=1);

namespace JOOservices\Client\Transport;

use JOOservices\Client\Contracts\TransportCapabilities;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\RequestException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class PsrTransport implements TransportInterface
{
    public function __construct(private readonly ClientInterface $client)
    {
    }

    public function capabilities(): TransportCapabilities
    {
        return new TransportCapabilities();
    }

    public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
    {
        unset($options);

        try {
            return $this->client->sendRequest($request);
        } catch (NetworkExceptionInterface $error) {
            // Normalize into this library's own exception types: an injected PSR-18 client's raw
            // exceptions aren't recognized by FailoverTransport's catch clauses, so without this a
            // failing PSR-18 client never triggers failover to the next configured transport.
            throw new NetworkConnectionException($request, $error->getMessage(), $error);
        } catch (RequestExceptionInterface $error) {
            throw new RequestException($request, $error->getMessage(), $error);
        } catch (ClientExceptionInterface $error) {
            throw new NetworkConnectionException($request, $error->getMessage(), $error);
        }
    }
}
