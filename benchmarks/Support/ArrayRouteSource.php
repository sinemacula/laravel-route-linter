<?php

namespace Benchmarks\Support;

use SineMacula\RouteLinter\Contracts\RouteSource;

/**
 * In-memory route-source adapter for the benchmark harness.
 *
 * Returns a fixed, pre-built list of route descriptors so the end-to-end
 * LintRoutes benchmark can exercise the real use case without booting a Laravel
 * application or reflecting over a live router. The descriptor list is supplied
 * once at construction and handed back verbatim on every call.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class ArrayRouteSource implements RouteSource
{
    /**
     * Create a new array-backed route source.
     *
     * @param  array<int, \SineMacula\RouteLinter\Dto\RouteDescriptor>  $descriptors
     */
    public function __construct(private array $descriptors) {}

    /**
     * Return the fixed list of app-owned route descriptors.
     *
     * @return array<int, \SineMacula\RouteLinter\Dto\RouteDescriptor>
     */
    #[\Override]
    public function appRoutes(): array
    {
        return $this->descriptors;
    }
}
