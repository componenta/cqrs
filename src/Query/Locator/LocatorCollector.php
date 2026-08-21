<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Locator;

final readonly class LocatorCollector implements QueryHandlerLocatorInterface
{
    /** @var list<QueryHandlerLocatorInterface&QuerySupportAwareInterface> */
    private array $locators;

    public function __construct(
        private QueryHandlerLocatorInterface $fallback,
        QueryHandlerLocatorInterface&QuerySupportAwareInterface ...$locators,
    ) {
        $this->locators = array_values($locators);
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
