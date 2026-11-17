<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\StandardMethodsRule;
use SineMacula\RouteLinter\Violation;
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
    /**
     * Provide method sets with the surface R7 flags, or null for none.
     *
     * @return iterable<string, array{list<string>, string|null}>
     */
    public static function provideMethods(): iterable
    {
        yield 'standard' => [['GET', 'HEAD'], null];
        yield 'non-standard' => [['PURGE'], 'PURGE'];
        yield 'non-standard beside standard' => [['GET', 'FOO'], 'FOO'];
        yield 'two non-standard' => [['ZZZ', 'GET', 'AAA'], 'AAA, ZZZ'];
    }

    /**
     * Test that a route's non-standard methods are flagged once, sorted and
     * comma-joined.
     *
     * @param  list<string>  $methods
     * @param  string|null  $surface
     * @return void
     */
    #[DataProvider('provideMethods')]
    public function testFlagsNonStandardMethods(array $methods, ?string $surface): void
    {
        $route = new NormalisedRoute('users', $methods, null, ['users'], []);

        $violations = (new StandardMethodsRule)->inspect($route, new RuleConfig([], [], [], []));

        self::assertSame(
            $surface === null ? [] : [['R7', Severity::ERROR, $surface, null]],
            array_map(
                static fn (Violation $violation): array => [$violation->ruleId, $violation->severity, $violation->offendingSurface, $violation->remediationHint],
                $violations,
            ),
        );
    }

    /**
     * Test that the rule reports as R7 at error severity.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        $rule = new StandardMethodsRule;

        self::assertSame('R7', $rule->id());
        self::assertSame(Severity::ERROR, $rule->severity());
    }
}
