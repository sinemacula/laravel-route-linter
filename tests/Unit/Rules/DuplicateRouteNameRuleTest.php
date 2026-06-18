<?php

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\DuplicateRouteNameRule;
use SineMacula\RouteLinter\Severity;
use Tests\TestCase;

/**
 * Tests for the DuplicateRouteNameRule (R6) aggregate error rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(DuplicateRouteNameRule::class)]
class DuplicateRouteNameRuleTest extends TestCase
{
    /** @var \SineMacula\RouteLinter\Rules\DuplicateRouteNameRule */
    private DuplicateRouteNameRule $rule;

    /** @var \SineMacula\RouteLinter\Dto\RuleConfig */
    private RuleConfig $config;

    /**
     * Set up shared fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->rule   = new DuplicateRouteNameRule;
        $this->config = new RuleConfig([], [], [], []);
    }

    /**
     * Test that distinct route names produce no violation.
     *
     * @return void
     */
    public function testDistinctNamesProduceNothing(): void
    {
        // Arrange
        $routes = [
            $this->route('users', 'users.index'),
            $this->route('orders', 'orders.index'),
        ];

        // Act
        $violations = $this->rule->inspect($routes, $this->config);

        // Assert
        static::assertEmpty($violations);
    }

    /**
     * Test that unnamed routes are ignored entirely.
     *
     * Kills the mutant removing the null-name guard: two unnamed routes must not
     * collide on a null name.
     *
     * @return void
     */
    public function testUnnamedRoutesAreIgnored(): void
    {
        // Arrange - two unnamed routes
        $routes = [
            $this->route('users', null),
            $this->route('orders', null),
        ];

        // Act
        $violations = $this->rule->inspect($routes, $this->config);

        // Assert
        static::assertEmpty($violations);
    }

    /**
     * Test that a reused name flags the second (duplicate) occurrence.
     *
     * @return void
     */
    public function testDuplicateNameFlagsTheSecondOccurrence(): void
    {
        // Arrange - two routes share the name `users.index`
        $routes = [
            $this->route('users', 'users.index'),
            $this->route('people', 'users.index'),
        ];

        // Act
        $violations = $this->rule->inspect($routes, $this->config);

        // Assert - attributed to the second registration
        static::assertCount(1, $violations);
        static::assertSame('R6', $violations[0]->ruleId);
        static::assertSame(Severity::ERROR, $violations[0]->severity);
        static::assertSame('GET people users.index', $violations[0]->routeIdentity);
        static::assertSame('users.index', $violations[0]->offendingSurface);
        static::assertNull($violations[0]->remediationHint);
    }

    /**
     * Test that three routes sharing a name flag the second and third in order.
     *
     * @return void
     */
    public function testThreeDuplicatesFlagAllButTheFirst(): void
    {
        // Arrange
        $routes = [
            $this->route('a', 'shared.name'),
            $this->route('b', 'shared.name'),
            $this->route('c', 'shared.name'),
        ];

        // Act
        $violations = $this->rule->inspect($routes, $this->config);

        // Assert
        static::assertCount(2, $violations);
        static::assertSame('GET b shared.name', $violations[0]->routeIdentity);
        static::assertSame('GET c shared.name', $violations[1]->routeIdentity);
    }

    /**
     * Test that an unnamed route between two duplicates does not halt detection.
     *
     * Kills the mutant turning the null-name `continue` into a `break`: the
     * duplicate registered after the unnamed route must still be flagged.
     *
     * @return void
     */
    public function testUnnamedRouteBetweenDuplicatesDoesNotStopDetection(): void
    {
        // Arrange - an unnamed route sits between the canonical and the duplicate
        $routes = [
            $this->route('a', 'shared.name'),
            $this->route('b', null),
            $this->route('c', 'shared.name'),
        ];

        // Act
        $violations = $this->rule->inspect($routes, $this->config);

        // Assert
        static::assertCount(1, $violations);
        static::assertSame('GET c shared.name', $violations[0]->routeIdentity);
    }

    /**
     * Test that rule id() returns 'R6' and severity() returns Severity::ERROR.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        static::assertSame('R6', $this->rule->id());
        static::assertSame(Severity::ERROR, $this->rule->severity());
    }

    /**
     * Build a normalised GET route from a URI and name.
     *
     * @param  string  $uri
     * @param  string|null  $name
     * @return \SineMacula\RouteLinter\NormalisedRoute
     */
    private function route(string $uri, ?string $name): NormalisedRoute
    {
        return new NormalisedRoute(
            uri: $uri,
            methods: ['GET'],
            name: $name,
            segments: explode('/', $uri),
            parameters: [],
        );
    }
}
