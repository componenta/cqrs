<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Command\Exception\HandlerNotFoundException;
use Componenta\CQRS\Command\Exception\InvalidHandlerException;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Psr\Container\ContainerInterface;
use Componenta\CQRS\Internal\RegistrationNormalizer;

/**
 * @phpstan-import-type Handler from RegistrationNormalizer
 * @phpstan-import-type Listener from RegistrationNormalizer
 */
final class CommandHandlerLocator implements CommandHandlerLocatorInterface, CommandSupportAwareInterface
{
    use CommandNameResolution;

    /** @var array<array-key, Handler> */
    private readonly array $handlers;

    /** @param array<array-key, mixed> $handlers */
    public function __construct(
        array $handlers,
        private readonly ContainerInterface $container,
        ?CommandNameResolverInterface $resolver = null,
    ) {
        $this->handlers = RegistrationNormalizer::handlers($handlers, 'command_handlers');
        $this->resolver = $resolver;
    }

    /**
     * @template T of object
     * @param T $command
     * @return callable(T): mixed
     *
     * @throws HandlerNotFoundException
     * @throws InvalidHandlerException
     */
    public function locateFor(object $command): callable
    {
        $commandName = $this->resolveCommandName($command);
        $descriptor = ($this->handlers[$commandName] ?? null);

        if ($descriptor === null) {
            throw new HandlerNotFoundException($commandName);
        }

        $handler = $this->container->get($descriptor['service']);

        if ($descriptor['method'] === '__invoke') {
            if (!is_callable($handler)) {
                throw InvalidHandlerException::serviceNotInvokable(
                    $descriptor['service'],
                    $commandName,
                );
            }

            return $handler;
        }

        $callable = [$handler, $descriptor['method']];

        if (!is_callable($callable)) {
            throw InvalidHandlerException::serviceMethodNotCallable(
                $descriptor['service'],
                $descriptor['method'],
            );
        }

        return $callable;
    }

    public function supports(object $command): bool
    {
        return isset($this->handlers[
            $this->resolveCommandName($command)
        ]);
    }
}
