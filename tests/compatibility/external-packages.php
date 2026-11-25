<?php

declare(strict_types=1);

/**
 * Prospective CQRS 5 integration acceptance. Never change the manifests in
 * the checked-out core or publish a dependency override. The scratch sources
 * use development versions, with explicit temporary CQRS/DI constraints.
 */
$root = dirname(__DIR__, 2);
$package = $argv[1] ?? '';
$workspace = $argv[2] ?? '';
$pins = json_decode(file_get_contents(__DIR__ . '/external-packages.json'), true, flags: JSON_THROW_ON_ERROR);
$extras = [
    'cqrs-app' => ['app', 'app-console'],
    'cqrs-policy' => ['policy', 'cqrs-transport'],
    'cqrs-retry' => [],
    'cqrs-lock' => [],
    'cqrs-transaction-cycle' => [],
    'cqrs-transport' => [],
    'cqrs-transport-console' => ['app', 'app-console', 'cqrs-transport'],
    'cqrs-transport-cycle' => ['cqrs-transport'],
];
$versions = [
    'cqrs' => '5.0.x-dev',
    'di' => '6.0.x-dev',
    'app' => '5.0.x-dev',
    'app-console' => '5.0.x-dev',
    'policy' => '3.0.x-dev',
    'cqrs-transport' => '5.0.x-dev',
];

if ((!array_key_exists($package, $extras) && $package !== 'cqrs-app-integration') || $workspace === '' || !is_dir($workspace)) {
    throw new InvalidArgumentException('Select one supported package and an existing scratch directory.');
}
$workspace = realpath($workspace);
if ($workspace === false || scandir($workspace) !== ['.', '..']) {
    throw new InvalidArgumentException('The scratch directory must be empty.');
}

function command(array $arguments, string $directory, bool $allowFailure = false): int
{
    echo '$ ', implode(' ', array_map('escapeshellarg', $arguments)), PHP_EOL;
    $process = proc_open($arguments, [STDIN, STDOUT, STDERR], $pipes, $directory);
    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start ' . $arguments[0]);
    }
    $exit = proc_close($process);
    if ($exit !== 0 && !$allowFailure) {
        throw new RuntimeException('Command failed: ' . $arguments[0] . ' (exit ' . $exit . ')');
    }
    return $exit;
}

function checkout(string $package, string $ref, string $directory): void
{
    command(['git', 'init', '-q', $directory], dirname($directory));
    command(['git', 'remote', 'add', 'origin', 'https://github.com/componenta/' . $package . '.git'], $directory);
    command(['git', 'fetch', '--depth=1', 'origin', $ref], $directory);
    command(['git', 'checkout', '-q', '--detach', 'FETCH_HEAD'], $directory);
    command(['git', 'rev-parse', 'HEAD'], $directory);
}

