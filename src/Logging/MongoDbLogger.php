<?php

declare(strict_types=1);

namespace JOOservices\Client\Logging;

use DateTimeImmutable;
use DateTimeZone;
use JOOservices\Client\Models\Mongo\ClientRequestLog;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Stringable;
use Throwable;

final class MongoDbLogger implements LoggerInterface
{
    private string $connection;

    private string $collection;

    private int $maxRequestBodyBytes;

    private int $maxResponseBodyBytes;

    /** @var array<int, string> */
    private array $redactKeys;

    /** @var callable(array<string, mixed>): void */
    private $writer;

    /**
     * @param array<int, string> $redactKeys
     * @param (callable(array<string, mixed>): void)|null $writer
     */
    public function __construct(
        string $connection = 'mongodb',
        string $collection = 'client_request_logs',
        int $maxRequestBodyBytes = 4096,
        int $maxResponseBodyBytes = 8192,
        array $redactKeys = ['authorization', 'cookie', 'set-cookie', 'token'],
        ?callable $writer = null
    ) {
        if ($maxRequestBodyBytes < 0) {
            throw new RuntimeException('maxRequestBodyBytes must be >= 0');
        }

        if ($maxResponseBodyBytes < 0) {
            throw new RuntimeException('maxResponseBodyBytes must be >= 0');
        }

        $this->connection = $connection;
        $this->collection = $collection;
        $this->maxRequestBodyBytes = $maxRequestBodyBytes;
        $this->maxResponseBodyBytes = $maxResponseBodyBytes;
        $this->redactKeys = array_values(array_map(static fn (string $key): string => strtolower($key), $redactKeys));
        $this->writer = $writer ?? function (array $document): void {
            $this->persistViaModel($document);
        };
    }

    public function getConnection(): string
    {
        return $this->connection;
    }

    public function getCollection(): string
    {
        return $this->collection;
    }

    /**
     * @param mixed $level
     * @param Stringable|string $message
     * @param array<string, mixed> $context
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $document = $this->buildDocument($this->normalizeLevel($level), (string) $message, $context);

        try {
            ($this->writer)($document);
        } catch (Throwable) {
            // Logging failures should not break outgoing HTTP requests.
        }
    }

    /**
     * @param Stringable|string $message
     * @param array<string, mixed> $context
     */
    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @param Stringable|string $message
     * @param array<string, mixed> $context
     */
    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * @param Stringable|string $message
     * @param array<string, mixed> $context
     */
    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @param Stringable|string $message
     * @param array<string, mixed> $context
     */
    public function error(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param Stringable|string $message
     * @param array<string, mixed> $context
     */
    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * @param Stringable|string $message
     * @param array<string, mixed> $context
     */
    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @param Stringable|string $message
     * @param array<string, mixed> $context
     */
    public function info(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @param Stringable|string $message
     * @param array<string, mixed> $context
     */
    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildDocument(string $level, string $message, array $context): array
    {
        $normalizedContext = $this->normalizeContext($context);
        $document = [
            'level' => $level,
            'message' => $message,
            'context' => $normalizedContext,
            'logged_at' => new DateTimeImmutable('now', new DateTimeZone('UTC')),
        ];

        $this->copyIfPresent($document, $normalizedContext, 'method');
        $this->copyIfPresent($document, $normalizedContext, 'uri');
        $this->copyIfPresent($document, $normalizedContext, 'status');
        $this->copyIfPresent($document, $normalizedContext, 'duration_ms');
        $this->copyIfPresent($document, $normalizedContext, 'correlation_id');
        $this->copyIfPresent($document, $normalizedContext, 'exception');
        $this->copyIfPresent($document, $normalizedContext, 'local_ip');
        $this->copyIfPresent($document, $normalizedContext, 'wan_ip');
        $this->copyIfPresent($document, $normalizedContext, 'target_ip');
        $this->copyIfPresent($document, $normalizedContext, 'target_hostname');

        if ($message === 'Request Body' && isset($normalizedContext['body'])) {
            [$payload, $truncated] = $this->trimPayload(
                $this->stringifyPayload($normalizedContext['body']),
                $this->maxRequestBodyBytes
            );
            $document['request_payload'] = $payload;
            $document['payload_truncated'] = $truncated;
        }

        if ($message === 'Response Body' && isset($normalizedContext['body'])) {
            [$payload, $truncated] = $this->trimPayload(
                $this->stringifyPayload($normalizedContext['body']),
                $this->maxResponseBodyBytes
            );
            $document['response_payload'] = $payload;
            $document['payload_truncated'] = $truncated;
        }

        return $document;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $source
     */
    private function copyIfPresent(array &$target, array $source, string $key): void
    {
        if (array_key_exists($key, $source)) {
            $target[$key] = $source[$key];
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            $normalized[$key] = $this->normalizeValue((string) $key, $value);
        }

        return $normalized;
    }

    private function normalizeValue(string $key, mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value->format(DATE_ATOM);
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $innerKey => $innerValue) {
                $normalized[$innerKey] = $this->normalizeValue((string) $innerKey, $innerValue);
            }

            return $this->redactIfSensitive($key, $normalized);
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_object($value)) {
            return sprintf('[object:%s]', $value::class);
        }

        if (is_resource($value)) {
            return sprintf('[resource:%s]', get_resource_type($value));
        }

        return '[unknown]';
    }

    private function normalizeLevel(mixed $level): string
    {
        if (is_string($level)) {
            return $level;
        }

        if (is_int($level) || is_float($level) || is_bool($level)) {
            return (string) $level;
        }

        if ($level instanceof Stringable) {
            return (string) $level;
        }

        return LogLevel::INFO;
    }

    private function stringifyPayload(mixed $payload): string
    {
        if (is_string($payload)) {
            return $payload;
        }

        if (is_scalar($payload) || $payload === null) {
            return (string) $payload;
        }

        if ($payload instanceof Stringable) {
            return (string) $payload;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (is_string($encoded)) {
            return $encoded;
        }

        return '';
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>|string
     */
    private function redactIfSensitive(string $key, array $value): array|string
    {
        if (in_array(strtolower($key), $this->redactKeys, true)) {
            return '[REDACTED]';
        }

        foreach (array_keys($value) as $innerKey) {
            if (is_string($innerKey) && in_array(strtolower($innerKey), $this->redactKeys, true)) {
                $value[$innerKey] = '[REDACTED]';
            }
        }

        return $value;
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function trimPayload(string $body, int $limit): array
    {
        if ($limit === 0) {
            return ['', $body !== ''];
        }

        if (strlen($body) <= $limit) {
            return [$body, false];
        }

        return [substr($body, 0, $limit), true];
    }

    /**
     * Persist via Eloquent model only. Connection and collection come from model defaults (env-driven).
     *
     * @param array<string, mixed> $document
     */
    private function persistViaModel(array $document): void
    {
        $attributes = $document;
        if (isset($attributes['logged_at']) && $attributes['logged_at'] instanceof \DateTimeInterface) {
            $attributes['logged_at'] = $attributes['logged_at']->format('Y-m-d H:i:s');
        }
        (new ClientRequestLog())->fill($attributes)->save();
    }
}
