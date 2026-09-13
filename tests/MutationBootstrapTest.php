<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('runs original mutation children successfully and detects substituted source', function (): void {
    $package = dirname(__DIR__);
    $source = $package . '/src/Command/Metadata/ReflectionCommandMetadataProvider.php';
    $replacement = tempnam(sys_get_temp_dir(), 'cqrs_mutation_');
    $marker = tempnam(sys_get_temp_dir(), 'cqrs_mutation_marker_');
    try {
        file_put_contents($replacement, "<?php file_put_contents(" . var_export($marker, true) . ", 'substituted'); throw new RuntimeException('CQRS_MUTATION_SENTINEL');");
        $arguments = [
            PHP_BINARY, '-d', 'opcache.enable_cli=0', __DIR__ . '/mutate.php',
            'tests/Command/Metadata/CommandMetadataProviderTest.php',
            '--filter="returns null when a command has no requested metadata"',
            '--colors=never', '--do-not-cache-result',
        ];
        $original = new Process($arguments, $package, [
            'PEST_MUTATION_TESTING' => $source,
            'PEST_MUTATION_FILE' => $source,
        ]);
        $original->run();
        expect($original->getExitCode())->toBe(0, $original->getOutput() . $original->getErrorOutput());
        expect(file_get_contents($marker))->toBe('');

        $mutated = new Process($arguments, $package, [
            'PEST_MUTATION_TESTING' => $source,
            'PEST_MUTATION_FILE' => $replacement,
        ]);
        $mutated->run();
        expect($mutated->getExitCode())->toBeGreaterThan(0);
        expect(file_get_contents($marker))->toBe('substituted');
    } finally {
        unlink($replacement);
        unlink($marker);
    }
});
