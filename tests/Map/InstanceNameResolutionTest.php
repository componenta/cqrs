<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\NamedCommandInterface;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\Map\CqrsMap;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Componenta\CQRS\Map\HandlerDescriptor;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Componenta\CQRS\Query\NamedQueryInterface;
use Psr\Container\ContainerInterface;

final readonly class InstanceNamedCommand implements NamedCommandInterface
{
    public function __construct(public string $commandName)
    {
    }
}

final readonly class InstanceNamedQuery implements NamedQueryInterface
{
    public function __construct(public string $queryName)
    {
    }
}

final readonly class InstanceNameHandler
{
    public function __construct(private string $result)
    {
    }

    public function __invoke(object $message): string
    {
        return $this->result;
    }
}

final readonly class InstanceNameMapProvider implements CqrsMapProviderInterface
{
    public function __construct(private CqrsMap $map)
    {
    }

    public function map(): CqrsMap
    {
        return $this->map;
    }
}

final readonly class InstanceNameContainer implements ContainerInterface
{
    /** @param array<string, object> $entries */
    public function __construct(private array $entries)
    {
    }

    public function get(string $id): mixed
    {
        return $this->entries[$id] ?? throw new RuntimeException($id);
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }
}

it('resolves command names per instance instead of poisoning the class cache', function (): void {
    $map = new CqrsMap(commandHandlers: [
        'command.a' => new HandlerDescriptor('handler.a', '__invoke'),
        'command.b' => new HandlerDescriptor('handler.b', '__invoke'),
    ]);
    $locator = new CommandHandlerLocator(
        new InstanceNameMapProvider($map),
        new InstanceNameContainer([
            'handler.a' => new InstanceNameHandler('a'),
            'handler.b' => new InstanceNameHandler('b'),
        ]),
    );
    $first = new InstanceNamedCommand('command.a');
    $second = new InstanceNamedCommand('command.b');

    expect(($locator->locateFor($first))($first))->toBe('a')
        ->and(($locator->locateFor($second))($second))->toBe('b');
});

it('resolves query names per instance instead of poisoning the class cache', function (): void {
    $map = new CqrsMap(queryHandlers: [
        'query.a' => new HandlerDescriptor('handler.a', '__invoke'),
        'query.b' => new HandlerDescriptor('handler.b', '__invoke'),
    ]);
    $locator = new QueryHandlerLocator(
        new InstanceNameMapProvider($map),
        new InstanceNameContainer([
            'handler.a' => new InstanceNameHandler('a'),
            'handler.b' => new InstanceNameHandler('b'),
        ]),
    );
    $first = new InstanceNamedQuery('query.a');
    $second = new InstanceNamedQuery('query.b');

    expect(($locator->locateFor($first))($first))->toBe('a')
        ->and(($locator->locateFor($second))($second))->toBe('b');
});

it('uses the named-command fallback when a custom resolver does not support the instance', function (): void {
    $map = new CqrsMap(commandHandlers: [
        'command.a' => new HandlerDescriptor('handler.a', '__invoke'),
    ]);
    $resolver = new class implements CommandNameResolverInterface {
        public function supports(object $command): bool
        {
            return false;
        }

        public function resolve(object $command): string
        {
            throw new RuntimeException('unsupported resolver was called');
        }
    };
    $locator = new CommandHandlerLocator(
        new InstanceNameMapProvider($map),
        new InstanceNameContainer(['handler.a' => new InstanceNameHandler('a')]),
        $resolver,
    );
    $command = new InstanceNamedCommand('command.a');

    expect(($locator->locateFor($command))($command))->toBe('a');
});
