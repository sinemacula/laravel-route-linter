<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\LowercaseRule;
use Tests\TestCase;

/**
 * Tests for the LowercaseRule (R3) error rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(LowercaseRule::class)]
final class LowercaseRuleTest extends TestCase
{
    /** @var \SineMacula\RouteLinter\Rules\LowercaseRule */
    private LowercaseRule $rule;

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

        $this->rule   = new LowercaseRule;
        $this->config = new RuleConfig([], [], [], []);
    }

    /**
     * Test that a segment containing an uppercase letter produces one R3 error
     * violation.
     *
     * @return void
     */
    public function testUppercaseSegmentIsFlagged(): void
    {
        // Arrange
        $route = new NormalisedRoute(
            uri: 'Users',
            methods: ['GET'],
            name: null,
            segments: ['Users'],
            parameters: [],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert
        self::assertCount(1, $violations);
        self::assertSame('R3', $violations[0]->ruleId);
        self::assertSame(Severity::ERROR, $violations[0]->severity);
        self::assertSame('Users', $violations[0]->offendingSurface);
        self::assertNull($violations[0]->remediationHint);
    }

    /**
     * Test that an all-lowercase segment produces no R3 violation.
     *
     * @return void
     */
    public function testLowercaseSegmentIsNotFlagged(): void
    {
        // Arrange
        $route = new NormalisedRoute(
            uri: 'users',
            methods: ['GET'],
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
     * Test that route-parameter segments wrapped in braces are ignored.
     *
     * @return void
     */
    public function testParameterSegmentsAreIgnored(): void
    {
        // Arrange - the sole segment is a route parameter
        $route = new NormalisedRoute(
            uri: '{User}',
            methods: ['GET'],
            name: null,
            segments: ['{User}'],
            parameters: ['User'],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert
        self::assertEmpty($violations);
    }

    /**
     * Test that a camelCase segment (mixed case) is flagged as a violation.
     *
     * @return void
     */
    public function testCamelCaseSegmentIsFlagged(): void
    {
        // Arrange - userProfiles contains uppercase letters; both R2 and R3
        // fire independently
        $route = new NormalisedRoute(
            uri: 'userProfiles',
            methods: ['GET'],
            name: null,
            segments: ['userProfiles'],
            parameters: [],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert - R3 produces one violation for the mixed-case segment
        self::assertCount(1, $violations);
        self::assertSame('R3', $violations[0]->ruleId);
        self::assertSame('userProfiles', $violations[0]->offendingSurface);
    }

    /**
     * Test that empty segments are ignored and do not produce violations.
     *
     * @return void
     */
    public function testEmptySegmentsAreIgnored(): void
    {
        // Arrange - trailing slash produces an empty trailing segment
        $route = new NormalisedRoute(
            uri: 'users/',
            methods: ['GET'],
            name: null,
            segments: ['users', ''],
            parameters: [],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert
        self::assertEmpty($violations);
    }

    /**
     * Test that a route-parameter segment preceding an uppercase literal still
     * produces a violation.
     *
     * Kills the Continue_->break mutant (#44): if the loop breaks instead of
     * continuing on a param/empty segment, any literal that follows is never
     * reached. With a param first then an uppercase literal, the original
     * produces a violation while the mutant produces none.
     *
     * @return void
     */
    public function testUppercaseSegmentAfterParameterIsFlagged(): void
    {
        // Arrange - '{user}' is first; 'Users' follows and must still be
        // inspected
        $route = new NormalisedRoute(
            uri: '{user}/Users',
            methods: ['GET'],
            name: null,
            segments: ['{user}', 'Users'],
            parameters: ['user'],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert - 'Users' is uppercase and must be flagged despite the leading
        // param
        self::assertCount(1, $violations);
        self::assertSame('R3', $violations[0]->ruleId);
        self::assertSame(Severity::ERROR, $violations[0]->severity);
        self::assertSame('Users', $violations[0]->offendingSurface);
    }

    /**
     * Test that an uppercase segment after a valid lowercase segment is still
     * flagged.
     *
     * Kills the Continue_ mutant on the lowercase-match skip: with `break`
     * instead of `continue`, a later offending segment is never inspected.
     *
     * @return void
     */
    public function testUppercaseSegmentAfterValidSegmentIsFlagged(): void
    {
        // Arrange - 'users' is lowercase and skipped; 'Posts' follows and must
        // still be inspected
        $route = new NormalisedRoute(
            uri: 'users/Posts',
            methods: ['GET'],
            name: null,
            segments: ['users', 'Posts'],
            parameters: [],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert - 'Posts' is uppercase and must be flagged despite the valid
        // leader
        self::assertCount(1, $violations);
        self::assertSame('R3', $violations[0]->ruleId);
        self::assertSame(Severity::ERROR, $violations[0]->severity);
        self::assertSame('Posts', $violations[0]->offendingSurface);
    }

    /**
     * Test that two uppercase segments each produce their own R3 violation.
     *
     * Kills the ArrayOneItem mutant (#45): when more than one violation is
     * produced, the mutant returns only the first element. Asserting count = 2
     * and both offendingSurface values ensures the complete violations array is
     * returned.
     *
     * @return void
     */
    public function testTwoUppercaseSegmentsProduceTwoViolations(): void
    {
        // Arrange - both 'Users' and 'Posts' contain uppercase letters
        $route = new NormalisedRoute(
            uri: 'Users/Posts',
            methods: ['GET'],
            name: null,
            segments: ['Users', 'Posts'],
            parameters: [],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert - two violations, one per offending segment, in segment order
        self::assertCount(2, $violations);
        self::assertSame('R3', $violations[0]->ruleId);
        self::assertSame('Users', $violations[0]->offendingSurface);
        self::assertSame('R3', $violations[1]->ruleId);
        self::assertSame('Posts', $violations[1]->offendingSurface);
    }

    /**
     * Test that rule id() returns 'R3' and severity() returns Severity::ERROR.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        self::assertSame('R3', $this->rule->id());
        self::assertSame(Severity::ERROR, $this->rule->severity());
    }
}
