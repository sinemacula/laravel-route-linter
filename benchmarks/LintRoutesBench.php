<?php

declare(strict_types = 1);

namespace Benchmarks;

use Benchmarks\Support\ArrayRouteSource;
use Benchmarks\Support\Concerns\RouteLinterFixtures;
use Benchmarks\Support\StaticRuleConfiguration;
use PhpBench\Attributes as Bench;
use SineMacula\RouteLinter\LintRoutes;

/**
 * Benchmarks for the end-to-end LintRoutes use case.
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
        $this->cleanLinter->lint();
    }

    /**
     * Benchmark linting a table mixing clean routes, violations and waivers.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(100)]
    #[Bench\Warmup(2)]
    public function benchLintMixedTable(): void
    {
        $this->mixedLinter->lint();
    }
}
