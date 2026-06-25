<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\RouteHandlerExistsRule;
use Tests\Fixtures\Controllers\RouteLintController;
use Tests\Fixtures\Rules\ParameterEchoRule;
use Tests\TestCase;

/**
 * Tests for the RouteHandlerExistsRule (R12) error rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(RouteHandlerExistsRule::class)]
final class RouteHandlerExistsRuleTest extends TestCase
{
    /** @var \SineMacula\RouteLinter\Rules\RouteHandlerExistsRule */
    private RouteHandlerExistsRule $rule;

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

        $this->rule   = new RouteHandlerExistsRule;
        $this->config = new RuleConfig([], [], [], []);
    }

    /**
     * Test that a closure route (null handler) is skipped.
     *
     * @return void
     */
    public function testClosureRouteIsSkipped(): void
    {
        // Act
        $violations = $this->rule->inspect($this->route(null), $this->config);

        // Assert
        self::assertEmpty($violations);
    }

    /**
     * Test that a `Class@method` handler whose method exists is not flagged.
     *
     * @return void
     */
    public function testExistingClassMethodIsNotFlagged(): void
    {
        // Act
        $violations = $this->rule->inspect($this->route(RouteLintController::class . '@index'), $this->config);

        // Assert
        self::assertEmpty($violations);
    }

    /**
     * Test that a `Class@method` handler whose method is missing is flagged.
     *
     * Kills the mutant forcing method_exists() true: with the method absent the
     * rule must still emit a violation.
     *
     * @return void
     */
    public function testMissingMethodIsFlagged(): void
    {
        // Arrange
        $handler = RouteLintController::class . '@doesNotExist';

        // Act
        $violations = $this->rule->inspect($this->route($handler), $this->config);

        // Assert
        self::assertCount(1, $violations);
        self::assertSame('R12', $violations[0]->ruleId);
        self::assertSame(Severity::ERROR, $violations[0]->severity);
        self::assertSame('GET users users.index', $violations[0]->routeIdentity);
        self::assertSame($handler, $violations[0]->offendingSurface);
        self::assertNull($violations[0]->remediationHint);
    }

    /**
     * Test that a handler whose class does not exist is flagged.
     *
     * @return void
     */
    public function testMissingClassIsFlagged(): void
    {
        // Arrange
        $handler = 'Tests\Fixtures\Controllers\NoSuchController@index';

        // Act
        $violations = $this->rule->inspect($this->route($handler), $this->config);

        // Assert
        self::assertCount(1, $violations);
        self::assertSame($handler, $violations[0]->offendingSurface);
    }

    /**
     * Test that a bare invokable class with an `__invoke` method is not
     * flagged.
     *
     * Kills the literal mutant replacing the `__invoke` default: an invokable
     * controller must resolve against `__invoke` and pass.
     *
     * @return void
     */
    public function testInvokableControllerIsNotFlagged(): void
    {
        // Act - no `@method`, so the rule defaults to `__invoke`
        $violations = $this->rule->inspect($this->route(RouteLintController::class), $this->config);

        // Assert
        self::assertEmpty($violations);
    }

    /**
     * Test that a bare class with no `__invoke` method is flagged.
     *
     * @return void
     */
    public function testBareClassWithoutInvokeIsFlagged(): void
    {
        // Arrange - ParameterEchoRule implements Rule, not an invokable
        $handler = ParameterEchoRule::class;

        // Act
        $violations = $this->rule->inspect($this->route($handler), $this->config);

        // Assert
        self::assertCount(1, $violations);
        self::assertSame($handler, $violations[0]->offendingSurface);
    }

    /**
     * Test that rule id() returns 'R12' and severity() returns Severity::ERROR.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        self::assertSame('R12', $this->rule->id());
        self::assertSame(Severity::ERROR, $this->rule->severity());
    }

    /**
     * Build a normalised route with the given handler.
     *
     * @param  string|null  $handler
     * @return \SineMacula\RouteLinter\NormalisedRoute
     */
    private function route(?string $handler): NormalisedRoute
    {
        return new NormalisedRoute(
            uri: 'users',
            methods: ['GET'],
            name: 'users.index',
            segments: ['users'],
            parameters: [],
            handler: $handler,
        );
    }
}
