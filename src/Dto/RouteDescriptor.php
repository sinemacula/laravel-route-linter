<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Dto;

/**
 * A route as read from the router, before normalisation.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class RouteDescriptor
{
    /**
     * Create a new route descriptor.
     *
     * @param  string  $uri
     * @param  array<int, string>  $methods
     * @param  string|null  $name
     * @param  bool  $isVendor
     * @param  string|null  $handler
     * @param  array<int, string>  $middleware
     * @param  list<\SineMacula\RouteLinter\Dto\RouteSuppression>  $suppressions
     */
    public function __construct(

        /** The route URI as registered, e.g. `users/{user}` */
        public string $uri,

        /** The uppercase HTTP methods the route responds to, e.g. `['GET', 'HEAD']` */
        public array $methods,

        /** The route name, or null when the route is unnamed */
        public ?string $name,

        /** Whether the route belongs to a vendor package */
        public bool $isVendor,

        /** The handler as `Class@method` (or `Class` for invokables), or null for closure routes */
        public ?string $handler = null,

        /** Gathered middleware names, e.g. `['auth:sanctum']`; closure middleware is excluded */
        public array $middleware = [],

        /** Inline suppressions declared via `ignoreRouteLint` */
        public array $suppressions = [],
    ) {}
}
