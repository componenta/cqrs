<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\NamedCommandInterface;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Componenta\CQRS\Query\NamedQueryInterface;
use Componenta\CQRS\Query\Resolver\QueryNameResolverInterface;
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
    $map = [
        'command.a' => ['service' => 'handler.a', 'method' => '__invoke'],
        'command.b' => ['service' => 'handler.b', 'method' => '__invoke'],
    ];
    $locator = new CommandHandlerLocator(
        $map,
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
    $map = [
        'query.a' => ['service' => 'handler.a', 'method' => '__invoke'],
        'query.b' => ['service' => 'handler.b', 'method' => '__invoke'],
    ];
    $locator = new QueryHandlerLocator(
        $map,
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

it('uses default naming when the configured name resolver does not support the message', function (string $kind, bool $named): void {
    $message = match (true) {
        !$named => new stdClass(),
        $kind === 'command' => new InstanceNamedCommand('command.a'),
        default => new InstanceNamedQuery('query.a'),
    };
    $name = $named ? $kind . '.a' : $message::class;
    $resolver = new class implements CommandNameResolverInterface, QueryNameResolverInterface {
        public function supports(object $message): bool
        {
            return false;
        }

        public function resolve(object $message): string
        {
            throw new RuntimeException('unsupported resolver was called');
        }
    };
    $map = [$name => ['service' => 'handler.a', 'method' => '__invoke']];
    $container = new InstanceNameContainer(['handler.a' => new InstanceNameHandler('a')]);
    $locator = $kind === 'command'
        ? new CommandHandlerLocator($map, $container, $resolver)
        : new QueryHandlerLocator($map, $container, $resolver);

    expect($locator->supports($message))->toBeTrue()
        ->and(($locator->locateFor($message))($message))->toBe('a');
})->with([
    'named command' => ['command', true],
    'ordinary command' => ['command', false],
    'named query' => ['query', true],
    'ordinary query' => ['query', false],
]);

it('does not fall back to default naming when a supporting resolver fails', function (string $kind): void {
    $failure = new RuntimeException('name resolution failed');
    $resolver = new readonly class ($failure) implements CommandNameResolverInterface, QueryNameResolverInterface {
        public function __construct(private Throwable $failure) {}

        public function supports(object $message): bool
        {
            return true;
        }

        public function resolve(object $message): string
        {
            throw $this->failure;
        }
    };
    $map = [stdClass::class => ['service' => 'handler.a', 'method' => '__invoke']];
    $container = new InstanceNameContainer(['handler.a' => new InstanceNameHandler('a')]);
    $locator = $kind === 'command'
        ? new CommandHandlerLocator($map, $container, $resolver)
        : new QueryHandlerLocator($map, $container, $resolver);

    foreach (['supports', 'locateFor'] as $method) {
        try {
            $locator->$method(new stdClass());
        } catch (Throwable $exception) {
            expect($exception)->toBe($failure);

            continue;
        }

        test()->fail('Expected the name resolution failure from ' . $method . '().');
    }
})->with(['command', 'query']);
