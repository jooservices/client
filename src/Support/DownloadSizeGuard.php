<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use JOOservices\Client\Exceptions\DownloadSizeExceededException;
use Psr\Http\Message\ResponseInterface;

final class DownloadSizeGuard
{
    public function assertWithin(ResponseInterface $response, int $maximumBytes): ResponseInterface
    {
        $length = $response->getHeaderLine('Content-Length');
        if ($length !== '' && ctype_digit($length) && (int) $length > $maximumBytes) {
            throw new DownloadSizeExceededException('Response body exceeds the configured download limit.');
        }

        return $response;
    }
}
