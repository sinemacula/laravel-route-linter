<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\DuplicateRouteNameRule;
use SineMacula\RouteLinter\Violation;
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
final class DuplicateRouteNameRuleTest extends TestCase
{
    /**
     * Provide route tables of URI and name with the routes R6 flags.
     *
     * @return iterable<string, array{list<array{string, string|null}>, list<array{string, string}>}>
     */
    public static function provideRouteTables(): iterable
    {
        yield 'distinct names' => [[['users', 'users.index'], ['orders', 'orders.index']], []];
        yield 'unnamed routes' => [[['users', null], ['orders', null]], []];
        yield 'one duplicate' => [[['users', 'users.index'], ['people', 'users.index']], [['people', 'users.index']]];
        yield 'two duplicates' => [[['a', 'shared.name'], ['b', 'shared.name'], ['c', 'shared.name']], [['b', 'shared.name'], ['c', 'shared.name']]];
        yield 'unnamed route between duplicates' => [[['a', 'shared.name'], ['b', null], ['c', 'shared.name']], [['c', 'shared.name']]];
    }

    /**
     * Test that every registration after the first of a name is flagged, and
     * unnamed routes never are.
     *
     * @param  list<array{string, string|null}>  $routes
     * @param  list<array{string, string}>  $flagged
     * @return void
     */
    #[DataProvider('provideRouteTables')]
    public function testFlagsEachLaterRegistrationOfAName(array $routes, array $flagged): void
    {
        $normalised = array_map(
            static fn (array $route): NormalisedRoute => new NormalisedRoute($route[0], ['GET'], $route[1], explode('/', $route[0]), []),
            $routes,
        );

        $violations = (new DuplicateRouteNameRule)->inspect($normalised, new RuleConfig([], [], [], []));

        self::assertSame(
            array_map(static fn (array $route): array => ['R6', Severity::ERROR, 'GET ' . $route[0] . ' ' . $route[1], $route[1], null], $flagged),
            array_map(
                static fn (Violation $v): array => [$v->ruleId, $v->severity, $v->routeIdentity, $v->offendingSurface, $v->remediationHint],
                $violations,
            ),
        );
    }

    /**
     * Test that the rule reports as R6 at error severity.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        $rule = new DuplicateRouteNameRule;

        self::assertSame('R6', $rule->id());
        self::assertSame(Severity::ERROR, $rule->severity());
    }
}
