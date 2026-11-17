<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\LowercaseRule;
use SineMacula\RouteLinter\Violation;
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
    /**
     * Provide URIs with the segments R3 flags on them.
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideRoutes(): iterable
    {
        yield 'uppercase' => ['Users', ['Users']];
        yield 'camelCase' => ['userProfiles', ['userProfiles']];
        yield 'lowercase' => ['users', []];
        yield 'parameter' => ['{User}', []];
        yield 'trailing slash' => ['users/', []];
        yield 'after a parameter' => ['{user}/Users', ['Users']];
        yield 'after a valid segment' => ['users/Posts', ['Posts']];
        yield 'two offenders' => ['Users/Posts', ['Users', 'Posts']];
    }

    /**
     * Test that every literal segment containing an uppercase letter is
     * flagged, in path order.
     *
     * @param  string  $uri
     * @param  list<string>  $surfaces
     * @return void
     */
    #[DataProvider('provideRoutes')]
    public function testFlagsEachSegmentWithUppercase(string $uri, array $surfaces): void
    {
        $route = new NormalisedRoute($uri, ['GET'], null, explode('/', $uri), []);

        $violations = (new LowercaseRule)->inspect($route, new RuleConfig([], [], [], []));

        self::assertSame(
            array_map(static fn (string $surface): array => ['R3', Severity::ERROR, $surface, null], $surfaces),
            array_map(
                static fn (Violation $violation): array => [$violation->ruleId, $violation->severity, $violation->offendingSurface, $violation->remediationHint],
                $violations,
            ),
        );
    }

    /**
     * Test that the rule reports as R3 at error severity.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        $rule = new LowercaseRule;

        self::assertSame('R3', $rule->id());
        self::assertSame(Severity::ERROR, $rule->severity());
    }
}
