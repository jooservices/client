<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

final class PortableRequestOptions
{
    public const TIMEOUT = 'timeout';
    public const CONNECT_TIMEOUT = 'connectTimeout';
    public const PROXY = 'proxy';
    public const VERIFY_SSL = 'verifySsl';
    public const ALLOW_REDIRECTS = 'allowRedirects';
}
