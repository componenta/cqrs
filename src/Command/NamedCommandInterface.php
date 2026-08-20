<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

interface NamedCommandInterface
{
    public string $commandName { get; }
}
