<?php

declare(strict_types=1);

$unitPath = $argv[1] ?? 'build/coverage/clover-Unit.xml';
$integrationPath = $argv[2] ?? 'build/coverage/clover-Integration.xml';
$outputPath = $argv[3] ?? 'build/coverage/clover-combined.xml';

if (! is_file($unitPath) || ! is_file($integrationPath)) {
    fwrite(STDERR, "Missing clover input files\n");
    exit(1);
}

$unit = simplexml_load_file($unitPath);
$integration = simplexml_load_file($integrationPath);

if ($unit === false || $integration === false) {
    fwrite(STDERR, "Invalid clover input\n");
    exit(1);
}

$project = $unit->project;
if ($project === null) {
    fwrite(STDERR, "Unit clover is missing project node\n");
    exit(1);
}

foreach ($integration->xpath('//file') as $file) {
    $domProject = dom_import_simplexml($project);
    $domSource = dom_import_simplexml($file);

    if ($domProject === false || $domSource === false) {
        fwrite(STDERR, "Unable to import clover nodes\n");
        exit(1);
    }

    $domProject->appendChild($domProject->ownerDocument->importNode($domSource, true));
}

$project->metrics['files'] = (string) count($unit->xpath('//file'));
$project->metrics['classes'] = (string) count($unit->xpath('//class'));

$statements = 0;
$covered = 0;

foreach ($unit->xpath('//line[@type="stmt"]') as $line) {
    ++$statements;
    if ((int) $line['count'] > 0) {
        ++$covered;
    }
}

$project->metrics['statements'] = (string) $statements;
$project->metrics['coveredstatements'] = (string) $covered;

if ($unit->asXML($outputPath) === false) {
    fwrite(STDERR, "Unable to write merged clover report\n");
    exit(1);
}

echo "Wrote {$outputPath}\n";
