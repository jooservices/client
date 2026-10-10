<?php

declare(strict_types=1);

namespace JOOservices\Client\Transport;

use JOOservices\Client\Contracts\TransportCapabilities;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Exceptions\TimeoutException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class FailoverTransport implements TransportInterface
{
    /**
     * @param list<TransportInterface> $transports Must be non-empty; enforced below, not just typed,
     *   since this class is constructible directly and not every caller goes through
     *   ClientBuilder::withFailoverTransport()'s own guard.
     */
    public function __construct(private readonly array $transports)
    {
        if ($transports === []) {
            // ClientBuilder::withFailoverTransport() already guards this; this class is public and
            // constructible directly too, so the guard belongs here as well, not only at that call site.
            throw new InvalidConfigurationException('A failover transport requires at least one transport.');
        }
    }

    public function capabilities(): TransportCapabilities
    {
        $merged = $this->transports[0]->capabilities();
        foreach ($this->transports as $transport) {
            $merged = $merged->intersect($transport->capabilities());
        }

        return $merged;
    }

    public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
    {
        $last = new NetworkConnectionException($request, 'All failover transports failed.');
        $bodyPosition = $this->bodyPosition($request);
        foreach ($this->transports as $index => $transport) {
            if ($index > 0) {
                $this->restoreBodyPosition($request, $bodyPosition, $last);
            }

            try {
                return $transport->handle($request, $options);
            } catch (TimeoutException $error) {
                // Intentionally not failed over: a timeout means the configured deadline was already
                // spent waiting, and moving to the next transport just risks paying that same wait
                // again rather than fixing anything — unlike a connection-refused/DNS failure, which a
                // different network path genuinely can route around.
                throw $error;
            } catch (NetworkConnectionException $error) {
                $last = $error;
            }
        }

        throw $last;
    }

    private function bodyPosition(RequestInterface $request): ?int
    {
        $body = $request->getBody();
        try {
            return $body->isSeekable() ? $body->tell() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function restoreBodyPosition(RequestInterface $request, ?int $position, Throwable $previous): void
    {
        if ($position === null) {
            throw new RequestException($request, 'The request body cannot be replayed safely.', $previous);
        }

        $body = $request->getBody();
        try {
            if (! $body->isSeekable()) {
                throw new RequestException($request, 'The request body cannot be replayed safely.', $previous);
            }
            $body->seek($position);
        } catch (RequestException $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new RequestException($request, 'Unable to rewind the request body for failover.', $error);
        }
    }
}
