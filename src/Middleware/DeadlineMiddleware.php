<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\TimeoutException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class DeadlineMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly float $seconds)
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $started = hrtime(true);

        try {
            $response = $handler->handle($request, new RequestOptions(
                timeout: min($options->timeout ?? $this->seconds, $this->seconds),
                connectTimeout: $options->connectTimeout,
                proxy: $options->proxy,
                verifySsl: $options->verifySsl,
                allowRedirects: $this->redirectBudget($options->allowRedirects),
                extra: $options->extra,
                compression: $options->compression,
            ));
        } catch (\Throwable $error) {
            // The per-attempt timeout above only bounds a single HTTP attempt; a retry/backoff sequence
            // wrapped by this middleware can still blow past the deadline before finally throwing. Make
            // sure that's surfaced here too, not just on the success path.
            if ($this->elapsedSeconds($started) > $this->seconds) {
                throw new TimeoutException($request, 'The client deadline elapsed.', $error);
            }

            throw $error;
        }

        if ($this->elapsedSeconds($started) > $this->seconds) {
            throw new TimeoutException($request, 'The client deadline elapsed.');
        }

        return $response;
    }

    private function elapsedSeconds(int $started): float
    {
        return (hrtime(true) - $started) / 1_000_000_000;
    }

    /**
     * Give the redirect chain the same wall-clock budget as the deadline. Without this the per-hop
     * timeout above would let a chain of N redirects run for up to N × deadline before the elapsed
     * check here ever fires.
     *
     * @param bool|array<string, mixed>|null $allow
     * @return bool|array<string, mixed>
     */
    private function redirectBudget(bool|array|null $allow): bool|array
    {
        if ($allow === false) {
            return false;
        }

        $configured = is_array($allow) ? $allow : [];
        $existing = $configured['total_timeout'] ?? null;
        $configured['total_timeout'] = is_numeric($existing)
            ? min((float) $existing, $this->seconds)
            : $this->seconds;

        return $configured;
    }
}
