<?php

declare(strict_types=1);

header('Content-Type: text/plain');
header('X-Received-Length: ' . strlen((string) file_get_contents('php://input')));
echo file_get_contents('php://input');
