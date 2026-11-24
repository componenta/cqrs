<?php

declare(strict_types=1);

// Across a dependency migration the two suites must have isolated vendors.
// Compare common package versions before interpreting performance differences.
function lockedVersions(string $file): array
{
    $lock = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    $versions = [];
    foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
        $versions[$package['name']] = $package['version'];
    }
    ksort($versions, SORT_STRING);
    return $versions;
}

$baseline = lockedVersions($argv[1] ?? throw new InvalidArgumentException('Baseline lock required.'));
$candidate = lockedVersions($argv[2] ?? throw new InvalidArgumentException('Candidate lock required.'));
$removed = array_diff_key($baseline, $candidate);
$added = array_diff_key($candidate, $baseline);
echo 'Baseline-only dependencies: ' . json_encode($removed, JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Candidate-only dependencies: ' . json_encode($added, JSON_THROW_ON_ERROR) . PHP_EOL;
foreach (array_intersect_key($baseline, $candidate) as $package => $version) {
    if ($candidate[$package] !== $version) {
        throw new RuntimeException(sprintf(
            'Benchmark comparison requires matching common dependencies: %s (%s vs %s).',
            $package,
            $version,
            $candidate[$package],
        ));
    }
}
echo "All common dependency versions match.\n";
