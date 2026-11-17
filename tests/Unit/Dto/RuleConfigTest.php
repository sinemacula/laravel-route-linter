<?php

declare(strict_types = 1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Dto\AllowlistEntry;
use SineMacula\RouteLinter\Dto\RuleConfig;
use Tests\TestCase;

/**
 * Tests for the RuleConfig DTO.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(RuleConfig::class)]
final class RuleConfigTest extends TestCase
{
    /**
     * Test that each config surface round-trips as given.
     *
     * @return void
     */
    public function testHoldsEachSurfaceSeparately(): void
    {
        $denylist     = ['get', 'list', 'create'];
        $hints        = ['get' => 'Use GET /resources', 'list' => 'Use GET /resources'];
        $exemption    = new AllowlistEntry(match: 'users.legacy', reason: 'Pre-REST route kept for backward compat');
        $uncountables = ['data', 'media'];

        $config = new RuleConfig(
            verbDenylist: $denylist,
            remediationHints: $hints,
            exemptions: [$exemption],
            uncountables: $uncountables,
        );

        self::assertSame($denylist, $config->verbDenylist);
        self::assertSame($hints, $config->remediationHints);
        self::assertSame([$exemption], $config->exemptions);
        self::assertSame($uncountables, $config->uncountables);
    }

    /**
     * Test that an empty exemption list is representable.
     *
     * @return void
     */
    public function testExemptionsMayBeEmpty(): void
    {
        self::assertSame([], (new RuleConfig(['get'], [], [], []))->exemptions);
    }

    /**
     * Test that nestingMaxDepth defaults to 3 and round-trips a custom value.
     *
     * @return void
     */
    public function testNestingMaxDepthDefaultsToThreeAndRoundTrips(): void
    {
        self::assertSame(3, (new RuleConfig([], [], [], []))->nestingMaxDepth);
        self::assertSame(5, (new RuleConfig([], [], [], [], nestingMaxDepth: 5))->nestingMaxDepth);
    }

    /**
     * Test that verbAllowlist defaults to empty and round-trips a custom value.
     *
     * @return void
     */
    public function testVerbAllowlistDefaultsToEmptyAndRoundTrips(): void
    {
        self::assertSame([], (new RuleConfig([], [], [], []))->verbAllowlist);
        self::assertSame(['reset'], (new RuleConfig([], [], [], [], verbAllowlist: ['reset']))->verbAllowlist);
    }

    /**
     * Test that singletonResources defaults to empty and round-trips a custom
     * value.
     *
     * @return void
     */
    public function testSingletonResourcesDefaultToEmptyAndRoundTrip(): void
    {
        self::assertSame([], (new RuleConfig([], [], [], []))->singletonResources);
        self::assertSame(['profile'], (new RuleConfig([], [], [], [], singletonResources: ['profile']))->singletonResources);
    }
}
