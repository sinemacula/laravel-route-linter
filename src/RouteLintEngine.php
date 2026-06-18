<?php

namespace SineMacula\RouteLinter;

use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;

/**
 * Pure ordered rule-set orchestrator for a single route.
 *
 * Runs each registered rule over a single normalised route and returns the
 * aggregated violations in a deterministic order. Rules are executed in the
 * fixed order they were supplied to the constructor - no sorting, no
 * randomness, no global state - so calling inspect() twice with the same inputs
 * returns byte-identical arrays (NFR-01).
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class RouteLintEngine
{
    /** @var array<int|string, \SineMacula\RouteLinter\Contracts\Rule> */
    private readonly array $rules;

    /**
     * Create a new route lint engine.
     *
     * Rule identifiers must be unique: a duplicate id would make report
     * ordering and per-rule suppression ambiguous, so the engine rejects it at
     * construction.
     *
     * @param  \SineMacula\RouteLinter\Contracts\Rule  ...$rules
     *
     * @throws \SineMacula\RouteLinter\Exceptions\InvalidConfigurationException
     */
    public function __construct(Rule ...$rules)
    {
        $seen = [];

        foreach ($rules as $rule) {
            $id = $rule->id();

            if (isset($seen[$id])) {
                throw new InvalidConfigurationException(sprintf('Duplicate route-linter rule id "%s".', $id));
            }

            $seen[$id] = true;
        }

        $this->rules = $rules;
    }

    /**
     * Run every rule over the given normalised route and return the aggregated
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
}
