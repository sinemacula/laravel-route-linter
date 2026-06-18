<?php

namespace Benchmarks;

use Benchmarks\Support\RouteLinterFixtures;
use PhpBench\Attributes as Bench;
use SineMacula\RouteLinter\Rules\Support\SegmentNormaliser;

/**
 * Benchmarks for the SegmentNormaliser six-step reduction pipeline.
 *
 * The normaliser is the regex-heavy hot path under the verb-in-path rule: per
 * surviving segment it runs a camelCase split, a multi-delimiter split, a
 * lowercase map, and an inflector singularisation. It is measured along two
 * URIs - a short plain collection path, and a deep path packed with version and
 * API prefixes, route parameters, and compound camelCase/kebab/snake/dot
 * segments - to bound both the cheap common case and the decomposition-heavy
 * worst case.
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

    /** @var mixed Sink preventing the measured expression from being optimised away. */
    public mixed $sink = null;

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
        $this->sink = $this->normaliser->normalise(self::SIMPLE_URI, self::UNCOUNTABLES);
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
        $this->sink = $this->normaliser->normalise(self::COMPOUND_URI, self::UNCOUNTABLES);
    }
}
