<?php

namespace Componenta\CQRS\Command;

interface NamedCommandInterface
{
    public string $commandName { get; }
}