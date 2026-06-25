<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\StandardMethodsRule;
use Tests\TestCase;

/**
 * Tests for the StandardMethodsRule (R7) error rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(StandardMethodsRule::class)]
final class StandardMethodsRuleTest extends TestCase
{
    /** @var \SineMacula\RouteLinter\Rules\StandardMethodsRule */
    private StandardMethodsRule $rule;

    /** @var \SineMacula\RouteLinter\Dto\RuleConfig */
    private RuleConfig $config;

    /**
     * Set up shared fixtures.
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->rule   = new StandardMethodsRule;
        $this->config = new RuleConfig([], [], [], []);
    }

    /**
     * Test that a route whose methods include a non-standard verb produces one
     * R7 error violation.
     *
     * @return void
     */
    public function testNonStandardMethodIsFlagged(): void
    {
        // Arrange - PURGE is not in the standard set
        $route = new NormalisedRoute(
            uri: 'users',
            methods: ['PURGE'],
            name: null,
            segments: ['users'],
            parameters: [],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert
        self::assertCount(1, $violations);
        self::assertSame('R7', $violations[0]->ruleId);
        self::assertSame(Severity::ERROR, $violations[0]->severity);
        self::assertSame('PURGE', $violations[0]->offendingSurface);
        self::assertNull($violations[0]->remediationHint);
    }

    /**
     * Test that a route with only standard methods produces no R7 violation.
     *
     * @return void
     */
    public function testStandardMethodsAreNotFlagged(): void
    {
        // Arrange - GET and HEAD are both in the standard set
        $route = new NormalisedRoute(
            uri: 'users',
            methods: ['GET', 'HEAD'],
            name: null,
            segments: ['users'],
            parameters: [],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert
        self::assertEmpty($violations);
    }

    /**
     * Test that multiple non-standard methods produce a single R7 violation
     * whose offending surface is the methods sorted alphabetically and joined
     * by ', '.
     *
     * Kills the FunctionCallRemoval mutant (#72) that removes the sort() call:
     * without sorting the non-standard methods are joined in input order, so
     * the offendingSurface would be 'ZZZ, AAA' instead of the expected 'AAA,
     * ZZZ'.
     *
     * @return void
     */
    public function testMultipleNonStandardMethodsAreSortedInOffendingSurface(): void
    {
        // Arrange - ZZZ and AAA are both non-standard; input order is ZZZ first
        $route = new NormalisedRoute(
            uri: 'users',
            methods: ['ZZZ', 'GET', 'AAA'],
            name: null,
            segments: ['users'],
            parameters: [],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert - one violation; non-standard methods must appear sorted
        self::assertCount(1, $violations);
        self::assertSame('R7', $violations[0]->ruleId);
        self::assertSame(Severity::ERROR, $violations[0]->severity);
        self::assertSame('AAA, ZZZ', $violations[0]->offendingSurface);
        self::assertNull($violations[0]->remediationHint);
    }

    /**
     * Test that a route with a non-standard method mixed in with standard ones
     * is flagged, and that only the non-standard method appears in the
     * offending surface.
     *
     * Kills the UnwrapArrayValues mutant (#71) by asserting the exact
     * offendingSurface string - if array_values were removed the key ordering
     * could differ under sort(), but since sort() re-indexes anyway the
     * observable difference is confirmed absent, making this an equivalent
     * mutant (see note below).
     *
     * @return void
     */
    public function testOnlyNonStandardMethodAppearsInOffendingSurface(): void
    {
        // Arrange - only FOO is non-standard; GET is standard and must be
        // excluded
        $route = new NormalisedRoute(
            uri: 'users',
            methods: ['GET', 'FOO'],
            name: null,
            segments: ['users'],
            parameters: [],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert - exactly one non-standard method; offendingSurface is 'FOO'
        // alone
        self::assertCount(1, $violations);
        self::assertSame('FOO', $violations[0]->offendingSurface);
    }

    /**
     * Test that rule id() returns 'R7' and severity() returns Severity::ERROR.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        self::assertSame('R7', $this->rule->id());
        self::assertSame(Severity::ERROR, $this->rule->severity());
    }
}
