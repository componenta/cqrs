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

it('uses the configured DI invoker to execute located callables and inject additional arguments', function (string $representation, string $expected): void {
    $services = [
        CqrsLocatedTargetDependency::class => new CqrsLocatedTargetDependency('injected'),
    ];
    $target = match ($representation) {
        'function name' => 'cqrsLocatedTargetFunction',
        'static method name' => CqrsLocatedStaticTarget::class . '::handle',
        'function closure' => cqrsLocatedTargetFunction(...),
        'static method array' => [CqrsLocatedStaticTarget::class, 'handle'],
    };

    // A locator that requires native target identity can supply an unambiguous
    // callable. String specification precedence belongs to the DI invoker.
    if ($representation === 'function closure') {
        $services['cqrsLocatedTargetFunction'] = static fn (object $message): string => 'shadow-service';
    }
    if ($representation === 'static method array') {
        $services[CqrsLocatedStaticTarget::class . '::handle'] = static fn (object $message): string => 'shadow-service';
    }

    $container = (new ContainerFactory())->create(
        new Config([], new Environment([])),
        new DependencyDefinitions([ConfigKey::SERVICES => $services]),
    )->container;

    $invoker = $container->get(CallableInvokerInterface::class);
    $locator = new readonly class ($target) implements CommandHandlerLocatorInterface, QueryHandlerLocatorInterface {
        public function __construct(private mixed $target)
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
    'function name' => ['function name', 'function:injected'],
    'static method name' => ['static method name', 'static:injected'],
    'function closure with conflicting service ID' => ['function closure', 'function:injected'],
    'static method array with conflicting service ID' => ['static method array', 'static:injected'],
]);
