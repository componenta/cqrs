<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

// Use the same isolated source substitution as the Pest mutation worker.
// No production source files are modified, even when a child test fails.
$workspace = sys_get_temp_dir() . '/cqrs-negative-' . bin2hex(random_bytes(12));
if (!mkdir($workspace, 0700)) {
    throw new RuntimeException('Cannot create negative-control workspace.');
}

/** @return array{status: int, tests: int, failures: int, errors: int, skipped: int} */
function runControl(
    string $root,
    string $source,
    string $replacement,
    string $report,
    string $testFile,
    string $filter,
): array {
    if (is_file($report)) {
        unlink($report);
    }

    $process = new Process([
        PHP_BINARY, '-d', 'opcache.enable_cli=0', $root . '/tests/mutate.php',
        $testFile,
        '--filter="' . $filter . '"',
        '--colors=never', '--do-not-cache-result', '--log-junit=' . $report,
    ], $root, [
        'PEST_MUTATION_TESTING' => $source,
        'PEST_MUTATION_FILE' => $replacement,
    ], timeout: 60);
    $status = $process->run();
    if (!is_file($report)) {
        throw new RuntimeException('Missing JUnit report: ' . $process->getOutput() . $process->getErrorOutput());
    }

    $xml = simplexml_load_file($report);
    if ($xml === false) {
        throw new RuntimeException('Invalid JUnit report.');
    }

    return [
        'status' => $status,
        'tests' => count($xml->xpath('//testcase') ?: []),
        'failures' => count($xml->xpath('//testcase/failure') ?: []),
        'errors' => count($xml->xpath('//testcase/error') ?: []),
        'skipped' => count($xml->xpath('//testcase/skipped') ?: []),
    ];
}

$controls = [
    ['UTC independence', 'src/Clock/UtcClock.php', 'new DateTimeZone(\'UTC\')', 'new DateTimeZone(date_default_timezone_get())', 'tests/Clock/UtcClockTest.php', 'returns fresh immutable UTC time independent of the process timezone'],
    ['explicit creation timestamp', 'src/Command/Operation.php', '$createdAt ?? (new UtcClock())->now()', '(new UtcClock())->now()', 'tests/Command/OperationTimeTest.php', 'preserves an explicitly supplied operation timestamp and minimal UUID by identity'],
    ['explicit operation identity', 'src/Command/Operation.php', 'if ($id === null)', 'if (true)', 'tests/Command/OperationTimeTest.php', 'preserves an explicitly supplied operation timestamp and minimal UUID by identity'],
    ['configured creation clock', 'src/Command/OperationFactory.php', 'createdAt: $this->clock->now(),', 'createdAt: (new UtcClock())->now(),', 'tests/Command/OperationTimeTest.php', 'reads the clock and generator once per creation and preserves the returned objects'],
    ['configured UUID generator', 'src/Command/OperationFactory.php', 'id: $this->uuidFactory->generate(),', 'id: (new UuidFactory())->generate(),', 'tests/Command/OperationTimeTest.php', 'reads the clock and generator once per creation and preserves the returned objects'],
    ['single-assignment result', 'src/Command/Operation.php', 'if ($this->result !== null)', 'if (false)', 'tests/CompletedOperationGuardTest.php', 'keeps the built-in operation result single-assignment contract'],
    ['first successful propagation stop', 'src/Command/Event/CommandEvent.php', '$this->propagationStoppedAt ??= $stoppedAt;', '$this->propagationStoppedAt = $stoppedAt;', 'tests/Command/LifecycleClockTest.php', 'preserves the first successful stop when a user clock suspends in a fiber'],
    ['idempotent stop', 'src/Command/Event/CommandEvent.php', 'if ($this->propagationStoppedAt === null)', 'if (true)', 'tests/Command/LifecycleClockTest.php', 'records lifecycle instants at each stage with the configured clock including first stop'],
    ['stopped listener resolution', 'src/Command/Middleware/EventMiddleware.php', 'if ($event->isPropagationStopped)', 'if (false)', 'tests/AuditContractRegressionTest.php', 'does not resolve later listeners after propagation of the current event is stopped'],
    ['operation attribute snapshot', 'src/Command/Operation.php', '$this->attributes = $snapshot;', '$this->attributes = $attributes;', 'tests/AttributeSnapshotTest.php', 'isolates operation attributes from caller references and returned array copies'],
    ['query context snapshot', 'src/Query/Context/Context.php', '$this->attributes = $snapshot;', '$this->attributes = $attributes;', 'tests/AttributeSnapshotTest.php', 'isolates context attributes from caller references and returned array copies'],
    ['registration conflict', 'src/Internal/RegistrationNormalizer.php', 'if (isset($handlers[$message]) && $handlers[$message] !== $handler)', 'if (false)', 'tests/AuditContractRegressionTest.php', 'rejects conflicting methods on the same registered handler service'],
    ['metadata cache-hit behavior', 'src/Command/Metadata/ReflectionCommandMetadataProvider.php', 'return $cached?->newInstance();', 'return null;', 'tests/Command/Metadata/CommandMetadataProviderTest.php', 'keeps constructor failures observable even after successful metadata lookup'],
    ['configured completion clock', 'src/Command/Middleware/HandleCommandHandler.php', 'processedAt: $this->clock->now()', 'processedAt: (new UtcClock())->now()', 'tests/Command/LifecycleClockTest.php', 'records lifecycle instants at each stage with the configured clock including first stop'],
    ['batch prevalidation', 'src/Command/BatchCommandBus.php', 'self::assertAttributes($commandClass, $commandAttributes);', '// Skip prevalidation.', 'tests/Command/BatchCommandBusTest.php', 'rejects malformed selected attributes before dispatching any command'],
    ['query middleware order', 'src/Query/QueryBus.php', 'self::compile($handler, array_values($middlewares))', 'self::compile($handler, array_reverse(array_values($middlewares)))', 'tests/Query/QueryBusTest.php', 'chains middlewares in registration order, onion-style'],
];

try {
    $replacement = $workspace . '/mutant.php';
    $report = $workspace . '/junit.xml';

    foreach ($controls as [$label, $file, $from, $to, $testFile, $filter]) {
        $source = $root . '/' . $file;
        $original = file_get_contents($source);
        if (!is_string($original) || substr_count($original, $from) !== 1) {
            throw new RuntimeException('Negative control needs review: ' . $label);
        }
        $mutant = str_replace($from, $to, $original);
        if (file_put_contents($replacement, $mutant) !== strlen($mutant)) {
            throw new RuntimeException('Cannot write isolated mutant.');
        }

        $red = runControl($root, $source, $replacement, $report, $testFile, $filter);
        if ($red['status'] !== 1 || $red['tests'] < 1 || $red['failures'] < 1
            || $red['errors'] !== 0 || $red['skipped'] !== 0) {
            throw new RuntimeException('Expected assertion failure for ' . $label . ': ' . json_encode($red));
        }

        $green = runControl($root, $source, $source, $report, $testFile, $filter);
        if ($green['status'] !== 0 || $green['tests'] !== $red['tests']
            || $green['failures'] !== 0 || $green['errors'] !== 0 || $green['skipped'] !== 0) {
            throw new RuntimeException('Expected original passing test for ' . $label . ': ' . json_encode($green));
        }

        echo 'RED/GREEN: ', $label, ' (', $red['failures'], ' assertion failure(s); ',
            $green['tests'], " original passing test(s))\n";
    }

    echo count($controls), " targeted negative controls passed.\n";
} finally {
    foreach (glob($workspace . '/*') as $path) {
        unlink($path);
    }
    rmdir($workspace);
}
