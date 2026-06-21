<?php

namespace Componenta\CQRS;

class ConfigKey extends \Componenta\Config\ConfigKey
{
    public final const string COMMAND_MIDDLEWARES = 'Componenta\CQRS\Command::middlewares';
    public final const string QUERY_MIDDLEWARES = 'Componenta\CQRS\Query::middlewares';

    public final const string QUERY_HANDLER_MAP = 'Componenta\CQRS\Query::HandlerMap';
    public final const string COMMAND_HANDLER_MAP = 'Componenta\CQRS\Command::HandlerMap';
    public final const string COMMAND_LISTENER_MAP = 'Componenta\CQRS\Command::ListenerMap';
    public final const string COMMAND_ATTRIBUTE_MAP = 'Componenta\CQRS\Command::AttributeMap';
    public final const string COMPILED_MAPS = 'Componenta\CQRS::CompiledMaps';
}
