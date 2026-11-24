<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\Config\ConfigKey;
use Componenta\Config\DependencyDefinitions;
use Componenta\Config\Environment;
use Componenta\CQRS\Command\CommandBus;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\CQRS\Query\QueryBus;
use Componenta\DI\CallableInvokerInterface;
use Componenta\DI\ContainerFactory;

final readonly class CqrsLocatedTargetDependency
{
    public function __construct(public string $value)
    {
    }
}

function cqrsLocatedTargetFunction(object $message, CqrsLocatedTargetDependency $dependency): string
{
    return 'function:' . $dependency->value;
}

final class CqrsLocatedStaticTarget
{
    public static function handle(object $message, CqrsLocatedTargetDependency $dependency): string
    {
        return 'static:' . $dependency->value;
    }
}

it('executes the located handler through real DI even when a service has its callable name', function (string $target, string $expected): void {
    $container = (new ContainerFactory())->create(
        new Config([], new Environment([])),
        new DependencyDefinitions([
            ConfigKey::SERVICES => [
                $target => static fn (object $message): string => 'shadow-service',
                CqrsLocatedTargetDependency::class => new CqrsLocatedTargetDependency('injected'),
            ],
        ]),
    )->container;

    $invoker = $container->get(CallableInvokerInterface::class);
    $locator = new readonly class ($target) implements CommandHandlerLocatorInterface, QueryHandlerLocatorInterface {
        public function __construct(private string $target)
        {
        }

        public function locateFor(object $message): callable
        {
            return $this->target;
        }
    };

    $queries = new QueryBus(new HandleQuery($locator, $invoker));
    $commands = new CommandBus(new HandleCommandHandler($locator, $invoker));

    expect($queries->handle(new stdClass()))->toBe($expected)
        ->and($commands->dispatch(new stdClass())->result?->value)->toBe($expected);
})->with([
    'named function' => ['cqrsLocatedTargetFunction', 'function:injected'],
    'static method' => [CqrsLocatedStaticTarget::class . '::handle', 'static:injected'],
]);
