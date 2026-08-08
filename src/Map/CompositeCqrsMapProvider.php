<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map;

final class CompositeCqrsMapProvider implements CqrsMapProviderInterface
{
    private ?CqrsMap $map = null;

    /** @var list<CqrsMapProviderInterface> */
    private readonly array $overlays;

    public function __construct(
        private readonly CqrsMapProviderInterface $base,
        CqrsMapProviderInterface ...$overlays,
    ) {
        $this->overlays = array_values($overlays);
    }

    public function map(): CqrsMap
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $map = $this->base->map();

        foreach ($this->overlays as $overlay) {
            $map = $map->merge($overlay->map());
        }

        return $this->map = $map;
    }
}
