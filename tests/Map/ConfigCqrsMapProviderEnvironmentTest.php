<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\Config\Environment;
use Componenta\CQRS\Map\ConfigCqrsMapProvider;
use Componenta\CQRS\Map\Exception\MissingCqrsMapException;

it('allows an empty runtime map only in development mode', function (?string $environment): void {
    $config = new Config(
        [],
        $environment === null ? null : new Environment(['APP_ENV' => $environment]),
    );

    expect((new ConfigCqrsMapProvider($config))->map()->toArray())
        ->toBe(['version' => 2]);
})->with([
    'absent environment defaults to development' => [null],
    'development' => ['development'],
]);

it('requires the compiled map in every non-development environment', function (string $environment): void {
    $config = new Config([], new Environment(['APP_ENV' => $environment]));

    expect(fn () => (new ConfigCqrsMapProvider($config))->map())
        ->toThrow(MissingCqrsMapException::class, $environment);
})->with([
    'production' => ['production'],
    'staging' => ['staging'],
    'test' => ['test'],
]);
