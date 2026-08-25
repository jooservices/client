<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Fixtures;

use JOOservices\Dto\Core\Dto;

final class UserDto extends Dto
{
    public function __construct(
        public readonly string $name = '',
        public readonly ?string $email = null,
        public readonly ?string $error = null,
    ) {
    }
}
