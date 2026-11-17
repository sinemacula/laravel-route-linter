<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter;

use SineMacula\RouteLinter\Contracts\AggregateRule;
use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;

/**
 * Runs the configured rules, in the order given, over the route table.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class RouteLintEngine
{
    /** @var array<int, \SineMacula\RouteLinter\Contracts\Rule> */
    private array $rules;

    /** @var array<int, \SineMacula\RouteLinter\Contracts\AggregateRule> */
    private array $aggregateRules;

    /**
     * Create a new route lint engine, rejecting duplicate rule ids.
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
     * Run every per-route rule over the given route.
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
     * Run every aggregate rule over the whole route set.
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
