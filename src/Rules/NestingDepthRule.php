<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\Support\CollectionSegments;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R11: Nesting-depth smell warning.
 *
 * Flags routes whose URI nests more collection levels than the configured
 * maximum (three by default). A collection level is a segment that names a
 * resource collection, as located by {@see CollectionSegments}: namespace
 * segments and singleton resources are not levels, and neither are `api` or
 * version tokens matching `v\d+`. Nesting beyond the maximum indicates a URI
 * that could be restructured as a top-level or shallower resource, reducing
 * coupling between resource types.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class NestingDepthRule implements Rule
{
    /** @var array<int, string> Literal prefix segments excluded from the collection-level count. */
    private const array EXCLUDED_PREFIXES = ['api'];

    /**
     * Create a new nesting depth rule.
     *
     * @param  \SineMacula\RouteLinter\Rules\Support\CollectionSegments  $collections
     */
    public function __construct(

        /** Locator of the route's collection segments */
        private CollectionSegments $collections = new CollectionSegments,
    ) {}

    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return 'R11';
    }

    /**
     * Return the severity tier for this rule.
     *
     * @return \SineMacula\RouteLinter\Enums\Severity
     */
    #[\Override]
    public function severity(): Severity
    {
        return Severity::WARNING;
    }

    /**
     * Inspect one normalised route and return zero or more violations.
     *
     * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    #[\Override]
    public function inspect(NormalisedRoute $route, RuleConfig $config): array
    {
        $depth = $this->collectionDepth($route, $config);

        if ($depth <= $config->nestingMaxDepth) {
            return [];
        }

        return [
            new Violation(
                ruleId: $this->id(),
                severity: $this->severity(),
                routeIdentity: $route->identity(),
                offendingSurface: $route->uri,
                remediationHint: null,
            ),
        ];
    }

    /**
     * Count the collection levels of a route, excluding `api` and version
     * tokens (`v` followed by one or more digits).
     *
     * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return int
     */
    private function collectionDepth(NormalisedRoute $route, RuleConfig $config): int
    {
        $depth = 0;

        foreach ($this->collections->indexes($route, $config) as $index) {
            $segment = $route->segments[$index];

            if (in_array($segment, self::EXCLUDED_PREFIXES, true) || preg_match('/^v\d+$/i', $segment) === 1) {
                continue;
            }

            $depth++;
        }

        return $depth;
    }
}
