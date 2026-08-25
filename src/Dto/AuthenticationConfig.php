<?php

declare(strict_types=1);

namespace JOOservices\Client\Dto;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Dto\Core\Dto;

final class AuthenticationConfig extends Dto
{
    /** 'api-key' sends $value on $header verbatim, with no scheme prefix — the intentional escape hatch for custom header-based schemes. */
    private const VALID_TYPES = ['bearer', 'basic', 'api-key'];

    public function __construct(
        public readonly string $type,
        public readonly string $value,
        public readonly string $header = 'Authorization',
        public readonly string $prefix = 'Bearer',
    ) {
        if (! in_array($type, self::VALID_TYPES, true)) {
            throw new InvalidConfigurationException(sprintf(
                'Unknown authentication type "%s"; expected one of: %s.',
                $type,
                implode(', ', self::VALID_TYPES),
            ));
        }

        if ($value === '') {
            throw new InvalidConfigurationException('AuthenticationConfig value must not be empty.');
        }
    }
}
