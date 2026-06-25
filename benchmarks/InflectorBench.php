<?php

declare(strict_types = 1);

namespace Benchmarks;

use Benchmarks\Support\Concerns\RouteLinterFixtures;
use PhpBench\Attributes as Bench;
use SineMacula\RouteLinter\Inflection\FrameworkInflector;

/**
 * Benchmarks for the FrameworkInflector adapter hot paths.
 *
 * The inflector wraps the framework singulariser, which is the most expensive
 * primitive the plural-collections and verb-in-path rules invoke per segment.
 * Both entry points are measured along their two branches: the configured
 * uncountable short-circuit (a list scan that never reaches the framework) and
 * the countable path that actually drives singularisation.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
#[Bench\OutputTimeUnit('microseconds')]
final class InflectorBench
{
    use RouteLinterFixtures;

    /** @var \SineMacula\RouteLinter\Inflection\FrameworkInflector The inflector under test. */
    private FrameworkInflector $inflector;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->inflector = $this->inflector();
    }

    /**
     * Benchmark singularising a countable plural through the framework
     * inflector.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchSingularCountable(): void
    {
        $this->inflector->singular('categories');
    }

    /**
     * Benchmark singularising an uncountable that short-circuits the framework.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchSingularUncountable(): void
    {
        $this->inflector->singular('media');
    }

    /**
     * Benchmark the plurality check on a countable plural.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchIsPluralCountable(): void
    {
        $this->inflector->isPlural('invoices');
    }

    /**
     * Benchmark the plurality check on a singular noun.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchIsPluralSingular(): void
    {
        $this->inflector->isPlural('invoice');
    }
}
