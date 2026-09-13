<?php

declare(strict_types=1);

namespace Componenta\CQRS;

class ConfigKey extends \Componenta\Config\ConfigKey
{
    final public const string COMMAND_MIDDLEWARES = 'Componenta\CQRS\Command::middlewares';
    final public const string QUERY_MIDDLEWARES = 'Componenta\CQRS\Query::middlewares';
    final public const string COMMAND_HANDLERS = 'cqrs.command_handlers';
    final public const string QUERY_HANDLERS = 'cqrs.query_handlers';
    final public const string COMMAND_LISTENERS = 'cqrs.command_listeners';
    final public const string MAPS = 'cqrs.maps';
}
