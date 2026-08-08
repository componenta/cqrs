<?php

declare(strict_types=1);

namespace Componenta\CQRS;

class ConfigKey extends \Componenta\Config\ConfigKey
{
    public final const string COMMAND_MIDDLEWARES = 'Componenta\CQRS\Command::middlewares';
    public final const string QUERY_MIDDLEWARES = 'Componenta\CQRS\Query::middlewares';

    /** Versioned CQRS runtime artifact. */
    public final const string CQRS_MAP = 'Componenta\CQRS::Map';

    /** @var string List of command metadata attribute class names discovered by cqrs-app. */
    public final const string COMMAND_METADATA_ATTRIBUTES
        = 'Componenta\CQRS\Command::MetadataAttributes';

    /**
     * @return list<string>
     */
    public static function legacyMapKeys(): array
    {
        return [
            'Componenta\\CQRS\\Query::HandlerMap',
            'Componenta\\CQRS\\Command::HandlerMap',
            'Componenta\\CQRS\\Command::ListenerMap',
            'Componenta\\CQRS\\Command::AttributeMap',
            'Componenta\\CQRS::CompiledMaps',
        ];
    }
}
