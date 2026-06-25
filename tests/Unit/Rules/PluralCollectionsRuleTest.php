<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Contracts\Inflector;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\PluralCollectionsRule;
use Tests\TestCase;

/**
 * Tests for the PluralCollectionsRule (R4) error rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(PluralCollectionsRule::class)]
final class PluralCollectionsRuleTest extends TestCase
{
    /** @var \SineMacula\RouteLinter\Rules\PluralCollectionsRule */
    private PluralCollectionsRule $rule;

    /**
     * Set up shared fixtures.
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = new PluralCollectionsRule($this->makeInflector());
    }

    /**
     * Test that a singular collection segment preceding a parameter is flagged.
     *
     * @return void
     */
    public function testSingularCollectionIsFlagged(): void
    {
        // Arrange - `user` precedes `{user}` so it is a collection segment
        $route = new NormalisedRoute(
            uri: 'user/{user}',
            methods: ['GET'],
            name: null,
            segments: ['user', '{user}'],
            parameters: ['user'],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert
        self::assertCount(1, $violations);
        self::assertSame('R4', $violations[0]->ruleId);
        self::assertSame(Severity::ERROR, $violations[0]->severity);
        self::assertSame('user', $violations[0]->offendingSurface);
        self::assertNull($violations[0]->remediationHint);
    }

    /**
     * Test that a plural collection segment preceding a parameter is not
     * flagged.
     *
     * @return void
     */
    public function testPluralCollectionIsNotFlagged(): void
    {
        // Arrange - `users` is already plural
        $route = new NormalisedRoute(
            uri: 'users/{user}',
            methods: ['GET'],
            name: null,
            segments: ['users', '{user}'],
            parameters: ['user'],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert
        self::assertEmpty($violations);
    }

    /**
     * Test that a configured uncountable segment is never flagged even when
     * singular.
     *
     * @return void
     */
    public function testUncountableSegmentIsNotFlagged(): void
    {
        // Arrange - `media` is in the uncountables list; the fake inflector
        // would return false for isPlural('media'), but the
        // uncountable bypass fires first
        $route = new NormalisedRoute(
            uri: 'media/{item}',
            methods: ['GET'],
            name: null,
            segments: ['media', '{item}'],
            parameters: ['item'],
        );

        $config = new RuleConfig([], [], [], ['media']);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert
        self::assertEmpty($violations);
    }

    /**
     * Test that a standalone top-level singular segment (no following param) is
     * flagged.
     *
     * @return void
     */
    public function testTopLevelSingularCollectionIsFlagged(): void
    {
        // Arrange - `user` is the final literal segment with no following param
        $route = new NormalisedRoute(
            uri: 'user',
            methods: ['GET'],
            name: null,
            segments: ['user'],
            parameters: [],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert
        self::assertCount(1, $violations);
        self::assertSame('user', $violations[0]->offendingSurface);
    }

    /**
     * Test that a standalone top-level plural segment produces no violation.
     *
     * @return void
     */
    public function testTopLevelPluralCollectionIsNotFlagged(): void
    {
        // Arrange
        $route = new NormalisedRoute(
            uri: 'users',
            methods: ['GET'],
            name: null,
            segments: ['users'],
            parameters: [],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert
        self::assertEmpty($violations);
    }

    /**
     * Test that route parameter segments are never evaluated as collection
     * segments.
     *
     * @return void
     */
    public function testParameterSegmentsAreIgnored(): void
    {
        // Arrange - a lone parameter with no preceding literal
        $route = new NormalisedRoute(
            uri: '{user}',
            methods: ['GET'],
            name: null,
            segments: ['{user}'],
            parameters: ['user'],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert
        self::assertEmpty($violations);
    }

    /**
     * Test that an empty segment before a singular collection is skipped (not
     * breaking the loop) so the singular collection still produces a violation.
     *
     * @return void
     */
    public function testEmptySegmentBeforeCollectionDoesNotBreakLoop(): void
    {
        // Arrange - leading empty string from a double-slash URI;
        // `user` must still be detected
        $route = new NormalisedRoute(
            uri: '/user/{user}',
            methods: ['GET'],
            name: null,
            segments: ['', 'user', '{user}'],
            parameters: ['user'],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert - empty segment at index 0 must be skipped, not break the loop
        self::assertCount(1, $violations);
        self::assertSame('user', $violations[0]->offendingSurface);
    }

    /**
     * Test that an uncountable collection segment before a singular one does
     * not break the loop - the singular segment after must still be flagged.
     *
     * @return void
     */
    public function testUncountableCollectionBeforeSingularCollectionDoesNotBreakLoop(): void
    {
        // Arrange - `media` is uncountable (at index 0, followed by
        // `{item}` so it IS a collection), `user` is the final literal
        // segment after `{item}` and is singular
        $route = new NormalisedRoute(
            uri: 'media/{item}/user',
            methods: ['GET'],
            name: null,
            segments: ['media', '{item}', 'user'],
            parameters: ['item'],
        );

        $config = new RuleConfig([], [], [], ['media']);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert - `media` is skipped via uncountable bypass (not
        // broken out of loop), `user` is the remaining singular
        // collection and must be flagged
        self::assertCount(1, $violations);
        self::assertSame('user', $violations[0]->offendingSurface);
    }

    /**
     * Test that a plural collection segment before a singular one does not
     * break the loop - the singular segment after must still be flagged.
     *
     * @return void
     */
    public function testPluralCollectionBeforeSingularCollectionDoesNotBreakLoop(): void
    {
        // Arrange - `users` is plural (skipped), `comment` is a
        // singular final literal
        $route = new NormalisedRoute(
            uri: 'users/{user}/comment',
            methods: ['GET'],
            name: null,
            segments: ['users', '{user}', 'comment'],
            parameters: ['user'],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert - `users` plural short-circuit must continue, not
        // break; `comment` is flagged
        self::assertCount(1, $violations);
        self::assertSame('comment', $violations[0]->offendingSurface);
    }

    /**
     * Test that multiple singular collection segments each produce a violation
     * so the full array is returned (not truncated to the first entry).
     *
     * @return void
     */
    public function testMultipleSingularCollectionsEachProduceViolation(): void
    {
        // Arrange - `user` precedes `{user}`, `comment` precedes
        // `{comment}`; both are singular
        $route = new NormalisedRoute(
            uri: 'user/{user}/comment/{comment}',
            methods: ['GET'],
            name: null,
            segments: ['user', '{user}', 'comment', '{comment}'],
            parameters: ['user', 'comment'],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert - both collections must be reported; verifies the
        // full array is returned
        self::assertCount(2, $violations);

        $surfaces = array_map(fn ($v) => $v->offendingSurface, $violations);

        self::assertContains('user', $surfaces);
        self::assertContains('comment', $surfaces);
    }

    /**
     * Test that a non-collection literal segment followed by another literal
     * segment is not misidentified as a collection when using index arithmetic.
     *
     * Targets the isCollectionSegment() index + 1 calculation: with +0 the
     * segment at 'user' in ['user', '{user}', 'comment'] would lose its
     * collection status because it would look at itself instead of '{user}'.
     *
     * @return void
     */
    public function testCollectionDetectionUsesNextSegmentIndex(): void
    {
        // Arrange - `user` is a collection (next is `{user}`); `comment` is the
        // final literal
        $route = new NormalisedRoute(
            uri: 'user/{user}/comment',
            methods: ['GET'],
            name: null,
            segments: ['user', '{user}', 'comment'],
            parameters: ['user'],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert - both `user` (next is param) and `comment` (final
        // literal) must be violations
        self::assertCount(2, $violations);

        $surfaces = array_map(fn ($v) => $v->offendingSurface, $violations);

        self::assertContains('user', $surfaces);
        self::assertContains('comment', $surfaces);
    }

    /**
     * Test that a non-terminal literal segment that has another literal after
     * it is NOT treated as a collection - targeting the for-loop range check.
     *
     * @return void
     */
    public function testNonTerminalLiteralWithLiteralAfterIsNotCollection(): void
    {
        // Arrange - `user` has `page` as the next segment (literal, not param),
        // then `{user}` follows; `user` must NOT be detected as a collection
        $route = new NormalisedRoute(
            uri: 'user/page/{user}',
            methods: ['GET'],
            name: null,
            segments: ['user', 'page', '{user}'],
            parameters: ['user'],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert - only `page` (immediately before `{user}`) is a
        // collection and is flagged; `user` must not be flagged (it
        // has a literal between itself and the parameter)
        self::assertCount(1, $violations);
        self::assertSame('page', $violations[0]->offendingSurface);
    }

    /**
     * Test that a literal segment preceded by a route parameter is not
     * misidentified as a collection via backward index arithmetic.
     *
     * Specifically: with index - 1 instead of index + 1 as the "next" lookup, a
     * segment at index 1 preceded by a route parameter at index 0 would
     * incorrectly be flagged as a collection.
     *
     * @return void
     */
    public function testSegmentPrecededByParameterIsNotCollection(): void
    {
        // Arrange - `{id}` at index 0, `user` at index 1, `extra` at index 2
        // `user` has a literal after it so it must NOT be a collection
        $route = new NormalisedRoute(
            uri: '{id}/user/extra',
            methods: ['GET'],
            name: null,
            segments: ['{id}', 'user', 'extra'],
            parameters: ['id'],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert - `extra` is the final literal and IS a collection
        // (singular, so a violation); `user` has `extra` (literal)
        // after it and is NOT a collection
        self::assertCount(1, $violations);
        self::assertSame('extra', $violations[0]->offendingSurface);
    }

    /**
     * Test that a literal segment followed only by another literal (not a
     * parameter) does not become a collection via short-circuit on `!== null`.
     *
     * @return void
     */
    public function testLiteralFollowedByLiteralIsNotCollection(): void
    {
        // Arrange - `user` at index 0, `users` at index 1; neither
        // is followed by a param. `user` (index 0) has `users`
        // (literal) after it so the for-loop finds a literal and does
        // not treat it as a collection. `users` (index 1) is the
        // final literal and IS a collection but is plural, so no
        // violation.
        $route = new NormalisedRoute(
            uri: 'user/users',
            methods: ['GET'],
            name: null,
            segments: ['user', 'users'],
            parameters: [],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert - `user` must not be misidentified as a collection
        // just because next is !== null
        self::assertEmpty($violations);
    }

    /**
     * Test that a singular final-literal segment followed only by an empty
     * segment (trailing slash) is still flagged.
     *
     * Kills the `&&` → `||` mutant in the final-literal-segment loop: under the
     * mutant the loop returns early when the trailing empty segment satisfies
     * the OR branch, so no violation is emitted. Under correct code both
     * conditions
     * (`=== ''` and `str_starts_with('{')`) must hold for the loop to continue,
     * and the singular `comment` is correctly flagged.
     *
     * @return void
     */
    public function testSingularFinalLiteralBeforeTrailingSlashIsFlagged(): void
    {
        // Arrange - URI `comment/` produces segments ['comment', '']; the fake
        // inflector treats `comment` as singular (does not end with 's')
        $route = new NormalisedRoute(
            uri: 'comment/',
            methods: ['GET'],
            name: null,
            segments: ['comment', ''],
            parameters: [],
        );

        $config = new RuleConfig([], [], [], []);

        // Act
        $violations = $this->rule->inspect($route, $config);

        // Assert - exactly one R4 violation for the singular segment 'comment'
        self::assertCount(1, $violations);
        self::assertSame('R4', $violations[0]->ruleId);
        self::assertSame('comment', $violations[0]->offendingSurface);
    }

    /**
     * Test that rule id() returns 'R4' and severity() returns Severity::ERROR.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        self::assertSame('R4', $this->rule->id());
        self::assertSame(Severity::ERROR, $this->rule->severity());
    }

    /**
     * Build a fake Inflector that treats words ending in 's' as plural and
     * everything else as singular. This is sufficient for fixture segments.
     *
     * @return \SineMacula\RouteLinter\Contracts\Inflector
     */
    private function makeInflector(): Inflector
    {
        return new class implements Inflector {
            /**
             * Return the singular form of a value.
             *
             * @param  string  $value
             * @return string
             */
            #[\Override]
            public function singular(string $value): string
            {
                return rtrim($value, 's');
            }

            /**
             * Determine whether the value is plural.
             *
             * @param  string  $value
             * @return bool
             */
            #[\Override]
            public function isPlural(string $value): bool
            {
                return str_ends_with($value, 's');
            }
        };
    }
}
