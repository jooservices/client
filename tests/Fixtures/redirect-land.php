<?php

declare(strict_types=1);

if (! isset($_GET['landed'])) {
    header('Location: /redirect-land.php?landed=1', true, 302);

    exit;
}

header('Content-Type: text/plain');
$cookie = $_SERVER['HTTP_COOKIE'] ?? '<none>';
echo is_string($cookie) ? $cookie : '<none>';
