<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use Composer\InstalledVersions;
use OutOfBoundsException;

final class PackageVersion
{
    public function value(): string
    {
        $reader = [InstalledVersions::class, 'getPrettyVersion'];

        try {
            $version = $reader('jooservices/client');
        } catch (OutOfBoundsException) {
            // getPrettyVersion() throws (rather than returning null) when the package isn't registered
            // in installed.php at all — a non-standard install layout, PHAR bundling, or a stale
            // autoloader cache. The null-return fallback below never runs for that case; this does.
            return '4.1.0-dev';
        }

        return is_string($version) ? $version : '4.1.0-dev';
    }
}
