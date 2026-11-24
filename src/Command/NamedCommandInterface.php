<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

/** Supplies an explicit logical command name instead of its PHP class name. */
interface NamedCommandInterface
{
    /**
     * Name used by the default resolver. It may vary between instances of a
     * class and is used without trimming or case normalization. A routable
     * name must match a valid registration (not empty or whitespace-only).
     */
    public string $commandName { get; }
}
