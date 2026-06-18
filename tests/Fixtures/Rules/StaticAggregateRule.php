<?php

namespace Tests\Fixtures\Rules;

use SineMacula\RouteLinter\Contracts\AggregateRule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Severity;
use SineMacula\RouteLinter\Violation;

/**
 * Fixture aggregate rule that emits one violation per route it is handed.
 *
 * Each violation is attributed to its route's identity, so tests can prove the
 * engine ran the rule across the whole set and that aggregate violations flow
 * through the same suppression path as per-route ones. The id and severity are
 * configurable so a single fixture covers several scenarios.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class StaticAggregateRule implements AggregateRule
{
    /**
     * Create a new static aggregate rule.
     *
     * @param  string  $ruleId
     * @param  \SineMacula\RouteLinter\Severity  $severityTier
     */
    public function __construct(
        private readonly string $ruleId = 'AGG',
        private readonly Severity $severityTier = Severity::WARNING,
    ) {}

    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return $this->ruleId;
    }

    /**
     * Return the severity tier for this rule.
     *
     * @return \SineMacula\RouteLinter\Severity
     */
    #[\Override]
    public function severity(): Severity
    {
        return $this->severityTier;
    }

    /**
     * Emit one violation per route, attributed to that route's identity.
     *
     * @param  array<int, \SineMacula\RouteLinter\NormalisedRoute>  $routes
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    #[\Override]
    public function inspect(array $routes, RuleConfig $config): array
    {
        return array_map(
            fn (NormalisedRoute $route): Violation => new Violation(
                ruleId: $this->ruleId,
                severity: $this->severityTier,
                routeIdentity: $route->identity(),
                offendingSurface: 'aggregate',
                remediationHint: null,
            ),
            $routes,
        );
    }
}
