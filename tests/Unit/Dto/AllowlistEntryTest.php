<?php

declare(strict_types = 1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Dto\AllowlistEntry;
use Tests\TestCase;

/**
 * Tests for the AllowlistEntry DTO.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(AllowlistEntry::class)]
final class AllowlistEntryTest extends TestCase
{
    /**
     * Provide waived rule lists with a rule ID and whether it is covered.
     *
     * @return iterable<string, array{list<string>|null, string, bool}>
     */
    public static function provideCoverage(): iterable
    {
        yield 'default list covers R1' => [null, 'R1', true];
        yield 'default list covers R9' => [null, 'R9', true];
        yield 'default list covers any ID' => [null, 'PLURAL_COLLECTIONS', true];
        yield 'listed first rule' => [['R9', 'R3'], 'R9', true];
        yield 'listed second rule' => [['R9', 'R3'], 'R3', true];
        yield 'unlisted rule' => [['R9'], 'R1', false];
        yield 'unlisted ID' => [['R9'], 'PLURAL_COLLECTIONS', false];
    }

    /**
     * Test that the default empty rule list covers every rule and a non-empty
     * one only its listed rules.
     *
     * @param  list<string>|null  $rules
     * @param  string  $ruleId
     * @param  bool  $expected
     * @return void
     */
    #[DataProvider('provideCoverage')]
    public function testCoversListedRulesOrAllByDefault(?array $rules, string $ruleId, bool $expected): void
    {
        $entry = $rules === null
            ? new AllowlistEntry('users.store', 'Legacy endpoint.')
            : new AllowlistEntry('users.store', 'Scoped waiver.', $rules);

        self::assertSame($expected, $entry->covers($ruleId));
    }

    /**
     * Test that the properties round-trip and the rules default to empty.
     *
     * @return void
     */
    public function testExposesMatchReasonAndRulesWithCorrectDefaults(): void
    {
        $defaultEntry = new AllowlistEntry('api/*', 'All API routes waived.');

        self::assertSame('api/*', $defaultEntry->match);
        self::assertSame('All API routes waived.', $defaultEntry->reason);
        self::assertSame([], $defaultEntry->rules);

        $scopedEntry = new AllowlistEntry('orders.index', 'Scoped.', ['R9']);

        self::assertSame(['R9'], $scopedEntry->rules);
    }
}