function manifest(string $directory): array
{
    return json_decode(file_get_contents($directory . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
}

function writeManifest(string $directory, array $manifest): void
{
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($directory . '/composer.json', $json) !== strlen($json)) {
        throw new RuntimeException('Cannot write scratch manifest.');
    }
}

function migrateRequirements(array $manifest): array
{
    foreach (['require', 'require-dev'] as $section) {
        foreach (['componenta/cqrs' => '^5.0', 'componenta/di' => '^6.0'] as $dependency => $constraint) {
            if (isset($manifest[$section][$dependency])) {
                echo $manifest['name'], ': ', $dependency, ' ',
                    $manifest[$section][$dependency], ' -> ', $constraint, ' (scratch only)', PHP_EOL;
                $manifest[$section][$dependency] = $constraint;
            }
        }
    }
    return $manifest;
}

/**
 * Reuse the maintained cross-package application suite, including its
 * LocalPackagesTest. Every explicitly required Componenta package is checked
 * out next to cqrs-app as required by the suite's public fixture contract.
 */
function runApplicationIntegration(string $root, string $workspace, array $pins, array $versions): void
{
    $app = $workspace . '/cqrs-app';
    checkout('cqrs-app', $pins['cqrs-app'], $app);
    $target = $app . '/integration';
    $manifest = manifest($target);
    $releaseVersions = $manifest['repositories'][0]['options']['versions'];
    $repositories = [];

    foreach ($manifest['require'] as $name => $constraint) {
        if (!str_starts_with($name, 'componenta/')) {
            continue;
        }
        $dependency = substr($name, strlen('componenta/'));
        $directory = $workspace . '/' . $dependency;

        if ($dependency === 'cqrs') {
            if (!symlink($root, $directory)) {
                throw new RuntimeException('Cannot link the candidate CQRS checkout.');
            }
            command(['git', 'rev-parse', 'HEAD'], $root);
            $version = $versions[$dependency];
        } else {
            if ($dependency !== 'cqrs-app') {
                checkout($dependency, $pins[$dependency] ?? 'v' . $releaseVersions[$name], $directory);
            }

            if (isset($pins[$dependency])) {
                if ($dependency !== 'di') {
                    writeManifest($directory, migrateRequirements(manifest($directory)));
                }
                // All modified/prospective packages have explicit dev versions.
                $version = $versions[$dependency] ?? preg_replace('/\.[0-9]+$/', '.x-dev', $releaseVersions[$name]);
            } else {
                $version = $releaseVersions[$name];
            }
        }

        $repositories[] = [
            'type' => 'path',
            'url' => $directory,
            'options' => ['symlink' => true, 'versions' => [$name => $version]],
        ];
        $manifest['require'][$name] = $version;
    }

    $manifest['repositories'] = $repositories;
    writeManifest($target, $manifest);
    command(['composer', 'update', '--with-all-dependencies', '--no-interaction', '--prefer-dist', '--no-progress', '--no-scripts'], $target);
    command(['composer', 'test'], $target);
    command(['composer', 'audit', '--locked', '--no-dev', '--no-interaction'], $target);
    echo "Cross-package application integration passed.\n";
}

if ($package === 'cqrs-app-integration') {
    runApplicationIntegration($root, $workspace, $pins, $versions);
    exit(0);
}

$target = $workspace . '/package';
checkout($package, $pins[$package], $target);
command(['composer', 'validate', '--strict'], $target);

$sources = $workspace . '/sources';
if (!mkdir($sources)) {
    throw new RuntimeException('Cannot create isolated sources directory.');
}
$repositories = [];
$selected = ['cqrs', 'di', ...$extras[$package]];

foreach ($selected as $dependency) {
    if ($dependency === 'cqrs') {
        $directory = $root;
        command(['git', 'rev-parse', 'HEAD'], $root);
    } else {
        $directory = $sources . '/' . $dependency;
        checkout($dependency, $pins[$dependency], $directory);
        // App, policy and transport have not yet declared CQRS 5 / DI 6
        // compatibility. Exercise their code in a provisional graph, not a
        // falsely labelled stable package or a released Composer manifest.
        if ($dependency !== 'di') {
            writeManifest($directory, migrateRequirements(manifest($directory)));
        }
    }
    $name = 'componenta/' . $dependency;
    $repositories[] = [
        'type' => 'path',
        'url' => $directory,
        'options' => ['symlink' => false, 'versions' => [$name => $versions[$dependency]]],
    ];
}

$manifest = migrateRequirements(manifest($target));
$manifest['repositories'] = $repositories;
foreach ($selected as $dependency) {
    $name = 'componenta/' . $dependency;
    $section = isset($manifest['require'][$name]) ? 'require' : 'require-dev';
    $manifest[$section][$name] = $versions[$dependency];
}
writeManifest($target, $manifest);

command(['composer', 'update', '--with-all-dependencies', '--no-interaction', '--prefer-dist', '--no-progress'], $target);
$lock = json_decode(file_get_contents($target . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$locked = array_column(array_merge($lock['packages'], $lock['packages-dev']), 'version', 'name');
foreach ($selected as $dependency) {
    if (($locked['componenta/' . $dependency] ?? null) !== $versions[$dependency]) {
        throw new RuntimeException('Unexpected candidate version of ' . $dependency);
    }
}
echo "Prospective graph installed; exact candidate development versions verified.\n";

$failed = [];
foreach ([
    ['composer', 'test'],
    ['composer', 'analyse'],
    ['composer', 'audit', '--locked', '--no-dev', '--no-interaction'],
] as $check) {
    if (command($check, $target, true) !== 0) {
        $failed[] = implode(' ', $check);
    }
}
if ($failed !== []) {
    throw new RuntimeException('Failed checks: ' . implode(', ', $failed));
}
require __DIR__ . '/external-negative-controls.php';
verifyExternalNegativeControls($package, $target);
echo "External package acceptance passed: ", $package, PHP_EOL;
