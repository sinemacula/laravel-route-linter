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
     * Test that all four surfaces are stored independently and round-trip
     * correctly, including the AllowlistEntry objects within exemptions.
     *
     * @return void
     */
    public function testHoldsThreeSeparateSurfacesAndHints(): void
    {
        // Arrange
        $denylist     = ['get', 'list', 'create'];
        $hints        = ['get' => 'Use GET /resources', 'list' => 'Use GET /resources'];
        $exemption    = new AllowlistEntry(match: 'users.legacy', reason: 'Pre-REST route kept for backward compat');
        $uncountables = ['data', 'media'];

        // Act
        $config = new RuleConfig(
            verbDenylist: $denylist,
            remediationHints: $hints,
            exemptions: [$exemption],
            uncountables: $uncountables,
        );

        // Assert - each surface is exactly the value passed in
        self::assertSame($denylist, $config->verbDenylist);
        self::assertSame($hints, $config->remediationHints);
        self::assertCount(1, $config->exemptions);
        self::assertSame($exemption, $config->exemptions[0]);
        self::assertSame($uncountables, $config->uncountables);

        // Assert AllowlistEntry properties round-trip through exemptions
        self::assertSame('users.legacy', $config->exemptions[0]->match);
        self::assertSame('Pre-REST route kept for backward compat', $config->exemptions[0]->reason);
    }

    /**
     * Test that a RuleConfig with an empty exemptions array is valid and
     * exemptions returns an empty array.
     *
     * @return void
     */
    public function testExemptionsDefaultEmptyIsRepresentable(): void
    {
        // Arrange & Act
        $config = new RuleConfig(
            verbDenylist: ['get'],
            remediationHints: [],
            exemptions: [],
            uncountables: [],
        );

        // Assert
        self::assertSame([], $config->exemptions);
    }

    /**
     * Test that nestingMaxDepth defaults to 3 when omitted and round-trips a
     * custom value when provided.
     *
     * @return void
     */
    public function testNestingMaxDepthDefaultsToThreeAndRoundTrips(): void
    {
        $default = new RuleConfig(
            verbDenylist: [],
            remediationHints: [],
            exemptions: [],
            uncountables: [],
        );

        self::assertSame(3, $default->nestingMaxDepth);

        $custom = new RuleConfig(
            verbDenylist: [],
            remediationHints: [],
            exemptions: [],
            uncountables: [],
            nestingMaxDepth: 5,
        );

        self::assertSame(5, $custom->nestingMaxDepth);
    }
}
