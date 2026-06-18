<?php

namespace Benchmarks;

use Benchmarks\Support\RouteLinterFixtures;
use PhpBench\Attributes as Bench;
use SineMacula\RouteLinter\ExemptionAllowlist;

/**
 * Benchmarks for the ExemptionAllowlist matching hot paths.
 *
 * The allowlist is consulted once per live route (observe) and once per
 * surviving violation (suppresses); both walk every entry running an exact name
 * compare and an `fnmatch()` wildcard test, so cost scales with allowlist size.
 * It is measured at a representative allowlist size along the observe path, the
 * suppress-hit path (an entry matches and covers the rule), and the
 * suppress-miss path (no entry matches - the full unbroken scan).
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
#[Bench\OutputTimeUnit('microseconds')]
final class ExemptionAllowlistBench
{
    use RouteLinterFixtures;

    /** @var int Representative number of entries in a maintained allowlist. */
    private const int ALLOWLIST_SIZE = 20;

    /** @var mixed Sink preventing the measured expression from being optimised away. */
    public mixed $sink = null;

    /** @var \SineMacula\RouteLinter\ExemptionAllowlist The allowlist under test. */
    private ExemptionAllowlist $allowlist;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->allowlist = new ExemptionAllowlist($this->allowlistEntries(self::ALLOWLIST_SIZE));
    }

    /**
     * Benchmark observing a live route against every allowlist entry.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchObserve(): void
    {
        $this->allowlist->observe('orders.index', 'api/v1/orders');
    }

    /**
     * Benchmark a suppression test that matches an entry and covers the rule.
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchSuppressesHit(): void
    {
        $this->sink = $this->allowlist->suppresses('orders.index', 'api/v1/orders', 'R4');
    }

    /**
     * Benchmark a suppression test that matches no entry (the full scan).
     *
     * @return void
     */
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchSuppressesMiss(): void
    {
        $this->sink = $this->allowlist->suppresses('unknown.route', 'api/v1/nonexistent/resource', 'R1');
    }
}
