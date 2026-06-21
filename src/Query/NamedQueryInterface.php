<?php

namespace Componenta\CQRS\Query;

interface NamedQueryInterface
{
    public string $queryName { get; }
}