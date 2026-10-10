<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

/** @internal Shared request-options key for the active deadline context. */
final class DeadlineOption
{
    public const KEY = '__joo_client_deadline_ns';

    private function __construct()
    {
    }
}
