<?php

declare(strict_types=1);

header('Content-Type: application/octet-stream');
for ($i = 0; $i < 200; ++$i) {
    echo str_repeat('x', 8192);
    flush();
}
