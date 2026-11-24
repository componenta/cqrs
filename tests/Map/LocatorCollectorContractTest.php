<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Locator\CommandSupportAwareInterface;
use Componenta\CQRS\Command\Locator\LocatorCollector as CommandLocatorCollector;
use Componenta\CQRS\Query\Locator\LocatorCollector as QueryLocatorCollector;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\CQRS\Query\Locator\QuerySupportAwareInterface;

/**
 * Contract probe for a replaceable locator, independent of the built-in maps.
 */
final class CollectorContractLocator implements
    CommandHandlerLocatorInterface,
    CommandSupportAwareInterface,
    QueryHandlerLocatorInterface,
    QuerySupportAwareInterface
{
    public array $located = [];

    public function __construct(
        private readonly string $label,
        private readonly bool $supported,
        private readonly ?Throwable $failure = null,
    ) {}

    public function supports(object $message): bool
    {
        return $this->supported;
    }

    public function locateFor(object $message): callable
    {
        $this->located[] = $message;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return fn (object $message): string => $this->label;
    }
}

it('delegates to the first supporting locator without resolving any other handler', function (string $collectorClass): void {
    $fallback = new CollectorContractLocator('fallback', true);
    $unsupported = new CollectorContractLocator('unsupported', false);
    $first = new CollectorContractLocator('first', true);
    $later = new CollectorContractLocator('later', true);
    $message = new stdClass();
    $collector = new $collectorClass($fallback, $unsupported, $first, $later);

    $handler = $collector->locateFor($message);

    expect($handler($message))->toBe('first')
        ->and($first->located)->toBe([$message])
        ->and($unsupported->located)->toBe([])
        ->and($later->located)->toBe([])
        ->and($fallback->located)->toBe([]);
})->with([CommandLocatorCollector::class, QueryLocatorCollector::class]);

it('uses the configured fallback when no delegate supports the message', function (string $collectorClass): void {
    $fallback = new CollectorContractLocator('fallback', true);
    $unsupported = new CollectorContractLocator('unsupported', false);
    $message = new stdClass();
    $collector = new $collectorClass($fallback, $unsupported);

    $handler = $collector->locateFor($message);

    expect($handler($message))->toBe('fallback')
        ->and($fallback->located)->toBe([$message])
        ->and($unsupported->located)->toBe([]);
})->with([CommandLocatorCollector::class, QueryLocatorCollector::class]);

it('propagates failure from the selected locator without silently choosing another handler', function (string $collectorClass): void {
    $failure = new RuntimeException('selected locator failed');
    $fallback = new CollectorContractLocator('fallback', true);
    $selected = new CollectorContractLocator('selected', true, $failure);
    $later = new CollectorContractLocator('later', true);
    $message = new stdClass();
    $collector = new $collectorClass($fallback, $selected, $later);

    try {
        $collector->locateFor($message);
        test()->fail('Expected the selected locator failure.');
    } catch (RuntimeException $exception) {
        expect($exception)->toBe($failure)
            ->and($selected->located)->toBe([$message])
            ->and($later->located)->toBe([])
            ->and($fallback->located)->toBe([]);
    }
})->with([CommandLocatorCollector::class, QueryLocatorCollector::class]);
