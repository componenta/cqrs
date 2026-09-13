<?php

declare(strict_types=1);

$package = dirname(__DIR__);
$packages = dirname($package);
$workspace = dirname($packages);
$local = $package . '/vendor/autoload.php';
$loader = require is_file($local) ? $local : $packages . '/var-export/vendor/autoload.php';
if (!is_file($local)) {
    foreach (require $workspace . '/vendor/composer/autoload_psr4.php' as $prefix => $paths) {
        $loader->addPsr4($prefix, $paths);
    }
    $loader->setPsr4('Componenta\\Config\\', $packages . '/config/src');
    $loader->setPsr4('Componenta\\DI\\', $packages . '/di/src');
    require_once $packages . '/config/src/functions.php';
    require_once $packages . '/di/src/Internal/functions.php';
}
$loader->addPsr4('Componenta\\Stdlib\\', $packages . '/iterator/src', true);
$loader->setPsr4('Componenta\\CQRS\\', $package . '/src');
$loader->setPsr4('Componenta\\CQRS\\Tests\\', __DIR__);

if (defined('COMPONENTA_CQRS_MUTATION_ARGUMENTS')) {
    $pestContainer = \Pest\Support\Container::getInstance();
    $mutationRunner = $pestContainer->get(\Pest\Mutate\Contracts\MutationTestRunner::class);
    if ($mutationRunner->isEnabled()) {
        $configuration = $pestContainer->get(\Pest\Mutate\Repositories\ConfigurationRepository::class);
        $arguments = $configuration->cliConfiguration->fromArguments(COMPONENTA_CQRS_MUTATION_ARGUMENTS);
        array_shift($arguments);
        $mutationRunner->setOriginalArguments([
            PHP_BINARY, '-d', 'opcache.enable_cli=0', __DIR__ . '/mutate.php', ...$arguments,
        ]);
    }
}
