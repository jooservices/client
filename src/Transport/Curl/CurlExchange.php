<?php

declare(strict_types=1);

namespace JOOservices\Client\Transport\Curl;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\DownloadSizeExceededException;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\TimeoutException;
use JOOservices\Client\Support\StreamContents;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class CurlExchange
{
    /** Safety ceiling against unbounded/chunked downloads when the caller hasn't set one. */
    private const DEFAULT_MAX_RESPONSE_BYTES = 104_857_600;

    public function __construct(
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
        private readonly CurlSession $session = new CurlSession(),
        private readonly CurlProxy $proxy = new CurlProxy(),
        private readonly StreamContents $body = new StreamContents(),
        private readonly int $maxResponseBytes = self::DEFAULT_MAX_RESPONSE_BYTES,
    ) {
    }

    /** @param list<string>|null $pinnedAddresses Force curl to connect to exactly these addresses for this request's host, skipping its own DNS lookup. See RedirectTargetPolicy::assertAllowed(). */
    public function send(RequestInterface $request, RequestOptions $options, ?array $pinnedAddresses = null): ResponseInterface
    {
        $handle = $this->session->handle();
        $headers = new CurlHeaderBuffer();
        $payload = $this->body->copyToResource($request->getBody());
        $resource = fopen('php://temp/maxmemory:2097152', 'w+b');
        if ($resource === false) {
            throw new NetworkConnectionException($request, 'Unable to allocate a temporary response stream.');
        }
        $verifySsl = $options->verifySsl ?? true;
        $proxy = $this->proxy->resolve($request, $options);
        $sizeCapExceeded = false;

        /** @var array<int, mixed> $curlOptions */
        $curlOptions = [
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_HTTPHEADER => $this->wireHeaders($request),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_HEADERFUNCTION => $headers->append(...),
            CURLOPT_WRITEFUNCTION => $this->writeCallback($resource, $sizeCapExceeded),
            CURLOPT_SHARE => $this->session->share(),
        ];
        if ($pinnedAddresses !== null && $pinnedAddresses !== []) {
            $curlOptions[CURLOPT_RESOLVE] = $this->resolveEntries($request, $pinnedAddresses);
        }

        $this->applyMethodAndBody($curlOptions, $request, $payload);
        $this->applyTimeouts($curlOptions, $options, $proxy);

        curl_setopt_array($handle, $curlOptions);
        $completed = curl_exec($handle);
        fclose($payload);
        if ($completed === false) {
            fclose($resource);
            $this->fail($request, curl_errno($handle), curl_error($handle), $sizeCapExceeded);
        }

        $status = $headers->status === 0 ? curl_getinfo($handle, CURLINFO_RESPONSE_CODE) : $headers->status;
        rewind($resource);
        $stream = $this->streams->createStreamFromResource($resource);

        $response = $this->responses->createResponse($status)->withBody($stream);
        foreach ($headers->headers as $name => $values) {
            $response = $response->withHeader($name, $values);
        }

        return $response;
    }

    public function close(): void
    {
        $this->session->close();
    }

    /**
     * @param resource $resource
     * @return \Closure(mixed, string): int
     */
    private function writeCallback(mixed $resource, bool &$sizeCapExceeded): \Closure
    {
        $bytesWritten = 0;

        return function (mixed $handle, string $chunk) use ($resource, &$bytesWritten, &$sizeCapExceeded): int {
            unset($handle);
            $bytesWritten += strlen($chunk);
            if ($bytesWritten > $this->maxResponseBytes) {
                $sizeCapExceeded = true;

                return 0;
            }

            $written = fwrite($resource, $chunk);

            return $written === false ? 0 : $written;
        };
    }

    /**
     * @param list<string> $addresses
     * @return list<string>
     */
    private function resolveEntries(RequestInterface $request, array $addresses): array
    {
        $host = $request->getUri()->getHost();
        $port = $request->getUri()->getPort() ?? (strtolower($request->getUri()->getScheme()) === 'https' ? 443 : 80);

        return array_map(
            static fn(string $address): string => sprintf('%s:%d:%s', $host, $port, self::formatPinnedAddress($address)),
            $addresses,
        );
    }

    /** libcurl's CURLOPT_RESOLVE requires IPv6 as host:port:[2001:db8::1], not host:port:2001:db8::1. */
    private static function formatPinnedAddress(string $address): string
    {
        $address = trim($address, '[]');

        return str_contains($address, ':') ? '[' . $address . ']' : $address;
    }

    /** @return list<string> */
    private function wireHeaders(RequestInterface $request): array
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = $name . ': ' . $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<int, mixed> $curlOptions
     * @param resource $payload
     */
    private function applyMethodAndBody(array &$curlOptions, RequestInterface $request, mixed $payload): void
    {
        if (strtoupper($request->getMethod()) === 'HEAD') {
            $curlOptions[CURLOPT_NOBODY] = true;

            return;
        }

        $stat = fstat($payload);
        $length = is_array($stat) ? $stat['size'] : 0;
        if ($length > 0 || in_array(strtoupper($request->getMethod()), ['POST', 'PUT', 'PATCH'], true)) {
            $curlOptions[CURLOPT_UPLOAD] = true;
            $curlOptions[CURLOPT_INFILE] = $payload;
            $curlOptions[CURLOPT_INFILESIZE_LARGE] = $length;
        }
    }

    /**
     * @param array<int, mixed> $curlOptions
     */
    private function applyTimeouts(array &$curlOptions, RequestOptions $options, ?string $proxy): void
    {
        if ($options->timeout !== null) {
            $curlOptions[CURLOPT_TIMEOUT_MS] = (int) round($options->timeout * 1000);
        }

        if ($options->connectTimeout !== null) {
            $curlOptions[CURLOPT_CONNECTTIMEOUT_MS] = (int) round($options->connectTimeout * 1000);
        }

        if ($proxy !== null) {
            $curlOptions[CURLOPT_PROXY] = $proxy;
        }
    }

    private function fail(RequestInterface $request, int $number, string $message, bool $sizeCapExceeded): never
    {
        if ($number === CURLE_OPERATION_TIMEDOUT) {
            throw new TimeoutException($request, 'The HTTP request timed out.');
        }

        // CURLE_WRITE_ERROR also fires when fwrite() itself fails (disk full, permission error, ...) —
        // the write callback returns 0 either way. Only report it as the size cap when that's actually
        // why the callback aborted, instead of always blaming a local write failure on response size.
        if ($number === CURLE_WRITE_ERROR && $sizeCapExceeded) {
            throw new DownloadSizeExceededException(sprintf('Response body exceeds the %d byte download limit.', $this->maxResponseBytes));
        }

        throw new NetworkConnectionException($request, 'HTTP network failure: ' . $message);
    }
}
