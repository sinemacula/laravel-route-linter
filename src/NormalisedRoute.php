<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter;

/**
 * A route as the rules inspect it.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class NormalisedRoute
{
    /**
     * Create a new normalised route.
     *
     * @param  string  $uri
     * @param  array<int, string>  $methods
     * @param  string|null  $name
     * @param  array<int, string>  $segments
     * @param  array<int, string>  $parameters
     * @param  string|null  $handler
     * @param  array<int, string>  $middleware
     */
    public function __construct(

        /** The route URI as registered, e.g. `users/{user}` */
        public string $uri,

        /** Uppercase HTTP methods for this route, e.g. `['GET', 'HEAD']` */
        public array $methods,

        /** The route name, or null when the route is unnamed */
        public ?string $name,

        /** The URI split on `/`, keeping empty segments so stray slashes stay visible */
        public array $segments,

        /** Route parameter names without braces, e.g. `['user']`, for custom rules */
        public array $parameters,

        /** The handler as `Class@method` (or `Class` for invokables), or null for closure routes */
        public ?string $handler = null,

        /** Gathered middleware names, e.g. `['auth:sanctum']`; closure middleware is excluded */
        public array $middleware = [],
    ) {}

    /**
     * Return the route's identity, e.g. `GET,HEAD users users.index`.
     *
     * @return string
     */
    public function identity(): string
    {
        $sortedMethods = $this->methods;
        sort($sortedMethods);

        $key = implode(',', $sortedMethods) . ' ' . $this->uri;

        if ($this->name !== null) {
            $key .= ' ' . $this->name;
        }

        return $key;
    }
}
