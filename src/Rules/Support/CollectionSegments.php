<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Rules\Support;

use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;

/**
 * Locates the segments of a route that name a resource collection.
 *
 * A literal names a collection when a parameter follows it or it is the last
 * literal; one followed by another literal is a namespace. Singleton segments
 * are excluded, whether inferred from a singleton member route or configured.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class CollectionSegments
{
    /** @var array<string, array<int, string>> The methods of each singleton member action. */
    private const array SINGLETON_ACTIONS = [
        'show'    => ['GET'],
        'update'  => ['PUT', 'PATCH'],
        'destroy' => ['DELETE'],
    ];

    /**
     * Return the indexes of the route's collection segments, in path order.
     *
     * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return list<int>
     */
    public function indexes(NormalisedRoute $route, RuleConfig $config): array
    {
        $segments   = $route->segments;
        $singletons = [$this->inferredSingleton($route), ...$this->declaredSingletons($segments, $config->singletonResources)];
        $indexes    = [];

        foreach ($segments as $index => $segment) {
            if (!$this->isLiteral($segment) || in_array($index, $singletons, true) || !$this->namesCollection($index, $segments)) {
                continue;
            }

            $indexes[] = $index;
        }

        return $indexes;
    }

    /**
     * Determine whether the literal at the given index is followed by a
     * parameter or by no literal.
     *
     * @param  int  $index
     * @param  array<int, string>  $segments
     * @return bool
     */
    private function namesCollection(int $index, array $segments): bool
    {
        foreach (array_slice($segments, $index + 1) as $next) {
            if ($next === '') {
                continue;
            }

            return $this->isParameter($next);
        }

        return true;
    }

    /**
     * Find the segment a singleton member route addresses, or null.
     *
     * An ordinary resource's member route ends in its parameter; a singleton's
     * ends in a literal.
     *
     * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
     * @return int|null
     */
    private function inferredSingleton(NormalisedRoute $route): ?int
    {
        $action = $route->name === null ? '' : substr((string) strrchr($route->name, '.'), 1);

        if (array_intersect(self::SINGLETON_ACTIONS[$action] ?? [], $route->methods) === []) {
            return null;
        }

        $last = array_key_last(array_filter($route->segments, static fn (string $segment): bool => $segment !== ''));

        return $last !== null && $this->isLiteral($route->segments[$last]) ? $last : null;
    }

    /**
     * Find the segments whose path ends with a configured singleton resource.
     *
     * @param  array<int, string>  $segments
     * @param  array<int, string>  $resources
     * @return list<int>
     */
    private function declaredSingletons(array $segments, array $resources): array
    {
        $route      = $this->anonymiseParameters($segments);
        $singletons = [];

        foreach ($resources as $resource) {
            $suffix = '/' . implode('/', $this->anonymiseParameters(explode('/', trim($resource, '/'))));

            foreach (array_keys($route) as $end) {
                if (!str_ends_with('/' . implode('/', array_slice($route, 0, $end + 1)), $suffix)) {
                    continue;
                }

                $singletons[] = $end;
            }
        }

        return $singletons;
    }

    /**
     * Replace each parameter with `{}`, so parameter names do not matter.
     *
     * @param  array<int, string>  $segments
     * @return array<int, string>
     */
    private function anonymiseParameters(array $segments): array
    {
        return array_map(fn (string $segment): string => $this->isParameter($segment) ? '{}' : $segment, $segments);
    }

    /**
     * Determine whether a segment is neither empty nor a parameter.
     *
     * @param  string  $segment
     * @return bool
     */
    private function isLiteral(string $segment): bool
    {
        return $segment !== '' && !$this->isParameter($segment);
    }

    /**
     * Determine whether a segment is a route parameter.
     *
     * @param  string  $segment
     * @return bool
     */
    private function isParameter(string $segment): bool
    {
        return str_starts_with($segment, '{');
    }
}
