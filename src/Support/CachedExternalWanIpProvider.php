<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use GuzzleHttp\Client as GuzzleClient;
use JOOservices\Client\Contracts\WanIpProviderInterface;
use Throwable;

final class CachedExternalWanIpProvider implements WanIpProviderInterface
{
    private ?string $cachedIp = null;

    private ?int $cachedAt = null;

    /** @var callable(): ?string */
    private $resolver;

    /**
     * @param callable(): ?string|null $resolver
     */
    public function __construct(
        private readonly int $cacheTtlSeconds = 3600,
        private readonly float $timeoutSeconds = 0.8,
        private readonly string $endpoint = 'https://api.ipify.org?format=json',
        ?callable $resolver = null
    ) {
        $this->resolver = $resolver ?? function (): ?string {
            return $this->resolveViaHttp();
        };
    }

    public function getPublicIp(): ?string
    {
        $now = time();

        if ($this->cachedIp !== null && $this->cachedAt !== null && ($now - $this->cachedAt) < $this->cacheTtlSeconds) {
            return $this->cachedIp;
        }

        try {
            $resolved = ($this->resolver)();
        } catch (Throwable) {
            $this->cachedAt = $now;
            return $this->cachedIp;
        }

        if (!is_string($resolved) || $resolved === '') {
            $this->cachedAt = $now;
            return $this->cachedIp;
        }

        if (filter_var($resolved, FILTER_VALIDATE_IP) === false) {
            $this->cachedAt = $now;
            return $this->cachedIp;
        }

        $this->cachedIp = $resolved;
        $this->cachedAt = $now;

        return $this->cachedIp;
    }

    private function resolveViaHttp(): ?string
    {
        $client = new GuzzleClient([
            'timeout' => $this->timeoutSeconds,
            'connect_timeout' => $this->timeoutSeconds,
            'http_errors' => false,
        ]);

        $response = $client->request('GET', $this->endpoint);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return null;
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (!is_array($decoded) || !isset($decoded['ip']) || !is_string($decoded['ip'])) {
            return null;
        }

        return $decoded['ip'];
    }
}
