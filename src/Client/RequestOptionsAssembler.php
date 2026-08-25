<?php

declare(strict_types=1);

namespace JOOservices\Client\Client;

use JOOservices\Client\Dto\ClientConfig;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\InvalidConfigurationException;

final class RequestOptionsAssembler
{
    /** @param array<string, mixed> $options */
    public function fromArray(array $options): RequestOptions
    {
        $known = ['timeout', 'connectTimeout', 'proxy', 'verifySsl', 'allowRedirects'];
        $extra = array_diff_key($options, array_flip($known));
        $timeout = $options['timeout'] ?? null;
        $connectTimeout = $options['connectTimeout'] ?? null;
        $proxy = $options['proxy'] ?? null;
        $verifySsl = $options['verifySsl'] ?? null;
        $allowRedirects = $options['allowRedirects'] ?? null;
        $this->assertTypes($timeout, $connectTimeout, $proxy, $verifySsl, $allowRedirects);

        /** @var string|array<string, mixed>|null $proxy */
        /** @var bool|null $verifySsl */
        /** @var bool|array<string, mixed>|null $allowRedirects */

        $timeoutSeconds = is_numeric($timeout) ? (float) $timeout : null;
        $connectSeconds = is_numeric($connectTimeout) ? (float) $connectTimeout : null;

        return new RequestOptions(
            $timeoutSeconds,
            $connectSeconds,
            $proxy,
            $verifySsl,
            $allowRedirects,
            $extra,
        );
    }

    public function merge(RequestOptions $delta, ClientConfig $config): RequestOptions
    {
        /** @var string|array<string, mixed>|null $proxy */
        $proxy = $delta->proxy ?? $config->proxy;
        /** @var bool|array<string, mixed> $allowRedirects */
        $allowRedirects = $delta->allowRedirects ?? $config->allowRedirects;

        return new RequestOptions(
            $delta->timeout ?? $config->timeout,
            $delta->connectTimeout ?? $config->connectTimeout,
            $proxy,
            $delta->verifySsl ?? $config->verifySsl,
            $allowRedirects,
            $delta->extra,
        );
    }

    public function assertTimeouts(RequestOptions $options): void
    {
        foreach ([$options->timeout, $options->connectTimeout] as $timeout) {
            if ($timeout !== null && $timeout <= 0) {
                throw new InvalidConfigurationException('Timeout values must be greater than zero.');
            }
        }
    }

    private function assertTypes(mixed $timeout, mixed $connectTimeout, mixed $proxy, mixed $verifySsl, mixed $allowRedirects): void
    {
        $this->assertNumericOrNull($timeout);
        $this->assertNumericOrNull($connectTimeout);
        $this->assertProxy($proxy);
        $this->assertSsl($verifySsl);
        $this->assertRedirects($allowRedirects);
    }

    private function assertNumericOrNull(mixed $value): void
    {
        if ($value !== null && ! is_numeric($value)) {
            throw new InvalidConfigurationException('Request options have invalid types.');
        }
    }

    private function assertProxy(mixed $proxy): void
    {
        if ($proxy !== null && ! is_string($proxy) && ! is_array($proxy)) {
            throw new InvalidConfigurationException('Request options have invalid types.');
        }
    }

    private function assertSsl(mixed $verifySsl): void
    {
        if ($verifySsl !== null && ! is_bool($verifySsl)) {
            throw new InvalidConfigurationException('Request options have invalid types.');
        }
    }

    private function assertRedirects(mixed $allowRedirects): void
    {
        if ($allowRedirects !== null && ! is_bool($allowRedirects) && ! is_array($allowRedirects)) {
            throw new InvalidConfigurationException('Request options have invalid types.');
        }
    }
}
