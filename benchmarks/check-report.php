<?php

declare(strict_types=1);

// PHPBench discovers the first class in a file. A successful empty/partial
// run is not evidence that the requested suite was measured.
$report = file_get_contents($argv[1] ?? '');
if ($report === false) {
    throw new RuntimeException('Cannot read PHPBench report.');
}
$report = preg_replace('/\x1b\[[0-9;]*m/', '', $report);
$expected = [
    'CqrsBench' => 7,
    'AttributeBench' => 36,
    'MetadataBench' => 5,
    'RuntimeBench' => 9,
    'RegistrationBench' => 3,
];
foreach ($expected as $class => $count) {
    preg_match_all('/^\|\s*' . preg_quote($class, '/') . '\s*\|/m', $report, $rows);
    if (count($rows[0]) !== $count) {
        throw new RuntimeException(sprintf('Expected %d result rows for %s, found %d.', $count, $class, count($rows[0])));
    }
}
echo "All expected benchmark scenarios were measured.\n";
