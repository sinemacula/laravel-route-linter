<?php

namespace Benchmarks;

use Benchmarks\Support\ArrayRouteSource;
use Benchmarks\Support\RouteLinterFixtures;
use Benchmarks\Support\StaticRuleConfiguration;
use PhpBench\Attributes as Bench;
use SineMacula\RouteLinter\LintRoutes;

/**
 * Benchmarks for the end-to-end LintRoutes use case.
 *
 * This is the headline cost: for a whole route table the use case sources every
 * descriptor, normalises each URI, runs all nine rules, applies inline and
 * allowlist suppression, and assembles the deterministically-ordered report. It
 * is measured along two axes at a representative mid-size API table - a
 * fully-clean table (the steady-state cost a passing CI run pays) and a
 * realistic mixed table whose defects drive the violation, suppression, and
 * stale-waiver paths the clean table never reaches.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
#[Bench\OutputTimeUnit('microseconds')]
final class LintRoutesBench
{
    use RouteLinterFixtures;

    /** @var int Representative number of app-owned routes in a mid-size API. */
    private const int ROUTE_TABLE_SIZE = 50;

    /** @var mixed Sink preventing the measured expression from being optimised away. */
    public mixed $sink = null;

    /** @var \SineMacula\RouteLinter\LintRoutes Use case wired over a fully-clean route table. */
    private LintRoutes $cleanLinter;

    /** @var \SineMacula\RouteLinter\LintRoutes Use case wired over a realistic mixed route table. */
    private LintRoutes $mixedLinter;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $inflector = $this->inflector();

        $this->cleanLinter = new LintRoutes(
            new ArrayRouteSource($this->cleanRouteTable(self::ROUTE_TABLE_SIZE)),
            new StaticRuleConfiguration($this->ruleConfig()),
            $this->engine($inflector),
        );

        $this->mixedLinter = new LintRoutes(
            new ArrayRouteSource($this->mixedRouteTable(self::ROUTE_TABLE_SIZE)),
            new StaticRuleConfiguration($this->ruleConfig($this->mixedExemptions())),
            $this->engine($inflector),
        );
    }

    /**
     * Benchmark linting a fully RESTful, violation-free route table.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(100)]
    #[Bench\Warmup(2)]
    public function benchLintCleanTable(): void
    {
        $this->sink = $this->cleanLinter->lint();
    }

    /**
     * Benchmark linting a realistic table mixing clean routes, violations,
     * inline suppressions, and allowlist waivers.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(100)]
    #[Bench\Warmup(2)]
    public function benchLintMixedTable(): void
    {
        $this->sink = $this->mixedLinter->lint();
    }
}
