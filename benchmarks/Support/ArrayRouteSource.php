<?php

declare(strict_types = 1);

namespace Benchmarks\Support;

use SineMacula\RouteLinter\Contracts\RouteSource;

/**
 * In-memory route source that returns a fixed descriptor list.
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
    public function __construct(

        /** The fixed list of app-owned route descriptors */
        private array $descriptors,
    ) {}

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
