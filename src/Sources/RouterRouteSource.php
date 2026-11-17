<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Sources;

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteAction;
use Illuminate\Routing\Router;
use SineMacula\RouteLinter\Contracts\RouteSource;
use SineMacula\RouteLinter\Dto\RouteDescriptor;
use SineMacula\RouteLinter\Dto\RouteSuppression;

/**
 * Reads the app's routes from the router, skipping routes defined in vendor/.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class RouterRouteSource implements RouteSource
{
    /** @var string The path segment that marks a vendor file. */
    private const string VENDOR_SEGMENT = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;

    /**
     * Create a new router-backed route source.
     *
     * @param  \Illuminate\Routing\Router  $router
     */
    public function __construct(

        /** The router whose routes are read */
        private Router $router,
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
     * Map a framework route to a descriptor.
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
     * Resolve the handler as `Class@method`, or null for a closure route.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return string|null
     */
    private function resolveHandler(Route $route): ?string
    {
        if ($route->getAction('uses') instanceof \Closure || RouteAction::containsSerializedClosure($route->action)) {
            return null;
        }

        return $route->getActionName();
    }

    /**
     * Gather the route's middleware names, dropping closure middleware.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return array<int, string>
     */
    private function resolveMiddleware(Route $route): array
    {
        return array_filter($route->gatherMiddleware(), 'is_string');
    }

    /**
     * Read the suppressions the `ignoreRouteLint` macro stored on the route.
     *
     * Malformed entries are skipped, so a corrupt route cache cannot crash it.
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
            $rules = is_array($entry) ? $this->suppressedRules($entry) : null;

            if ($rules === null) {
                continue;
            }

            $reason = isset($entry['reason']) && is_string($entry['reason']) ? $entry['reason'] : '';

            $suppressions[] = new RouteSuppression($rules, $reason);
        }

        return $suppressions;
    }

    /**
     * Read the rule IDs a suppression covers, or null when it is malformed.
     *
     * A bad ID voids the entry: dropping it could widen it to every rule.
     *
     * @param  array<mixed>  $entry
     * @return list<string>|null
     */
    private function suppressedRules(array $entry): ?array
    {
        if (!isset($entry['rules']) || !is_array($entry['rules'])) {
            return [];
        }

        $rules = [];

        foreach ($entry['rules'] as $rule) {
            if (!is_string($rule)) {
                return null;
            }

            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * Determine whether the route's closure or controller lives in vendor/.
     *
     * Anything unresolvable counts as app-owned, so no app route is dropped.
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
     * Determine whether a closure is defined in vendor/.
     *
     * @param  \Closure(): void  $closure
     * @return bool
     */
    private function isClosureVendor(\Closure $closure): bool
    {
        try {
            $file = (new \ReflectionFunction($closure))->getFileName();
        } catch (\ReflectionException) { // @codeCoverageIgnore
            return false; // @codeCoverageIgnore
        }

        return $this->isVendorFile($file ?: null);
    }

    /**
     * Determine whether the route's controller is defined in vendor/.
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
     * Determine whether a file path lies under a vendor directory.
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
     * Return the file that defines a class, or null when it cannot be found.
     *
     * @param  string  $class
     * @return string|null
     */
    private function classFile(string $class): ?string
    {
        if (!class_exists($class)) {
            return null;
        }

        return (new \ReflectionClass($class))->getFileName() ?: null;
    }
}
