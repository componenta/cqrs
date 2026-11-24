<?php

declare(strict_types=1);

namespace Componenta\CQRS\Benchmarks;

use Componenta\Config\Config;
use Componenta\Config\ContainerValue;
use Componenta\Config\DependencyDefinitions;
use Componenta\Config\Environment;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Factory\CqrsMapsFactory;
use Componenta\DI\ContainerFactory;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('prepare')]
#[Bench\ParamProviders('provideRegistrations')]
#[Bench\Iterations(5)]
#[Bench\Revs(100)]
#[Bench\Warmup(5)]
final class RegistrationBench
{
    private ContainerValue $container;
    private CqrsMapsFactory $factory;

    public function provideRegistrations(): iterable
    {
        yield 'small' => ['size' => 32, 'denseListeners' => false];
        yield 'distributed' => ['size' => 512, 'denseListeners' => false];
        yield 'dense-listeners' => ['size' => 512, 'denseListeners' => true];
    }

    public function prepare(array $params): void
    {
        $commands = [];
        $queries = [];
        $listeners = [];
        for ($i = 0; $i < $params['size']; ++$i) {
            $commands[] = ['message' => 'command.' . $i, 'service' => 'handler.' . $i, 'method' => '__invoke'];
            $queries[] = ['message' => 'query.' . $i, 'service' => 'handler.' . $i, 'method' => 'handle'];
            $listeners[] = [
                'message' => $params['denseListeners'] ? 'command.0' : 'command.' . $i,
                'service' => 'listener.' . $i,
                'events' => [CommandProcessedEvent::class, CommandProcessEvent::class],
                'priority' => $i % 5,
            ];
        }
        $config = new Config([
            ConfigKey::COMMAND_HANDLERS => $commands,
            ConfigKey::QUERY_HANDLERS => $queries,
            ConfigKey::COMMAND_LISTENERS => $listeners,
        ], new Environment([]));
        $this->container = (new ContainerFactory())->create($config, new DependencyDefinitions([]));
        $this->factory = new CqrsMapsFactory();
        $maps = ($this->factory)($this->container);
        if (count($maps['command_handlers']) !== $params['size']
            || count($maps['query_handlers']) !== $params['size']
            || array_sum(array_map('count', $maps['command_listeners'])) !== $params['size']) {
            throw new \RuntimeException('Invalid registration benchmark fixture.');
        }
    }

    public function benchBuildRegistrationMaps(array $params): void
    {
        ($this->factory)($this->container);
    }
}
