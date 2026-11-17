<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\RequiredMiddlewareRule;
use SineMacula\RouteLinter\Violation;
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
final class RequiredMiddlewareRuleTest extends TestCase
{
    /**
     * Test that a missing middleware is flagged with its pattern in the hint.
     *
     * @return void
     */
    public function testFlagsMissingMiddleware(): void
    {
        $violations = $this->inspect(['admin/*' => ['auth']], 'admin/users', ['throttle']);

        self::assertCount(1, $violations);
        self::assertSame('R10', $violations[0]->ruleId);
        self::assertSame(Severity::WARNING, $violations[0]->severity);
        self::assertSame('GET admin/users admin.users', $violations[0]->routeIdentity);
        self::assertSame('auth', $violations[0]->offendingSurface);
        self::assertSame('add the `auth` middleware (route matches `admin/*`)', $violations[0]->remediationHint);
    }

    /**
     * Provide required-middleware maps and routes with the middleware R10
     * flags.
     *
     * @return iterable<string, array{array<string, array<int, string>>, string, list<string>, list<string>}>
     */
    public static function provideRoutes(): iterable
    {
        yield 'no patterns' => [[], 'admin/users', ['auth'], []];
        yield 'middleware present' => [['admin/*' => ['auth']], 'admin/users', ['auth'], []];
        yield 'no matching pattern' => [['admin/*' => ['auth']], 'api/users', [], []];
        yield 'one of two missing' => [['admin/*' => ['auth', 'can:access-admin']], 'admin/users', ['auth'], ['can:access-admin']];
        yield 'two matching patterns' => [['admin/*' => ['auth'], 'admin/users/*' => ['can:manage-users']], 'admin/users/5', [], ['auth', 'can:manage-users']];
        yield 'match after a miss' => [['api/*' => ['throttle'], 'admin/*' => ['auth']], 'admin/users', [], ['auth']];
    }

    /**
     * Test that each middleware a matching pattern requires and the route lacks
     * is flagged, in pattern order.
     *
     * @param  array<string, array<int, string>>  $required
     * @param  string  $uri
     * @param  list<string>  $middleware
     * @param  list<string>  $surfaces
     * @return void
     */
    #[DataProvider('provideRoutes')]
    public function testFlagsEachMissingMiddleware(array $required, string $uri, array $middleware, array $surfaces): void
    {
        $violations = $this->inspect($required, $uri, $middleware);

        self::assertSame($surfaces, array_map(static fn (Violation $violation): string => $violation->offendingSurface, $violations));
    }

    /**
     * Test that the rule reports as R10 at warning severity.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        $rule = new RequiredMiddlewareRule;

        self::assertSame('R10', $rule->id());
        self::assertSame(Severity::WARNING, $rule->severity());
    }

    /**
     * Inspect a GET route, named after its URI, against a required-middleware
     * map.
     *
     * @param  array<string, array<int, string>>  $required
     * @param  string  $uri
     * @param  array<int, string>  $middleware
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    private function inspect(array $required, string $uri, array $middleware): array
    {
        $route = new NormalisedRoute($uri, ['GET'], str_replace('/', '.', $uri), explode('/', $uri), [], middleware: $middleware);

        return (new RequiredMiddlewareRule)->inspect($route, new RuleConfig([], [], [], [], requiredMiddleware: $required));
    }
}
