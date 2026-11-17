<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\RouteHandlerExistsRule;
use SineMacula\RouteLinter\Violation;
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
    /**
     * Provide handlers with whether R12 flags them.
     *
     * @return iterable<string, array{string|null, bool}>
     */
    public static function provideHandlers(): iterable
    {
        yield 'closure' => [null, false];
        yield 'existing method' => [RouteLintController::class . '@index', false];
        yield 'missing method' => [RouteLintController::class . '@doesNotExist', true];
        yield 'missing class' => ['Tests\Fixtures\Controllers\NoSuchController@index', true];
        yield 'invokable class' => [RouteLintController::class, false];
        yield 'class without __invoke' => [ParameterEchoRule::class, true];
    }

    /**
     * Test that a handler whose class or method does not exist is flagged, with
     * the handler as the surface.
     *
     * @param  string|null  $handler
     * @param  bool  $flagged
     * @return void
     */
    #[DataProvider('provideHandlers')]
    public function testFlagsMissingHandlers(?string $handler, bool $flagged): void
    {
        $route = new NormalisedRoute('users', ['GET'], 'users.index', ['users'], [], handler: $handler);

        $violations = (new RouteHandlerExistsRule)->inspect($route, new RuleConfig([], [], [], []));

        self::assertSame(
            $flagged ? [['R12', Severity::ERROR, 'GET users users.index', $handler, null]] : [],
            array_map(
                static fn (Violation $v): array => [$v->ruleId, $v->severity, $v->routeIdentity, $v->offendingSurface, $v->remediationHint],
                $violations,
            ),
        );
    }

    /**
     * Test that the rule reports as R12 at error severity.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        $rule = new RouteHandlerExistsRule;

        self::assertSame('R12', $rule->id());
        self::assertSame(Severity::ERROR, $rule->severity());
    }
}
