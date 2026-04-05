<?php

declare(strict_types=1);

namespace JOOservices\Client\Client;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\TransferStats;
use InvalidArgumentException;
use JOOservices\Client\Contracts\AsyncHttpClientInterface;
use JOOservices\Client\Contracts\HttpClientInterface;
use JOOservices\Client\Contracts\ResponseWrapperInterface;
use JOOservices\Client\Contracts\TransportAdapterInterface;
use JOOservices\Client\Response\ResponseWrapper;
use JOOservices\Client\Support\OptionsMerger;
use JOOservices\Client\Support\TransferStatsBag;
use JOOservices\Client\ValueObjects\ClientConfig;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class HttpClient implements HttpClientInterface, AsyncHttpClientInterface
{
    public const TRANSFER_STATS_OPTION_KEY = '_joo_transfer_stats';

    private OptionsMerger $merger;

    public function __construct(
        private TransportAdapterInterface $adapter,
        private ClientConfig $config,
        ?OptionsMerger $merger = null
    ) {
        $this->merger = $merger ?? new OptionsMerger();
    }

    public function get(string $uri, array $options = []): ResponseWrapperInterface
    {
        return $this->request('GET', $uri, $options);
    }

    public function post(string $uri, array $options = []): ResponseWrapperInterface
    {
        return $this->request('POST', $uri, $options);
    }

    public function put(string $uri, array $options = []): ResponseWrapperInterface
    {
        return $this->request('PUT', $uri, $options);
    }

    public function patch(string $uri, array $options = []): ResponseWrapperInterface
    {
        return $this->request('PATCH', $uri, $options);
    }

    public function delete(string $uri, array $options = []): ResponseWrapperInterface
    {
        return $this->request('DELETE', $uri, $options);
    }

    public function request(string $method, string $uri, array $options = []): ResponseWrapperInterface
    {
        $globalOptions = $this->config->toGuzzleOptions();
        $finalOptions = $this->merger->merge($globalOptions, $options);
        $finalOptions = $this->attachTransferStatsCollector($finalOptions);
        $request = new Request($method, $uri);
        $psrResponse = $this->adapter->send($request, $finalOptions);
        return new ResponseWrapper($psrResponse);
    }

    public function getAsync(string $uri, array $options = []): PromiseInterface
    {
        return $this->requestAsync('GET', $uri, $options);
    }

    public function postAsync(string $uri, array $options = []): PromiseInterface
    {
        return $this->requestAsync('POST', $uri, $options);
    }

    public function requestAsync(string $method, string $uri, array $options = []): PromiseInterface
    {
        $globalOptions = $this->config->toGuzzleOptions();
        $finalOptions = $this->merger->merge($globalOptions, $options);
        $finalOptions = $this->attachTransferStatsCollector($finalOptions);
        $request = new Request($method, $uri);

        return $this->adapter->sendAsync($request, $finalOptions)
            ->then(function (mixed $response): ResponseWrapper {
                if (!$response instanceof ResponseInterface) {
                    throw new InvalidArgumentException('Async adapter resolved to a non-response value.');
                }

                return new ResponseWrapper($response);
            });
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function attachTransferStatsCollector(array $options): array
    {
        $statsBag = new TransferStatsBag();
        $existingOnStats = $options['on_stats'] ?? null;

        $internalOnStats = static function (TransferStats $stats) use ($statsBag): void {
            $handlerStats = $stats->getHandlerStats();
            /** @phpstan-ignore function.alreadyNarrowedType (runtime may return non-array in some environments) */
            if (!is_array($handlerStats)) {
                return;
            }
            $primaryIp = $handlerStats['primary_ip'] ?? null;
            $localIp = $handlerStats['local_ip'] ?? null;

            $statsBag->targetIp = is_string($primaryIp) && $primaryIp !== '' ? $primaryIp : null;
            $statsBag->localIp = is_string($localIp) && $localIp !== '' ? $localIp : null;

            $effectiveUri = $stats->getEffectiveUri();
            /** @phpstan-ignore notIdentical.alwaysTrue (PSR-7 allows null at runtime) */
            $statsBag->effectiveUri = $effectiveUri !== null ? (string) $effectiveUri : null;
        };

        $options['on_stats'] = $internalOnStats;

        if (is_callable($existingOnStats)) {
            $options['on_stats'] = static function (TransferStats $stats) use (
                $internalOnStats,
                $existingOnStats
            ): void {
                $internalOnStats($stats);
                $existingOnStats($stats);
            };
        }

        $options[self::TRANSFER_STATS_OPTION_KEY] = $statsBag;

        return $options;
    }

    /**
     * @param iterable<array-key, PromiseInterface|RequestInterface|callable(): PromiseInterface> $requests
     */
    public function batch(iterable $requests, int $concurrency = 25): array
    {
        $results = [];

        $generator = function () use ($requests) {
            foreach ($requests as $key => $r) {
                $promise = $this->resolveBatchPromise($r);

                if (!$promise instanceof PromiseInterface) {
                    throw new InvalidArgumentException(
                        'Batch item must be a PromiseInterface, RequestInterface, or callable ' .
                        'returning PromiseInterface.'
                    );
                }

                yield $key => $this->wrapBatchPromise($promise, $key);
            }
        };

        $promise = \GuzzleHttp\Promise\Each::ofLimit(
            $generator(),
            $concurrency,
            function (mixed $wrapped) use (&$results): void {
                $this->storeWrappedResult($results, $wrapped);
            },
            function (mixed $reason): void {
                unset($reason);
            }
        );

        $promise->wait();

        return $results;
    }

    private function resolveBatchPromise(mixed $request): ?PromiseInterface
    {
        if ($request instanceof PromiseInterface) {
            return $request;
        }

        if ($request instanceof RequestInterface) {
            $options = [];
            $headers = $request->getHeaders();
            if ($headers !== []) {
                $options['headers'] = $headers;
            }

            $body = (string) $request->getBody();
            if ($body !== '') {
                $options['body'] = $body;
            }

            return $this->requestAsync($request->getMethod(), (string) $request->getUri(), $options);
        }

        if (is_callable($request)) {
            $promise = $request();

            return $promise instanceof PromiseInterface ? $promise : null;
        }

        return null;
    }

    private function wrapBatchPromise(PromiseInterface $promise, int|string $key): PromiseInterface
    {
        return $promise->then(
            static function (mixed $value) use ($key): array {
                return ['key' => $key, 'value' => $value, 'state' => 'fulfilled'];
            },
            static function (mixed $reason) use ($key): array {
                return ['key' => $key, 'value' => $reason, 'state' => 'rejected'];
            }
        );
    }

    /**
     * @param array<array-key, mixed> $results
     */
    private function storeWrappedResult(array &$results, mixed $wrapped): void
    {
        if (!is_array($wrapped) || !array_key_exists('key', $wrapped)) {
            return;
        }

        $key = $wrapped['key'];
        if (!is_int($key) && !is_string($key)) {
            return;
        }

        $results[$key] = $wrapped['value'] ?? null;
    }
}
