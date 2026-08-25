<?php

declare(strict_types=1);

namespace JOOservices\Client\Dto;

use JOOservices\Dto\Core\Dto;

final class ResponseValidationConfig extends Dto
{
    public function __construct(public readonly bool $onlySuccessful = true)
    {
    }
}
