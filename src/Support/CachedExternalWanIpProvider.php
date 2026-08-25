<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use JOOservices\Client\Contracts\WanIpProviderInterface;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

final class CachedExternalWanIpProvider implements WanIpProviderInterface
{
    private ?string $cached = null;

    public function __construct(private readonly ClientInterface $client, private readonly RequestFactoryInterface $requests, private readonly string $endpoint = 'https://api.ipify.org')
    {
    }

    public function address(): string
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $address = trim((string) $this->client->sendRequest($this->requests->createRequest('GET', $this->endpoint))->getBody());
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            throw new InvalidConfigurationException('WAN IP provider returned an invalid IP address.');
        }

        return $this->cached = $address;
    }
}
