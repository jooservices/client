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
use Throwable;

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

        $body = $request->getBody();
        $position = null;
        try {
            if ($body->isSeekable()) {
                $position = $body->tell();
            }
        } catch (Throwable $error) {
            throw new RequestException($request, 'Unable to preserve the request body position.', $error);
        }

        try {
            $response = $this->send($request);
        } catch (Throwable $sendFailure) {
            try {
                $this->restoreBodyPosition($request, $position);
            } catch (RequestException) {
                // Keep the send failure primary; a failed restore must not hide the original error.
            }

            throw $sendFailure;
        }

        $this->restoreBodyPosition($request, $position);

        return $response;
    }

    private function send(RequestInterface $request): ResponseInterface
    {
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

    private function restoreBodyPosition(RequestInterface $request, ?int $position): void
    {
        if ($position === null) {
            return;
        }

        try {
            $request->getBody()->seek($position);
        } catch (Throwable $error) {
            throw new RequestException($request, 'Unable to restore the request body position after sending.', $error);
        }
    }
}
