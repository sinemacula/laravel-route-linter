<?php

declare(strict_types = 1);

namespace Benchmarks;

use Benchmarks\Support\Concerns\RouteLinterFixtures;
use PhpBench\Attributes as Bench;
use SineMacula\RouteLinter\Rules\Support\SegmentNormaliser;

/**
 * Benchmarks for the segment normaliser.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
#[Bench\OutputTimeUnit('microseconds')]
final class SegmentNormaliserBench
{
    use RouteLinterFixtures;

    /** @var string A short plain collection path with a single route parameter. */
    private const string SIMPLE_URI = 'api/v1/users/{user}/orders';

    /** @var string A deep path packed with prefixes, parameters, and compound segments. */
    private const string COMPOUND_URI = 'api/v2/userProfiles/{user}/order-items/{item}/audit_logs/exportReports';

    /** @var \SineMacula\RouteLinter\Rules\Support\SegmentNormaliser The normaliser under test. */
    private SegmentNormaliser $normaliser;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->normaliser = new SegmentNormaliser($this->inflector());
    }

    /**
     * Benchmark normalising a short plain collection path.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchNormaliseSimple(): void
    {
        $this->normaliser->normalise(self::SIMPLE_URI, self::UNCOUNTABLES);
    }

    /**
     * Benchmark normalising a deep, decomposition-heavy compound path.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchNormaliseCompound(): void
    {
        $this->normaliser->normalise(self::COMPOUND_URI, self::UNCOUNTABLES);
    }
}
