<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Sources;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use SineMacula\RouteLinter\Contracts\RouteSource;
use SineMacula\RouteLinter\Dto\RouteDescriptor;
use SineMacula\RouteLinter\Dto\RouteSuppression;

/**
 * Route-source adapter backed by the Laravel router.
 *
 * Enumerates the live route table via `Router::getRoutes()` after a full
 * application boot - the same source that `route:list` consumes - and maps each
 * route to a `RouteDescriptor` DTO. Vendor routes (whose defining class or
 * closure resolves to a file under the `vendor/` directory) are excluded from
 * the returned set; the domain never sees a framework `Route` object.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class RouterRouteSource implements RouteSource
{
    /** @var string The path segment used to identify vendor-owned files. */
    private const string VENDOR_SEGMENT = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;

    /**
     * Create a new router-backed route source.
     *
     * @param  \Illuminate\Routing\Router  $router
     */
    public function __construct(

        /** Laravel router whose live route table is enumerated */
        private readonly Router $router,
    ) {}

    /**
     * Enumerate every app-owned route, excluding vendor routes.
     *
     * @return array<int, \SineMacula\RouteLinter\Dto\RouteDescriptor>
     */
    #[\Override]
    public function appRoutes(): array
    {
        $descriptors = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $descriptor = $this->toDescriptor($route);

            if ($descriptor->isVendor) {
                continue;
            }

            $descriptors[] = $descriptor;
        }

        return $descriptors;
    }

    /**
     * Map a single framework route to a descriptor DTO.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return \SineMacula\RouteLinter\Dto\RouteDescriptor
     */
    private function toDescriptor(Route $route): RouteDescriptor
    {
        return new RouteDescriptor(
            uri: $route->uri(),
            methods: $route->methods(),
            name: $route->getName(),
            isVendor: $this->isVendorRoute($route),
            handler: $this->resolveHandler($route),
            middleware: $this->resolveMiddleware($route),
            suppressions: $this->buildSuppressions($route),
        );
    }

    /**
     * Resolve the route handler as `Class@method` (or `Class` for invokables),
     * returning null for closure routes (which have no class to inspect).
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return string|null
     */
    private function resolveHandler(Route $route): ?string
    {
        if ($route->getAction('uses') instanceof \Closure) {
            return null;
        }

        return $route->getActionName();
    }

    /**
     * Gather the route's middleware names, discarding any closure middleware
     * (which cannot be matched against a configured string).
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return array<int, string>
     */
    private function resolveMiddleware(Route $route): array
    {
        // gatherMiddleware() already returns a de-duplicated, contiguous list;
        // the filter only narrows the element type to string for the contract.
        return array_filter($route->gatherMiddleware(), 'is_string');
    }

    /**
     * Extract inline suppressions from the route action array.
     *
     * Reads the `route-linter::lint-ignore` key written by the
     * `ignoreRouteLint` macro. Each entry must be an array with a `rules` array
     * and a `reason` string; entries that do not conform to this shape are
     * silently skipped so a corrupt route cache never crashes the linter.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return list<\SineMacula\RouteLinter\Dto\RouteSuppression>
     */
    private function buildSuppressions(Route $route): array
    {
        $raw = $route->getAction('route-linter::lint-ignore');

        if (!is_array($raw)) {
            return [];
        }

        $suppressions = [];

        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $rules  = isset($entry['rules'])  && is_array($entry['rules']) ? array_values($entry['rules']) : [];
            $reason = isset($entry['reason']) && is_string($entry['reason']) ? $entry['reason'] : '';

            $suppressions[] = new RouteSuppression($rules, $reason);
        }

        return $suppressions;
    }

    /**
     * Determine whether a route belongs to a vendor package.
     *
     * Resolution strategy mirrors `route:list --except-vendor`:
     * - Closure routes: reflect the closure's defining file.
     * - Controller routes: reflect the controller class's defining file.
     * - Unresolvable sources default to app-owned (false) so no app route is
     *   silently dropped.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return bool
     */
    private function isVendorRoute(Route $route): bool
    {
        $uses = $route->getAction('uses');

        if ($uses instanceof \Closure) {
            return $this->isClosureVendor($uses);
        }

        return is_string($uses) && $this->isControllerVendor($route);
    }

    /**
     * Determine whether a closure-backed route's defining file is under vendor.
     *
     * @param  \Closure(): void  $closure
     * @return bool
     */
    private function isClosureVendor(\Closure $closure): bool
    {
        try {
            $file = (new \ReflectionFunction($closure))->getFileName();
        } catch (\ReflectionException) { // @codeCoverageIgnore
            // Unreachable for a real Closure (always reflectable); defaulting
            // to app-owned is the safe direction - a route is never silently
            // dropped.
            return false; // @codeCoverageIgnore
        }

        return $this->isVendorFile($file ?: null);
    }

    /**
     * Determine whether a controller-backed route's defining class file is
     * under vendor.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return bool
     */
    private function isControllerVendor(Route $route): bool
    {
        $controllerClass = $route->getControllerClass();

        if ($controllerClass === null) {
            return false;
        }

        return $this->isVendorFile($this->classFile($controllerClass));
    }

    /**
     * Determine whether an absolute file path lives under a vendor directory.
     *
     * @param  string|null  $file
     * @return bool
     */
    private function isVendorFile(?string $file): bool
    {
        if ($file === null) {
            return false;
        }

        return str_contains($file, self::VENDOR_SEGMENT);
    }

    /**
     * Resolve the absolute file path that defines a given class.
     *
     * @param  string  $class
     * @return string|null
     */
    private function classFile(string $class): ?string
    {
        try {
            return (new \ReflectionClass($class))->getFileName() ?: null;
        } catch (\ReflectionException) {
            // An unresolvable controller class yields null, which
            // isVendorFile() maps to app-owned - the conservative direction
            // that
            // never drops a route.
            return null;
        }
    }
}
