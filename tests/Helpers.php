<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Helper to get temporary directory for tests
 */
function getTmpDir(): string
{
    // unique per test run
    return __DIR__ . '/tmp';
}

/**
 * Helper to clear temporary directory
 */
function clearTmpDir(): void
{
    $fs = new Filesystem();
    if ($fs->exists(getTmpDir())) {
        $fs->remove(getTmpDir());
    }
}

/**
 * Helper to make a sub-directory in tmp
 */
function makeTmpDir(string $subdir): string
{
    $fs = new Filesystem();
    $path = Path::join(getTmpDir(), $subdir);
    $fs->mkdir($path);

    return $path;
}
