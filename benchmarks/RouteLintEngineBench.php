<?php

namespace Benchmarks;

use Benchmarks\Support\RouteLinterFixtures;
use PhpBench\Attributes as Bench;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\RouteLintEngine;

/**
 * Benchmarks for the RouteLintEngine per-route orchestrator.
 *
 * Isolates the cost of running the full nine-rule set over a single normalised
 * route, free of the sourcing, suppression, and reporting work the use case
 * layers on top. It is measured along two routes: a clean route every rule
 * clears without emitting a finding (the common case), and a deliberately
 * defective route that trips several rules at once — bounding both the
 * best-case scan and the allocation cost of constructing many violations.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
#[Bench\OutputTimeUnit('microseconds')]
final class RouteLintEngineBench
{
    use RouteLinterFixtures;

    /** @var mixed Sink preventing the measured expression from being optimised away. */
    public mixed $sink = null;

    /** @var \SineMacula\RouteLinter\RouteLintEngine The fully-assembled rule engine. */
    private RouteLintEngine $engine;

    /** @var \SineMacula\RouteLinter\Dto\RuleConfig The shipped default rule configuration. */
    private RuleConfig $config;

    /** @var \SineMacula\RouteLinter\NormalisedRoute A fully RESTful route that trips no rule. */
    private NormalisedRoute $cleanRoute;

    /** @var \SineMacula\RouteLinter\NormalisedRoute A defective route that trips several rules. */
    private NormalisedRoute $dirtyRoute;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->engine     = $this->engine($this->inflector());
        $this->config     = $this->ruleConfig();
        $this->cleanRoute = $this->normalisedRoute('api/v1/users/{user}/orders', ['GET', 'HEAD'], 'users.orders.index');
        $this->dirtyRoute = $this->normalisedRoute('api/v1/getUserProfiles/{id}/edit', ['GET', 'PURGE'], 'Bad_Name');
    }

    /**
     * Benchmark inspecting a clean route every rule clears.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchInspectClean(): void
    {
        $this->sink = $this->engine->inspect($this->cleanRoute, $this->config);
    }

    /**
     * Benchmark inspecting a defective route that trips several rules at once.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchInspectDirty(): void
    {
        $this->sink = $this->engine->inspect($this->dirtyRoute, $this->config);
    }
}
