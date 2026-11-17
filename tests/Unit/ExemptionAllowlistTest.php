<?php

declare(strict_types = 1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Dto\AllowlistEntry;
use SineMacula\RouteLinter\ExemptionAllowlist;
use Tests\TestCase;

/**
 * Tests for the ExemptionAllowlist domain service.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(ExemptionAllowlist::class)]
final class ExemptionAllowlistTest extends TestCase
{
    /**
     * Test that an empty allowlist suppresses nothing and reports nothing.
     *
     * @return void
     */
    public function testEmptyAllowlistSuppressesNothing(): void
    {
        $allowlist = new ExemptionAllowlist([]);

        $allowlist->observe('users.index', 'users');

        self::assertFalse($allowlist->suppresses('users.index', 'users', 'R1'));
        self::assertSame([], $allowlist->unmatched());
        self::assertSame([], $allowlist->unused());
    }

    /**
     * Test that an entry without a rules list suppresses any rule.
     *
     * @return void
     */
    public function testEntryWithoutRulesSuppressesAnyRule(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('users.store', 'Legacy store endpoint kept for backward compat.'),
        ]);

        self::assertTrue($allowlist->suppresses('users.store', 'users', 'R1'));
        self::assertTrue($allowlist->suppresses('users.store', 'users', 'R9'));
        self::assertTrue($allowlist->suppresses('users.store', 'users', 'R99'));
    }

    /**
     * Test that an entry with a rules list suppresses only those rules.
     *
     * @return void
     */
    public function testEntryWithRulesSuppressesOnlyListedRules(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('login', 'Legacy auth endpoint.', ['R1']),
        ]);

        self::assertTrue($allowlist->suppresses(null, 'login', 'R1'));
        self::assertFalse($allowlist->suppresses(null, 'login', 'R2'));
    }

    /**
     * Test that an entry matches by exact route name.
     *
     * @return void
     */
    public function testSuppressesByExactRouteName(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('users.store', 'Legacy store endpoint.'),
        ]);

        self::assertTrue($allowlist->suppresses('users.store', 'users', 'R1'));
        self::assertFalse($allowlist->suppresses('users.index', 'users', 'R1'));
    }

    /**
     * Test that an entry matches by URI wildcard pattern.
     *
     * @return void
     */
    public function testSuppressesByUriWildcardPattern(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('users/*', 'All user sub-routes are exempted for migration.'),
        ]);

        self::assertTrue($allowlist->suppresses(null, 'users/create', 'R1'));
        self::assertTrue($allowlist->suppresses(null, 'users/profile', 'R2'));
        self::assertFalse($allowlist->suppresses(null, 'articles/create', 'R1'));
    }

    /**
     * Test that an entry matching no observed route is unmatched.
     *
     * @return void
     */
    public function testEntryMatchingNoObservedRouteIsUnmatched(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('users.store', 'Legacy store endpoint.'),
            new AllowlistEntry('articles.old', 'Deprecated article route.'),
        ]);

        $allowlist->observe('users.store', 'users');

        self::assertSame(['articles.old'], $allowlist->unmatched());
    }

    /**
     * Test that unmatched entries are sorted ascending.
     *
     * @return void
     */
    public function testUnmatchedIsSorted(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('users/*', 'User sub-routes.'),
            new AllowlistEntry('articles.old', 'Old article route.'),
            new AllowlistEntry('beta/feature', 'Beta feature.'),
        ]);

        self::assertSame(['articles.old', 'beta/feature', 'users/*'], $allowlist->unmatched());
    }

    /**
     * Test that an entry matching a live route but suppressing nothing is
     * unused.
     *
     * @return void
     */
    public function testMatchedEntryThatSuppressedNothingIsUnused(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('users.index', 'Waived for migration.', ['R1']),
        ]);

        $allowlist->observe('users.index', 'users');

        self::assertSame([], $allowlist->unmatched());
        self::assertCount(1, $allowlist->unused());
        self::assertStringContainsString('users.index', $allowlist->unused()[0]);
        self::assertStringContainsString('suppressed nothing', $allowlist->unused()[0]);
        self::assertStringContainsString('Waived for migration.', $allowlist->unused()[0]);
    }

    /**
     * Test that an entry that suppressed a violation is neither unused nor
     * unmatched.
     *
     * @return void
     */
    public function testUsedEntryIsNotReported(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('login', 'Legacy auth endpoint.', ['R1']),
        ]);

        $allowlist->observe(null, 'login');
        $allowlist->suppresses(null, 'login', 'R1');

        self::assertSame([], $allowlist->unmatched());
        self::assertSame([], $allowlist->unused());
    }

    /**
     * Test that an unused entry after a used one is still reported.
     *
     * @return void
     */
    public function testUnusedEntryAfterUsedEntryIsStillReported(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('login', 'Legacy auth endpoint.', ['R1']),
            new AllowlistEntry('users.index', 'Waived for migration.', ['R1']),
        ]);

        $allowlist->observe(null, 'login');
        $allowlist->suppresses(null, 'login', 'R1');
        $allowlist->observe('users.index', 'users');

        self::assertCount(1, $allowlist->unused());
        self::assertStringContainsString('users.index', $allowlist->unused()[0]);
    }

    /**
     * Test that a never-matched entry is unmatched, not unused.
     *
     * @return void
     */
    public function testNeverMatchedEntryIsNotReportedAsUnused(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('never.matched', 'Waiver that never matched a live route.'),
        ]);

        self::assertSame([], $allowlist->unused());
        self::assertSame(['never.matched'], $allowlist->unmatched());
    }

    /**
     * Test that unused entries are sorted ascending.
     *
     * @return void
     */
    public function testUnusedIsSorted(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('z-route', 'Last alphabetically.', ['R9']),
            new AllowlistEntry('a-route', 'First alphabetically.', ['R9']),
        ]);

        $allowlist->observe('z-route', 'z-route');
        $allowlist->observe('a-route', 'a-route');

        $unused = $allowlist->unused();

        self::assertCount(2, $unused);
        self::assertStringContainsString('a-route', $unused[0]);
        self::assertStringContainsString('z-route', $unused[1]);
    }

    /**
     * Test that observing a matching route marks the entry as matched.
     *
     * @return void
     */
    public function testObserveMarksEntryAsMatched(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('users.index', 'Should be matched.'),
        ]);

        $allowlist->observe('users.index', 'users');

        self::assertSame([], $allowlist->unmatched());
    }

    /**
     * Test that a matching entry after a non-matching one still suppresses.
     *
     * @return void
     */
    public function testSuppressesChecksEveryEntry(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('orders.index', 'First entry, non-matching.', ['R1']),
            new AllowlistEntry('users.store', 'Second entry, matching.', ['R1']),
        ]);

        self::assertTrue($allowlist->suppresses('users.store', 'users', 'R1'));
    }

    /**
     * Test that suppressing on a matching route marks the entry as matched.
     *
     * @return void
     */
    public function testSuppressesMarksEntryAsMatched(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('users.store', 'Legacy endpoint.', ['R1']),
        ]);

        $allowlist->suppresses('users.store', 'users', 'R1');

        self::assertSame([], $allowlist->unmatched());
    }

    /**
     * Test that suppressing a violation marks the entry as used.
     *
     * @return void
     */
    public function testSuppressesMarksEntryAsUsed(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('users.store', 'Legacy endpoint.', ['R1']),
        ]);

        self::assertTrue($allowlist->suppresses('users.store', 'users', 'R1'));
        self::assertSame([], $allowlist->unused());
    }

    /**
     * Test the exact format of an unused entry.
     *
     * @return void
     */
    public function testUnusedEntryHasExactFormat(): void
    {
        $allowlist = new ExemptionAllowlist([
            new AllowlistEntry('legacy.route', 'Migration period waiver.', ['R5']),
        ]);

        $allowlist->observe('legacy.route', 'legacy/route');

        self::assertSame(['legacy.route (suppressed nothing): Migration period waiver.'], $allowlist->unused());
    }
}
