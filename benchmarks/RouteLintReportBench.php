<?php

declare(strict_types = 1);

namespace Benchmarks;

use Benchmarks\Support\Concerns\RouteLinterFixtures;
use PhpBench\Attributes as Bench;
use SineMacula\RouteLinter\RouteLintReport;

/**
 * Benchmarks for the report's ordered accessors.
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
            $this->report->addStaleWaiver('Waiver ' . ($i % 7) . ' for ' . self::RESOURCES[$i % count(self::RESOURCES)]);
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
