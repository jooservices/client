<?php

declare(strict_types=1);

$threshold = 85.0;
$pattern = $argv[1] ?? 'build/coverage/clover-*.xml';
/** @var list<string>|false $files */
$files = glob($pattern);

if ($files === false || count($files) === 0) {
    fwrite(STDERR, "No clover files found for pattern: {$pattern}\n");
    exit(1);
}

foreach ($files as $file) {
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        fwrite(STDERR, basename($file) . ": invalid clover\n");
        exit(1);
    }

    $metrics = $xml->project->metrics;
    $statements = (int) $metrics['statements'];
    $covered = (int) $metrics['coveredstatements'];
    $pct = $statements === 0 ? 100.0 : ($covered / $statements) * 100.0;

    echo basename($file) . ': ' . number_format($pct, 2) . "% ({$covered}/{$statements})\n";

    if ($pct < $threshold) {
        fwrite(STDERR, basename($file) . " below {$threshold}%\n");
        exit(1);
    }
}
