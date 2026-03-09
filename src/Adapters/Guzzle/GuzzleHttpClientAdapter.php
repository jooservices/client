<?php

declare(strict_types=1);

namespace JOOservices\Client\Adapters\Guzzle;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use JOOservices\Client\Contracts\TransportAdapterInterface;
use JOOservices\Client\Exceptions\ClientException;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\TimeoutException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class GuzzleHttpClientAdapter implements TransportAdapterInterface
{
    public function __construct(
        private GuzzleClientInterface $client
    ) {
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        try {
            return $this->client->send($request, $options);
        } catch (ConnectException $exception) {
            $this->handleConnectException($exception);
        } catch (GuzzleException $exception) {
            throw new ClientException('HTTP Client error: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function sendAsync(RequestInterface $request, array $options = []): \GuzzleHttp\Promise\PromiseInterface
    {
        return $this->client->sendAsync($request, $options)
            ->then(null, function ($reason) {
                if ($reason instanceof ConnectException) {
                    $this->handleConnectException($reason);
                }
                if ($reason instanceof GuzzleException) {
                    throw new ClientException('HTTP Client error: ' . $reason->getMessage(), 0, $reason);
                }
                if ($reason instanceof \Throwable) {
                    throw $reason;
                }
                return \GuzzleHttp\Promise\Create::rejectionFor($reason);
            });
    }

    /**
     * @throws NetworkConnectionException|TimeoutException
     */
    private function handleConnectException(ConnectException $exception): never
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            throw new TimeoutException('Request timed out: ' . $exception->getMessage(), 0, $exception);
        }
        throw new NetworkConnectionException('Network connection failed: ' . $exception->getMessage(), 0, $exception);
    }
}
