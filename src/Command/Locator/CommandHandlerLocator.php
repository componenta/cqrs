<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Command\Exception\HandlerNotFoundException;
use Componenta\CQRS\Command\Exception\InvalidHandlerException;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Psr\Container\ContainerInterface;

final class CommandHandlerLocator implements CommandHandlerLocatorInterface, CommandSupportAwareInterface
{
    use CommandNameResolution;

    public function __construct(
        private readonly CqrsMapProviderInterface $mapProvider,
        private readonly ContainerInterface $container,
        ?CommandNameResolverInterface $resolver = null,
    ) {
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
        $descriptor = $this->mapProvider->map()->commandHandler($commandName);

        if ($descriptor === null) {
            throw new HandlerNotFoundException($commandName);
        }

        $handler = $this->container->get($descriptor->service);

        if ($descriptor->method === '__invoke') {
            if (!is_callable($handler)) {
                throw InvalidHandlerException::serviceNotInvokable(
                    $descriptor->service,
                    $commandName,
                );
            }

            return $handler;
        }

        if (!is_callable([$handler, $descriptor->method])) {
            throw InvalidHandlerException::serviceMethodNotCallable(
                $descriptor->service,
                $descriptor->method,
            );
        }

        return $handler->{$descriptor->method}(...);
    }

    public function supports(object $command): bool
    {
        return $this->mapProvider->map()->commandHandler(
            $this->resolveCommandName($command),
        ) !== null;
    }
}
