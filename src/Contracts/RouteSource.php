<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Contracts;

/**
 * Supplies the application's own routes, excluding vendor routes.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
interface RouteSource
{
    /**
     * Enumerate every app-owned route.
     *
     * @return array<int, \SineMacula\RouteLinter\Dto\RouteDescriptor>
     */
    public function appRoutes(): array;
}
