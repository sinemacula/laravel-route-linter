<?php

declare(strict_types = 1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * Provide suppressed rule lists with a rule ID and whether it is covered.
     *
     * @return iterable<string, array{list<string>, string, bool}>
     */
    public static function provideCoverage(): iterable
    {
        yield 'empty list covers R1' => [[], 'R1', true];
        yield 'empty list covers R9' => [[], 'R9', true];
        yield 'empty list covers any ID' => [[], 'PLURAL_COLLECTIONS', true];
        yield 'listed first rule' => [['R9', 'R3'], 'R9', true];
        yield 'listed second rule' => [['R9', 'R3'], 'R3', true];
        yield 'unlisted rule' => [['R9'], 'R1', false];
        yield 'unlisted ID' => [['R9'], 'PLURAL_COLLECTIONS', false];
    }

    /**
     * Test that an empty rule list covers every rule and a non-empty one only
     * its listed rules.
     *
     * @param  list<string>  $rules
     * @param  string  $ruleId
     * @param  bool  $expected
     * @return void
     */
    #[DataProvider('provideCoverage')]
    public function testCoversListedRulesOrAllWhenEmpty(array $rules, string $ruleId, bool $expected): void
    {
        self::assertSame($expected, (new RouteSuppression($rules, 'Reason.'))->covers($ruleId));
    }

    /**
     * Test that the rules and reason are exposed as given.
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
