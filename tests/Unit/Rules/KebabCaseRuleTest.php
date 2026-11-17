<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\KebabCaseRule;
use SineMacula\RouteLinter\Violation;
use Tests\TestCase;

/**
 * Tests for the KebabCaseRule (R2) error rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(KebabCaseRule::class)]
final class KebabCaseRuleTest extends TestCase
{
    /**
     * Provide URIs with the segments R2 flags on them.
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideRoutes(): iterable
    {
        yield 'camelCase' => ['userProfiles', ['userProfiles']];
        yield 'kebab-case' => ['user-profiles', []];
        yield 'plain lowercase' => ['users', []];
        yield 'parameter' => ['{user}', []];
        yield 'trailing slash' => ['users/', []];
        yield 'after a parameter' => ['{user}/userProfiles', ['userProfiles']];
        yield 'after a valid segment' => ['users/userProfiles', ['userProfiles']];
        yield 'two offenders' => ['userProfiles/some_other_stuff', ['userProfiles', 'some_other_stuff']];
    }

    /**
     * Test that every literal segment that is not kebab-case is flagged, in
     * path order.
     *
     * @param  string  $uri
     * @param  list<string>  $surfaces
     * @return void
     */
    #[DataProvider('provideRoutes')]
    public function testFlagsEachNonKebabSegment(string $uri, array $surfaces): void
    {
        $route = new NormalisedRoute($uri, ['GET'], null, explode('/', $uri), []);

        $violations = (new KebabCaseRule)->inspect($route, new RuleConfig([], [], [], []));

        self::assertSame(
            array_map(static fn (string $surface): array => ['R2', Severity::ERROR, $surface, null], $surfaces),
            array_map(
                static fn (Violation $violation): array => [$violation->ruleId, $violation->severity, $violation->offendingSurface, $violation->remediationHint],
                $violations,
            ),
        );
    }

    /**
     * Test that the rule reports as R2 at error severity.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        $rule = new KebabCaseRule;

        self::assertSame('R2', $rule->id());
        self::assertSame(Severity::ERROR, $rule->severity());
    }
}
