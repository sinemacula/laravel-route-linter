<?php

declare(strict_types = 1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Dto\RouteSuppression;
use Tests\TestCase;

/**
 * Tests for the RouteSuppression DTO.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(RouteSuppression::class)]
final class RouteSuppressionTest extends TestCase
{
    /**
     * Test that covers() returns true for any rule ID when the rules list is
     * empty, i.e. the suppression targets all rules.
     *
     * @return void
     */
    public function testCoversReturnsTrueForAnyRuleWhenRulesListIsEmpty(): void
    {
        $suppression = new RouteSuppression([], 'Suppresses all rules.');

        self::assertTrue($suppression->covers('R1'));
        self::assertTrue($suppression->covers('R9'));
        self::assertTrue($suppression->covers('PLURAL_COLLECTIONS'));
    }

    /**
     * Test that covers() returns true when the given rule ID is explicitly
     * present in the suppression's rules list.
     *
     * @return void
     */
    public function testCoversReturnsTrueForExplicitlyListedRule(): void
    {
        $suppression = new RouteSuppression(['R9', 'R3'], 'Specific rules suppressed.');

        self::assertTrue($suppression->covers('R9'));
        self::assertTrue($suppression->covers('R3'));
    }

    /**
     * Test that covers() returns false when the given rule ID is not in the
     * suppression's non-empty rules list.
     *
     * @return void
     */
    public function testCoversReturnsFalseForRuleNotInList(): void
    {
        $suppression = new RouteSuppression(['R9'], 'Only R9 suppressed.');

        self::assertFalse($suppression->covers('R1'));
        self::assertFalse($suppression->covers('PLURAL_COLLECTIONS'));
    }

    /**
     * Test that the constructor correctly exposes rules and reason as public
     * properties.
     *
     * @return void
     */
    public function testExposesRulesAndReason(): void
    {
        $suppression = new RouteSuppression(['R9', 'R3'], 'Legacy route.');

        self::assertSame(['R9', 'R3'], $suppression->rules);
        self::assertSame('Legacy route.', $suppression->reason);
    }
}
