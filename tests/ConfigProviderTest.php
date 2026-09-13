<?php

declare(strict_types=1);

use Componenta\Config\ConfigFactory;
use Componenta\Config\ConfigProvider as BaseProvider;
use Componenta\Config\ContainerValue;
use Componenta\Config\Environment;
use Componenta\CQRS\Command\CommandBusInterface;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\NamedCommandInterface;
use Componenta\CQRS\ConfigProvider;
use Componenta\DI\ContainerFactory;

function registrationContainer(callable ...$providers): ContainerValue
{
    $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider(), ...$providers);
    return (new ContainerFactory())->create($composition->config, $composition->dependencies);
}

it('combines registrations from providers and dispatches through the existing container', function (): void {
    $seen = new ArrayObject();
    $provider = static fn (string $name): BaseProvider => new class ($name, $seen) extends BaseProvider {
        public function __construct(private string $name, private ArrayObject $seen)
        {
        }
        protected function getConfig(): array
        {
            return ['cqrs.command_handlers' => [
                ['message' => $this->name, 'service' => $this->name, 'method' => '__invoke'],
            ]];
        }
        protected function getServices(): array
        {
            return [$this->name => function (object $command): string {
                $this->seen[] = $this->name;
                return $this->name;
            }];
        }
    };
    $container = registrationContainer($provider('first'), $provider('second'));
    $bus = $container->get(CommandBusInterface::class);
    foreach (['first', 'second'] as $name) {
        $command = new readonly class ($name) implements NamedCommandInterface {
            public function __construct(public string $commandName)
            {
            }
        };
        expect($bus->dispatch($command)->result?->value)->toBe($name);
    }
    expect($seen->getArrayCopy())->toBe(['first', 'second']);
});

it('rejects conflicting handler registrations before resolving any handler', function (): void {
    $first = static fn (): array => ['cqrs.command_handlers' => [
        ['message' => 'message', 'service' => 'first', 'method' => '__invoke'],
    ]];
    $second = static fn (): array => ['cqrs.command_handlers' => [
        ['message' => 'message', 'service' => 'second', 'method' => '__invoke'],
    ]];
    $container = registrationContainer($first, $second);
    expect(fn () => $container->get(CommandHandlerLocatorInterface::class))
        ->toThrow(\Componenta\DI\Exception\ResolutionException::class, 'message');
});
