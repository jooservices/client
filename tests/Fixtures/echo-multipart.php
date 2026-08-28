<?php

declare(strict_types=1);

header('Content-Type: application/json');

$files = [];
foreach ($_FILES as $field => $file) {
    if (! is_array($file) || ! isset($file['error'], $file['name'], $file['type'], $file['size'], $file['tmp_name'])) {
        continue;
    }

    $files[$field] = [
        'name' => $file['name'],
        'type' => $file['type'],
        'size' => $file['size'],
        'error' => $file['error'],
        'contents' => $file['error'] === UPLOAD_ERR_OK && is_string($file['tmp_name'])
            ? file_get_contents($file['tmp_name'])
            : null,
    ];
}

echo json_encode(['post' => $_POST, 'files' => $files], JSON_THROW_ON_ERROR);
