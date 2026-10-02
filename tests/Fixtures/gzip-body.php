<?php

declare(strict_types=1);

$value = $_GET['value'] ?? '';
$body = is_string($value) ? $value : '';
$encoded = gzencode($body);

if ($encoded === false) {
    http_response_code(500);

    exit;
}

header('Content-Type: text/plain');
header('Content-Encoding: gzip');
header('Content-Length: ' . strlen($encoded));
header('Vary: Accept-Encoding');
echo $encoded;
