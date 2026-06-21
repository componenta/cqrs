<?php

declare(strict_types=1);

use Componenta\Config\ConfigKey as DependencyConfigKey;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\ConfigProvider;

it('registers empty bus middleware chains by default', function (): void {
    $config = (new ConfigProvider())();

    expect($config[ConfigKey::COMMAND_MIDDLEWARES])->toBe([])
        ->and($config[ConfigKey::QUERY_MIDDLEWARES])->toBe([])
        ->and($config[DependencyConfigKey::DEPENDENCIES][DependencyConfigKey::AUTOWIRES] ?? [])->toBe([]);
});
