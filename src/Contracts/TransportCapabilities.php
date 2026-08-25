<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\InvalidConfigurationException;

final class TransportCapabilities
{
    public readonly bool $timeout;

    public readonly bool $connectTimeout;

    public readonly bool $proxy;

    public readonly bool $verifySsl;

    public readonly bool $allowRedirects;

    /** @param array<string, bool> $flags */
    public function __construct(array $flags = [])
    {
        $this->timeout = $flags['timeout'] ?? false;
        $this->connectTimeout = $flags['connectTimeout'] ?? false;
        $this->proxy = $flags['proxy'] ?? false;
        $this->verifySsl = $flags['verifySsl'] ?? false;
        $this->allowRedirects = $flags['allowRedirects'] ?? false;
    }

    public function intersect(self $other): self
    {
        return new self([
            'timeout' => $this->timeout && $other->timeout,
            'connectTimeout' => $this->connectTimeout && $other->connectTimeout,
            'proxy' => $this->proxy && $other->proxy,
            'verifySsl' => $this->verifySsl && $other->verifySsl,
            'allowRedirects' => $this->allowRedirects && $other->allowRedirects,
        ]);
    }

    public function assertHonors(RequestOptions $options): void
    {
        $pairs = [
            'timeout' => [$options->timeout, $this->timeout],
            'connectTimeout' => [$options->connectTimeout, $this->connectTimeout],
            'proxy' => [$options->proxy, $this->proxy],
            'verifySsl' => [$options->verifySsl, $this->verifySsl],
            'allowRedirects' => [$options->allowRedirects, $this->allowRedirects],
        ];

        foreach ($pairs as $field => [$value, $supported]) {
            if ($value !== null && $supported === false) {
                throw new InvalidConfigurationException(sprintf('The selected transport does not support request option "%s".', $field));
            }
        }

        if ($options->extra !== []) {
            throw new InvalidConfigurationException('Unknown request option(s): ' . implode(', ', array_keys($options->extra)));
        }
    }
}
