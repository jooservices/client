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

    public function __invoke(RequestInterface $request, array $options, Closure $next): ResponseInterface
    {
        $start = microtime(true);
        $method = $request->getMethod();
        $uri = (string) $request->getUri();
        $correlationId = $request->getHeaderLine(CorrelationIdMiddleware::HEADER_NAME);
        $targetHostname = parse_url($uri, PHP_URL_HOST);
        $transferStats = $this->getTransferStatsBag($options);

        $context = [
            'method' => $method,
            'uri' => $uri,
            'correlation_id' => $correlationId,
            'target_hostname' => is_string($targetHostname) ? $targetHostname : null,
            'target_ip' => $transferStats?->targetIp,
            'local_ip' => $transferStats?->localIp,
        ];

        if ($this->wanIpProvider !== null) {
            $context['wan_ip'] = $this->wanIpProvider->getPublicIp();
        }

        $this->logger->info("Sending request to {$method} {$uri}", $context);

        if ($this->logBodies) {
            // Be careful with large bodies!
            $this->logger->debug('Request Body', [
                'body' => (string) $request->getBody(),
                'headers' => $request->getHeaders()
            ]);
            $request->getBody()->rewind();
        }

        try {
            /** @var ResponseInterface $response */
            $response = $next($request, $options);

            $duration = round((microtime(true) - $start) * 1000, 2);
            $statusCode = $response->getStatusCode();

            $transferStats = $this->getTransferStatsBag($options);
            $context['target_ip'] = $transferStats?->targetIp;
            $context['local_ip'] = $transferStats?->localIp;

            $context['status'] = $statusCode;
            $context['duration_ms'] = $duration;

            $level = ($statusCode >= 400) ? 'error' : 'info';

            $this->logger->log(
                $level,
                "Received response {$statusCode} for {$method} {$uri} ({$duration}ms)",
                $context
            );

            if ($this->logBodies) {
                // If body is seekable, log it. CAUTION: Consumes stream if not rewindable.
                // PSR-7 streams usually comply, but Guzzle streams do.
                $body = (string) $response->getBody();
                $this->logger->debug('Response Body', [
                    'body' => $body,
                    'headers' => $response->getHeaders()
                ]);
                $response->getBody()->rewind();
            }

            return $response;
        } catch (Throwable $e) {
            $duration = round((microtime(true) - $start) * 1000, 2);
            $transferStats = $this->getTransferStatsBag($options);

            $context['target_ip'] = $transferStats?->targetIp;
            $context['local_ip'] = $transferStats?->localIp;
            $context['duration_ms'] = $duration;
            $context['exception'] = $e->getMessage();

            $this->logger->error("Exception for {$method} {$uri}: " . $e->getMessage(), $context);
            throw $e;
        }
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
