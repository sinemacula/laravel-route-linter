<?php

declare(strict_types = 1);

namespace Benchmarks;

use Benchmarks\Support\Concerns\RouteLinterFixtures;
use PhpBench\Attributes as Bench;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Rules\DuplicateRouteNameRule;

/**
 * Benchmarks for the cross-route (aggregate) pass.
 *
 * Measures the duplicate-route-name rule over a realistically-sized route table
 * to confirm detection scales linearly - a single hashed pass over the set, not
 * a quadratic pairwise comparison - as the table grows.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
#[Bench\OutputTimeUnit('microseconds')]
final class AggregateRuleBench
{
    use RouteLinterFixtures;

    /** @var \SineMacula\RouteLinter\Rules\DuplicateRouteNameRule The aggregate rule under test. */
    private DuplicateRouteNameRule $rule;

    /** @var \SineMacula\RouteLinter\Dto\RuleConfig The shipped default rule configuration. */
    private RuleConfig $config;

    /** @var array<int, \SineMacula\RouteLinter\NormalisedRoute> A mid-size route table seeded with a few duplicate names. */
    private array $routes;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->rule   = new DuplicateRouteNameRule;
        $this->config = $this->ruleConfig();
        $this->routes = $this->buildRoutes(200);
    }

    /**
     * Benchmark detecting duplicate route names across the whole table.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchDetectDuplicateNames(): void
    {
        $this->rule->inspect($this->routes, $this->config);
    }

    /**
     * Build a route table of the given size, reusing one name every 25 routes
     * so the rule has real duplicates to emit without dominating the scan cost.
     *
     * @param  int  $count
     * @return array<int, \SineMacula\RouteLinter\NormalisedRoute>
     */
    private function buildRoutes(int $count): array
    {
        $routes = [];

        for ($i = 0; $i < $count; $i++) {
            $name     = $i % 25 === 0 ? 'shared.index' : 'resource' . $i . '.index';
            $routes[] = $this->normalisedRoute('api/v1/resource' . $i, ['GET', 'HEAD'], $name);
        }

        return $routes;
    }
}
