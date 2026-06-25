<?php

declare(strict_types = 1);

namespace Benchmarks;

use Benchmarks\Support\Concerns\RouteLinterFixtures;
use PhpBench\Attributes as Bench;
use SineMacula\RouteLinter\RouteLintReport;

/**
 * Benchmarks for the RouteLintReport deterministic-ordering hot paths.
 *
 * The report guarantees byte-identical output across runs by re-sorting on
 * every read: errors() and warnings() filter by severity and sort by the
 * routeIdentity / ruleId / offendingSurface composite key, while staleWaivers()
 * sorts its entries. Each accessor is measured over a representative
 * accumulated finding set so the filter-and-sort cost is captured at the size a
 * large, defect-heavy route table would produce.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
#[Bench\OutputTimeUnit('microseconds')]
final class RouteLintReportBench
{
    use RouteLinterFixtures;

    /** @var int Representative number of violations accumulated by a defect-heavy run. */
    private const int VIOLATION_COUNT = 100;

    /** @var int Representative number of stale waivers accumulated by a defect-heavy run. */
    private const int STALE_WAIVER_COUNT = 20;

    /** @var \SineMacula\RouteLinter\RouteLintReport A report pre-loaded with a representative finding set. */
    private RouteLintReport $report;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->report = new RouteLintReport;

        foreach ($this->violations(self::VIOLATION_COUNT) as $violation) {
            $this->report->addViolation($violation);
        }

        for ($i = 0; $i < self::STALE_WAIVER_COUNT; $i++) {
            $this->report->addStaleWaiver('BL-' . ($i % 7) . ' waiver for ' . self::RESOURCES[$i % count(self::RESOURCES)]);
        }
    }

    /**
     * Benchmark filtering and ordering the error-severity findings.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchErrors(): void
    {
        $this->report->errors();
    }

    /**
     * Benchmark filtering and ordering the warning-severity findings.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchWarnings(): void
    {
        $this->report->warnings();
    }

    /**
     * Benchmark ordering the accumulated stale-waiver entries.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchStaleWaivers(): void
    {
        $this->report->staleWaivers();
    }
}
