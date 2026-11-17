<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\RouteNameRule;
use SineMacula\RouteLinter\Violation;
use Tests\TestCase;

/**
 * Tests for the RouteNameRule (R8) warning rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(RouteNameRule::class)]
final class RouteNameRuleTest extends TestCase
{
    /**
     * Provide route names with whether R8 flags them.
     *
     * @return iterable<string, array{string|null, bool}>
     */
    public static function provideNames(): iterable
    {
        foreach (['index', 'show', 'store', 'update', 'destroy', 'create', 'edit'] as $action) {
            yield $action => ['resources.' . $action, false];
        }

        yield 'nested resource' => ['users.posts.show', false];
        yield 'unnamed' => [null, false];
        yield 'non-resource action' => ['users.getAll', true];
        yield 'no dot' => ['login', true];
        yield 'leading dot' => ['.index', true];
        yield 'one-letter resource' => ['a.list', true];
    }

    /**
     * Test that a route name not of the form `{resource}.{action}`, with a
     * resource action, is flagged with the name as the surface.
     *
     * @param  string|null  $name
     * @param  bool  $flagged
     * @return void
     */
    #[DataProvider('provideNames')]
    public function testFlagsNamesOutsideTheConvention(?string $name, bool $flagged): void
    {
        $route = new NormalisedRoute('users', ['GET'], $name, ['users'], []);

        $violations = (new RouteNameRule)->inspect($route, new RuleConfig([], [], [], []));

        self::assertSame(
            $flagged ? [['R8', Severity::WARNING, $name, null]] : [],
            array_map(
                static fn (Violation $violation): array => [$violation->ruleId, $violation->severity, $violation->offendingSurface, $violation->remediationHint],
                $violations,
            ),
        );
    }

    /**
     * Test that the rule reports as R8 at warning severity.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        $rule = new RouteNameRule;

        self::assertSame('R8', $rule->id());
        self::assertSame(Severity::WARNING, $rule->severity());
    }
}
