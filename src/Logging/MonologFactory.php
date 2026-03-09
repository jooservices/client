<?php

declare(strict_types=1);

namespace JOOservices\Client\Logging;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class MonologFactory
{
    /**
     * Create a logger with daily rotation grouped by domain.
     *
     * @param string $domain The domain name for grouping (e.g. api.example.com)
     * @param string|null $path Base path for logs. Defaults to ./logs if null.
     * @param int $retentionDays Days to keep logs.
     * @return LoggerInterface
     */
    public static function createDaily(
        string $domain,
        ?string $path = null,
        int $retentionDays = 14
    ): LoggerInterface {
        // 1. Determine Path
        // If path is not provided, try ENV, else default to ./logs
        if ($path === null) {
            $path = getenv('JOO_CLIENT_LOG_PATH');
        }

        if (!is_string($path)) {
            $path = './logs';
        }
        if ($path === '' || $path === '.') { // Avoiding basic empty
            $path = './logs';
        }

        // 2. Sanitize Domain for Directory Name
        $safeDomain = preg_replace('/[^a-zA-Z0-9.-]/', '_', $domain);

        // 3. Construct Full Path
        // Pattern: base_path/domain/client.log (RotatingHandler adds date automatically: client-2023-10-27.log)
        $directory = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $safeDomain;
        $filename = $directory . DIRECTORY_SEPARATOR . 'client.log';

        // 4. Create Logger
        $logger = new Logger('client');
        $handler = new RotatingFileHandler($filename, $retentionDays, Logger::DEBUG);
        $handler->setFilenameFormat('{filename}-{date}', 'Y-m-d');

        $logger->pushHandler($handler);

        return $logger;
    }
}
