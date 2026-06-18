<?php

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\RequiredMiddlewareRule;
use SineMacula\RouteLinter\Severity;
use Tests\TestCase;

/**
 * Tests for the RequiredMiddlewareRule (R10) warning rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(RequiredMiddlewareRule::class)]
class RequiredMiddlewareRuleTest extends TestCase
{
    /** @var \SineMacula\RouteLinter\Rules\RequiredMiddlewareRule */
    private RequiredMiddlewareRule $rule;

    /**
     * Set up shared fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = new RequiredMiddlewareRule;
    }

    /**
     * Test that an empty required-middleware config produces no violation.
     *
     * @return void
     */
    public function testEmptyConfigProducesNothing(): void
    {
        // Act
        $violations = $this->rule->inspect($this->route('admin/users', ['auth']), $this->config([]));

        // Assert
        static::assertEmpty($violations);
    }

    /**
     * Test that a route matching a pattern and declaring the required middleware
     * is not flagged.
     *
     * @return void
     */
    public function testPresentMiddlewareIsNotFlagged(): void
    {
        // Arrange
        $config = $this->config(['admin/*' => ['auth']]);

        // Act
        $violations = $this->rule->inspect($this->route('admin/users', ['auth']), $config);

        // Assert
        static::assertEmpty($violations);
    }

    /**
     * Test that a route matching a pattern but missing the required middleware
     * is flagged with a remediation hint.
     *
     * @return void
     */
    public function testMissingMiddlewareIsFlagged(): void
    {
        // Arrange
        $config = $this->config(['admin/*' => ['auth']]);

        // Act
        $violations = $this->rule->inspect($this->route('admin/users', ['throttle']), $config);

        // Assert
        static::assertCount(1, $violations);
        static::assertSame('R10', $violations[0]->ruleId);
        static::assertSame(Severity::WARNING, $violations[0]->severity);
        static::assertSame('GET admin/users admin.users', $violations[0]->routeIdentity);
        static::assertSame('auth', $violations[0]->offendingSurface);
        static::assertSame('add the `auth` middleware (route matches `admin/*`)', $violations[0]->remediationHint);
    }

    /**
     * Test that a route not matching any pattern is not flagged even when it
     * lacks the middleware.
     *
     * Kills the mutant negating the fnmatch guard: a non-matching route must be
     * skipped.
     *
     * @return void
     */
    public function testNonMatchingRouteIsNotFlagged(): void
    {
        // Arrange - the route is under api/, not admin/
        $config = $this->config(['admin/*' => ['auth']]);

        // Act
        $violations = $this->rule->inspect($this->route('api/users', []), $config);

        // Assert
        static::assertEmpty($violations);
    }

    /**
     * Test that every missing middleware in a matching pattern is flagged.
     *
     * @return void
     */
    public function testEveryMissingMiddlewareIsFlagged(): void
    {
        // Arrange - two required, only one present
        $config = $this->config(['admin/*' => ['auth', 'can:access-admin']]);

        // Act
        $violations = $this->rule->inspect($this->route('admin/users', ['auth']), $config);

        // Assert - only the absent `can:access-admin` is flagged
        static::assertCount(1, $violations);
        static::assertSame('can:access-admin', $violations[0]->offendingSurface);
    }

    /**
     * Test that violations accumulate across every matching pattern.
     *
     * @return void
     */
    public function testViolationsAccumulateAcrossPatterns(): void
    {
        // Arrange - the route matches both patterns and declares neither
        $config = $this->config([
            'admin/*'       => ['auth'],
            'admin/users/*' => ['can:manage-users'],
        ]);

        // Act
        $violations = $this->rule->inspect($this->route('admin/users/5', []), $config);

        // Assert
        static::assertCount(2, $violations);
        static::assertSame('auth', $violations[0]->offendingSurface);
        static::assertSame('can:manage-users', $violations[1]->offendingSurface);
    }

    /**
     * Test that a pattern matched only after an earlier non-matching pattern is
     * still evaluated.
     *
     * Kills the mutant turning the non-match `continue` into a `break`: a later
     * matching pattern must not be skipped because an earlier one missed.
     *
     * @return void
     */
    public function testLaterMatchingPatternAfterNonMatchIsFlagged(): void
    {
        // Arrange - the route matches the second pattern but not the first
        $config = $this->config([
            'api/*'   => ['throttle'],
            'admin/*' => ['auth'],
        ]);

        // Act
        $violations = $this->rule->inspect($this->route('admin/users', []), $config);

        // Assert - the second pattern still produced its violation
        static::assertCount(1, $violations);
        static::assertSame('auth', $violations[0]->offendingSurface);
    }

    /**
     * Test that rule id() returns 'R10' and severity() returns Severity::WARNING.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        static::assertSame('R10', $this->rule->id());
        static::assertSame(Severity::WARNING, $this->rule->severity());
    }

    /**
     * Build a normalised GET route from a URI and middleware list.
     *
     * @param  string  $uri
     * @param  array<int, string>  $middleware
     * @return \SineMacula\RouteLinter\NormalisedRoute
     */
    private function route(string $uri, array $middleware): NormalisedRoute
    {
        return new NormalisedRoute(
            uri: $uri,
            methods: ['GET'],
            name: str_replace('/', '.', $uri),
            segments: explode('/', $uri),
            parameters: [],
            middleware: $middleware,
        );
    }

    /**
     * Build a rule config carrying the given required-middleware map.
     *
     * @param  array<string, array<int, string>>  $requiredMiddleware
     * @return \SineMacula\RouteLinter\Dto\RuleConfig
     */
    private function config(array $requiredMiddleware): RuleConfig
    {
        return new RuleConfig([], [], [], [], 3, $requiredMiddleware);
    }
}
