<?php

declare(strict_types=1);

/**
 * Run deliberate regressions on the isolated external-package checkout only.
 * A parse error, skipped test, missing report or runner crash is not a kill.
 */
function verifyExternalNegativeControls(string $package, string $target): void
{
    $controls = json_decode(file_get_contents(__DIR__ . '/external-negative-controls.json'), true, flags: JSON_THROW_ON_ERROR);
    $controls = $controls[$package] ?? throw new RuntimeException('No external controls for ' . $package);
    $report = tempnam(sys_get_temp_dir(), 'cqrs-external-junit-');
    if ($report === false) {
        throw new RuntimeException('Cannot create JUnit report.');
    }

    $run = static function (array $control) use ($target, $report): array {
        if (is_file($report)) {
            unlink($report);
        }
        $status = command([
            PHP_BINARY, '-d', 'opcache.enable_cli=0',
            $target . '/vendor/bin/pest',
            $control['test'],
            '--filter=' . $control['filter'],
            '--colors=never', '--do-not-cache-result',
            '--log-junit=' . $report,
        ], $target, true);

        if (!is_file($report)) {
            throw new RuntimeException('Negative control did not produce a JUnit report: ' . $control['label']);
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
    };

    try {
        foreach ($controls as $control) {
            $source = realpath($target . '/' . $control['source']);
            if ($source === false || !str_starts_with($source, $target . '/src/')) {
                throw new RuntimeException('Control source is outside the isolated package.');
            }
            $original = file_get_contents($source);
            if (!is_string($original)) {
                throw new RuntimeException('Cannot read control source.');
            }
            $mutant = $original;
            foreach ($control['edits'] as [$from, $to]) {
                if (substr_count($mutant, $from) !== 1) {
                    throw new RuntimeException('Control needs review: ' . $control['label']);
                }
                $mutant = str_replace($from, $to, $mutant);
            }
            try {
                if (file_put_contents($source, $mutant) !== strlen($mutant)) {
                    throw new RuntimeException('Cannot write isolated mutant.');
                }
                $red = $run($control);
            } finally {
                if (file_put_contents($source, $original) !== strlen($original)) {
                    throw new RuntimeException('Cannot restore isolated source.');
                }
            }

            if ($red['status'] !== 1 || $red['tests'] < 1 || $red['failures'] < 1
                || $red['errors'] !== 0 || $red['skipped'] !== 0) {
                throw new RuntimeException('Expected assertion failure: ' . $control['label'] . ' ' . json_encode($red));
            }
            $green = $run($control);
            if ($green['status'] !== 0 || $green['tests'] !== $red['tests']
                || $green['failures'] !== 0 || $green['errors'] !== 0 || $green['skipped'] !== 0) {
                throw new RuntimeException('Expected restored passing test: ' . $control['label'] . ' ' . json_encode($green));
            }
            echo 'RED/GREEN: ', $control['label'], ' (', $red['failures'], ' assertion failure(s); ',
                $green['tests'], " restored passing test(s))\n";
        }
        command(['git', 'diff', '--exit-code', '--', 'src'], $target);
        echo count($controls), " external targeted negative controls passed: ", $package, PHP_EOL;
    } finally {
        if (is_file($report)) {
            unlink($report);
        }
    }
}
