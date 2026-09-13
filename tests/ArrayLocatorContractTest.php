<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\NamedCommandInterface;
use Psr\Container\ContainerInterface;

it('resolves each named command from arrays without owning the service lifetime', function (): void {
    $container = new class () implements ContainerInterface {
        public int $gets = 0;
        public function get(string $id): mixed
        {
            ++$this->gets;
            return static fn (object $command): string => $id;
        }
        public function has(string $id): bool
        {
            return true;
        }
    };
    $command = new class () implements NamedCommandInterface {
        public string $name = 'first';
        public string $commandName { get => $this->name; }
    };
    $locator = new CommandHandlerLocator([
        'first' => ['service' => 'handler.a', 'method' => '__invoke'],
        'second' => ['service' => 'handler.b', 'method' => '__invoke'],
    ], $container);

    expect($locator->supports($command))->toBeTrue()
        ->and($container->gets)->toBe(0)
        ->and(($locator->locateFor($command))($command))->toBe('handler.a');
    $command->name = 'second';
    expect(($locator->locateFor($command))($command))->toBe('handler.b')
        ->and($container->gets)->toBe(2);
});
