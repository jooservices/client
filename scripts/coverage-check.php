#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Ensures line coverage is above the required minimum (default 98%).
 * Run after: vendor/bin/phpunit (which generates coverage/clover.xml).
 */
$cloverPath = dirname(__DIR__) . '/coverage/clover.xml';
$minimumPercent = (float) ($argv[1] ?? '98');

if (!is_file($cloverPath)) {
    fwrite(STDERR, "Coverage file not found: {$cloverPath}. Run phpunit with coverage first.\n");
    exit(1);
}

$xml = @simplexml_load_file($cloverPath);
if ($xml === false) {
    fwrite(STDERR, "Invalid Clover XML: {$cloverPath}\n");
    exit(1);
}

$project = $xml->project ?? $xml;
$metrics = $project->metrics;
if ($metrics === null) {
    fwrite(STDERR, "No project metrics in Clover XML.\n");
    exit(1);
}
$statements = (int) (string) ($metrics['statements'] ?? 0);
$covered = (int) (string) ($metrics['coveredstatements'] ?? 0);
$lineRate = $statements > 0 ? $covered / $statements : 0.0;
$linePercent = round($lineRate * 100, 2);

if ($linePercent < $minimumPercent) {
    fwrite(STDERR, "Line coverage {$linePercent}% is below minimum {$minimumPercent}%.\n");
    exit(1);
}

echo "Line coverage: {$linePercent}% (minimum: {$minimumPercent}%)\n";
exit(0);
