<?php

declare(strict_types=1);

/** Configure a temporary CI/consumer root, never a dependency's manifest. */
$target = realpath($argv[1] ?? '');
$checkouts = realpath($argv[2] ?? '');
if ($target === false || $checkouts === false) {
    throw new InvalidArgumentException('Pass an existing Composer root and dependency checkout directory.');
}

$manifestFile = $target . '/composer.json';
$manifest = json_decode(file_get_contents($manifestFile), true, flags: JSON_THROW_ON_ERROR);
$branch = 'dev-fix/callback-dependency-baseline-2026-11-26';
foreach (['componenta/di' => 'di6', 'componenta/identity' => 'identity'] as $package => $directory) {
    $source = $checkouts . '/' . $directory;
    $dependency = json_decode(file_get_contents($source . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    if ($dependency['name'] !== $package || !isset($dependency['extra']['branch-alias'][$branch])) {
        throw new RuntimeException('The checkout must declare its actual development branch alias: ' . $package);
    }
    $manifest['repositories'][$directory] = [
        'type' => 'path',
        'url' => $source,
        'options' => ['symlink' => false],
    ];
    $manifest['require'][$package] = $branch;
}
$manifest['minimum-stability'] = 'stable';
$manifest['prefer-stable'] = true;
file_put_contents(
    $manifestFile,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
);
