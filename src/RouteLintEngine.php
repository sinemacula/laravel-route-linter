<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter;

use SineMacula\RouteLinter\Contracts\AggregateRule;
use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;

/**
 * Pure ordered rule-set orchestrator for the route table.
 *
 * Holds two kinds of rule: per-route {@see Rule}s, run one route at a time via
 * inspect(), and cross-route {@see AggregateRule}s, run once over the whole set
 * via inspectAll(). Both kinds are executed in the fixed order they were
 * supplied to the constructor - no sorting, no randomness, no global state - so
 * calling either method twice with the same inputs returns byte-identical
 * arrays (NFR-01). Rule identifiers must be unique across both kinds.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class RouteLintEngine
{
    /** @var array<int, \SineMacula\RouteLinter\Contracts\Rule> */
    private readonly array $rules;

    /** @var array<int, \SineMacula\RouteLinter\Contracts\AggregateRule> */
    private readonly array $aggregateRules;

    /**
     * Create a new route lint engine.
     *
     * Rules are partitioned by kind: those implementing AggregateRule run in
     * the aggregate pass, the rest in the per-route pass. Rule identifiers must
     * be unique across both kinds: a duplicate id would make report ordering
     * and per-rule suppression ambiguous, so the engine rejects it at
     * construction.
     *
     * @param  \SineMacula\RouteLinter\Contracts\AggregateRule|\SineMacula\RouteLinter\Contracts\Rule  ...$rules
     *
     * @throws \SineMacula\RouteLinter\Exceptions\InvalidConfigurationException
     */
    public function __construct(AggregateRule|Rule ...$rules)
    {
        $seen      = [];
        $perRoute  = [];
        $aggregate = [];

        foreach ($rules as $rule) {
            $id = $rule->id();

            if (isset($seen[$id])) {
                throw new InvalidConfigurationException(sprintf('Duplicate route-linter rule id "%s".', $id));
            }

            $seen[$id] = true;

            if ($rule instanceof AggregateRule) {
                $aggregate[] = $rule;
            } else {
                $perRoute[] = $rule;
            }
        }

        $this->rules          = $perRoute;
        $this->aggregateRules = $aggregate;
    }

    /**
     * Run every per-route rule over the given route and return the aggregated
     * violations.
     *
     * Rules are executed in the fixed order they were supplied to the
     * constructor. The returned array preserves that order - no additional
     * sorting is applied here; deterministic final ordering is the report's
     * responsibility.
     *
     * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    public function inspect(NormalisedRoute $route, RuleConfig $config): array
    {
        $violations = [];

        foreach ($this->rules as $rule) {
            array_push($violations, ...$rule->inspect($route, $config));
        }

        return $violations;
    }

    /**
     * Run every cross-route rule over the whole route set and return the
     * aggregated violations.
     *
     * Aggregate rules are executed in the fixed order they were supplied to the
     * constructor; the returned array preserves that order. Deterministic final
     * ordering is the report's responsibility.
     *
     * @param  array<int, \SineMacula\RouteLinter\NormalisedRoute>  $routes
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    public function inspectAll(array $routes, RuleConfig $config): array
    {
        $violations = [];

        foreach ($this->aggregateRules as $rule) {
            array_push($violations, ...$rule->inspect($routes, $config));
        }

        return $violations;
    }
}
