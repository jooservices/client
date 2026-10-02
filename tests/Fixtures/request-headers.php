<?php

declare(strict_types=1);

header('Content-Type: text/plain');
$acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? null;
header('X-Received-Accept-Encoding: ' . (is_string($acceptEncoding) ? $acceptEncoding : '<none>'));
echo 'ok';
