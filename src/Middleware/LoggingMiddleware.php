<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use Closure;
use JOOservices\Client\Client\HttpClient;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\WanIpProviderInterface;
use JOOservices\Client\Support\TransferStatsBag;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class LoggingMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private bool $logBodies;
    private ?WanIpProviderInterface $wanIpProvider;

    public function __construct(
        LoggerInterface $logger,
        bool $logBodies = false,
        ?WanIpProviderInterface $wanIpProvider = null
    ) {
        $this->logger = $logger;
        $this->logBodies = $logBodies;
        $this->wanIpProvider = $wanIpProvider;
    }

    /** @SuppressWarnings("PHPMD.ExcessiveMethodLength") */
    public function __invoke(RequestInterface $request, array $options, Closure $next): ResponseInterface
    {
        $start = microtime(true);
        $method = $request->getMethod();
        $uri = (string) $request->getUri();
        $correlationId = $request->getHeaderLine(CorrelationIdMiddleware::HEADER_NAME);
        $transferStats = $this->getTransferStatsBag($options);
        $targetHostname = $this->resolveTargetHostname($uri, $options, $transferStats);

        $context = [
            'method' => $method,
            'uri' => $uri,
            'correlation_id' => $correlationId,
            'target_hostname' => $targetHostname,
            'target_ip' => $transferStats?->targetIp,
            'local_ip' => $transferStats?->localIp,
        ];

        if ($this->wanIpProvider !== null) {
            $context['wan_ip'] = $this->wanIpProvider->getPublicIp();
        }

        $this->logger->info("Sending request to {$method} {$uri}", $context);

        if ($this->logBodies) {
            $this->logRequestBody($request);
        }

        try {
            /** @var ResponseInterface $response */
            $response = $next($request, $options);

            $duration = round((microtime(true) - $start) * 1000, 2);
            $statusCode = $response->getStatusCode();

            $context = $this->updateTransferContext($context, $options);

            $context['status'] = $statusCode;
            $context['duration_ms'] = $duration;

            $level = ($statusCode >= 400) ? 'error' : 'info';

            $this->logger->log(
                $level,
                "Received response {$statusCode} for {$method} {$uri} ({$duration}ms)",
                $context
            );

            if ($this->logBodies) {
                $this->logResponseBody($response);
            }

            return $response;
        } catch (Throwable $e) {
            $duration = round((microtime(true) - $start) * 1000, 2);
            $context = $this->updateTransferContext($context, $options);
            $context['duration_ms'] = $duration;
            $context['exception'] = $e->getMessage();

            $this->logger->error("Exception for {$method} {$uri}: " . $e->getMessage(), $context);
            throw $e;
        }
    }

    private function logRequestBody(RequestInterface $request): void
    {
        $this->logger->debug('Request Body', [
            'body' => (string) $request->getBody(),
            'headers' => $request->getHeaders(),
        ]);
        $request->getBody()->rewind();
    }

    private function logResponseBody(ResponseInterface $response): void
    {
        $this->logger->debug('Response Body', [
            'body' => (string) $response->getBody(),
            'headers' => $response->getHeaders(),
        ]);
        $response->getBody()->rewind();
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function updateTransferContext(array $context, array $options): array
    {
        $transferStats = $this->getTransferStatsBag($options);
        $context['target_ip'] = $transferStats?->targetIp;
        $context['local_ip'] = $transferStats?->localIp;

        if ($context['target_hostname'] === null && $transferStats?->effectiveUri !== null) {
            $context['target_hostname'] = $this->resolveTargetHostname(
                $transferStats->effectiveUri,
                $options,
                null
            );
        }

        return $context;
    }

    /**
     * Resolve target hostname from request URI, base_uri option, or transfer stats effectiveUri.
     *
     * @param array<string, mixed> $options
     */
    private function resolveTargetHostname(string $uri, array $options, ?TransferStatsBag $transferStats): ?string
    {
        $host = parse_url($uri, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        $baseUri = $options['base_uri'] ?? null;
        if ($baseUri !== null) {
            $uriString = $baseUri instanceof \Psr\Http\Message\UriInterface
                ? (string) $baseUri
                : (is_string($baseUri) ? $baseUri : '');
            $host = parse_url($uriString, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return $host;
            }
        }

        if ($transferStats?->effectiveUri !== null) {
            $host = parse_url($transferStats->effectiveUri, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return $host;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getTransferStatsBag(array $options): ?TransferStatsBag
    {
        $stats = $options[HttpClient::TRANSFER_STATS_OPTION_KEY] ?? null;

        return $stats instanceof TransferStatsBag ? $stats : null;
    }
}
