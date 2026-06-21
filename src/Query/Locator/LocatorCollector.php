<?php

namespace Componenta\CQRS\Query\Locator;

final class LocatorCollector implements QueryHandlerLocatorInterface
{
    /** @var array<QueryHandlerLocatorInterface&QuerySupportAwareInterface> */
    private array $locators;

    public function __construct(
        private QueryHandlerLocatorInterface $fallback,
        QueryHandlerLocatorInterface&QuerySupportAwareInterface ...$locators
    ) {
        $this->locators = $locators;
    }

    public function locateFor(object $query): callable
    {
        foreach ($this->locators as $locator) {
            if ($locator->supports($query)) {
                return $locator->locateFor($query);
            }
        }

        return $this->fallback->locateFor($query);
    }
}
