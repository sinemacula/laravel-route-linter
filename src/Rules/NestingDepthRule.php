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
 * Rule R11: routes nest no more collection levels than configured.
 *
 * Collection levels are located by {@see CollectionSegments}; `api` and version
 * tokens such as `v1` are not levels.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class NestingDepthRule implements Rule
{
    /**
     * Create a new nesting depth rule.
     *
     * @param  \SineMacula\RouteLinter\Rules\Support\CollectionSegments  $collections
     */
    public function __construct(

        /** Locates the route's collection segments */
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
        if ($this->collectionDepth($route, $config) <= $config->nestingMaxDepth) {
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
     * Count the route's collection levels.
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

            if ($segment === 'api' || preg_match('/^v\d+$/i', $segment) === 1) {
                continue;
            }

            $depth++;
        }

        return $depth;
    }
}
