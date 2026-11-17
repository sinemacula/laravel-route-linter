<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\ApiResourceAlignmentRule;
use SineMacula\RouteLinter\Violation;
use Tests\TestCase;

/**
 * Tests for the ApiResourceAlignmentRule (R9) warning rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(ApiResourceAlignmentRule::class)]
final class ApiResourceAlignmentRuleTest extends TestCase
{
    /**
     * Provide URIs with the surfaces R9 flags on them.
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideRoutes(): iterable
    {
        yield 'edit' => ['photos/{photo}/edit', ['edit']];
        yield 'create' => ['photos/create', ['create']];
        yield 'edit before a parameter' => ['users/edit/{user}', ['edit']];
        yield 'create before a parameter' => ['photos/create/{photo}', ['create']];
        yield 'create before a trailing slash' => ['photos/create/', ['create']];
        yield 'show' => ['photos/{photo}', []];
        yield 'create not last' => ['create/items', []];
        yield 'parameters only' => ['{tenant}/{user}', []];
    }

    /**
     * Test that the final literal segment is flagged when it is an HTML-only
     * action.
     *
     * @param  string  $uri
     * @param  list<string>  $surfaces
     * @return void
     */
    #[DataProvider('provideRoutes')]
    public function testFlagsHtmlOnlyFinalAction(string $uri, array $surfaces): void
    {
        $route = new NormalisedRoute($uri, ['GET'], null, explode('/', $uri), []);

        $violations = (new ApiResourceAlignmentRule)->inspect($route, new RuleConfig([], [], [], []));

        self::assertSame(
            array_map(static fn (string $surface): array => ['R9', Severity::WARNING, $surface, null], $surfaces),
            array_map(
                static fn (Violation $violation): array => [$violation->ruleId, $violation->severity, $violation->offendingSurface, $violation->remediationHint],
                $violations,
            ),
        );
    }

    /**
     * Test that the rule reports as R9 at warning severity.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        $rule = new ApiResourceAlignmentRule;

        self::assertSame('R9', $rule->id());
        self::assertSame(Severity::WARNING, $rule->severity());
    }
}
