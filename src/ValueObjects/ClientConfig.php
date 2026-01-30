<?php

declare(strict_types=1);

namespace JOOservices\Client\ValueObjects;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use Symfony\Component\OptionsResolver\OptionsResolver;

final readonly class ClientConfig
{
    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $baseUri = '',
        public int $timeout = 30,
        public int $connectTimeout = 10,
        public array $headers = [],
        public bool $verifySsl = true,
        public bool $httpErrors = false,
        public array $options = []
    ) {
        if ($this->timeout < 0) {
            throw new InvalidConfigurationException('Timeout cannot be negative');
        }
        if ($this->connectTimeout < 0) {
            throw new InvalidConfigurationException('Connect timeout cannot be negative');
        }
    }

    /**
     * Convert to Guzzle options array.
     *
     * @return array<string, mixed>
     */
    public function toGuzzleOptions(): array
    {
        $defaults = [
            'base_uri' => $this->baseUri,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'headers' => $this->headers,
            'verify' => $this->verifySsl,
            'http_errors' => $this->httpErrors,
        ];

        // Merge custom options, but protect critical keys if needed.
        // For Phase 1, we allow overriding everything via options for flexibility.
        return array_merge($defaults, $this->options);
    }

    /**
     * Create from array with validation.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'baseUri' => '',
            'timeout' => 30,
            'connectTimeout' => 10,
            'headers' => [],
            'verifySsl' => true,
            'httpErrors' => false,
            'options' => [],
        ]);

        $resolver->setAllowedTypes('baseUri', 'string');
        $resolver->setAllowedTypes('timeout', 'int');
        $resolver->setAllowedTypes('connectTimeout', 'int');
        $resolver->setAllowedTypes('headers', 'array');
        $resolver->setAllowedTypes('verifySsl', 'bool');
        $resolver->setAllowedTypes('httpErrors', 'bool');
        $resolver->setAllowedTypes('options', 'array');

        $resolved = $resolver->resolve($config);

        /** @var string $baseUri */
        $baseUri = $resolved['baseUri'];
        /** @var int $timeout */
        $timeout = $resolved['timeout'];
        /** @var int $connectTimeout */
        $connectTimeout = $resolved['connectTimeout'];
        /** @var array<string, mixed> $headers */
        $headers = $resolved['headers'];
        /** @var bool $verifySsl */
        $verifySsl = $resolved['verifySsl'];
        /** @var bool $httpErrors */
        $httpErrors = $resolved['httpErrors'];
        /** @var array<string, mixed> $options */
        $options = $resolved['options'];

        return new self(
            baseUri: $baseUri,
            timeout: $timeout,
            connectTimeout: $connectTimeout,
            headers: $headers,
            verifySsl: $verifySsl,
            httpErrors: $httpErrors,
            options: $options,
        );
    }
}
