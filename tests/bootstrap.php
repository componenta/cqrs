<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

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
