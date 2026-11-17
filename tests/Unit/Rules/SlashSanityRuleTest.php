<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\SlashSanityRule;
use SineMacula\RouteLinter\Violation;
use Tests\TestCase;

/**
 * Tests for the SlashSanityRule (R5) error rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(SlashSanityRule::class)]
final class SlashSanityRuleTest extends TestCase
{
    /**
     * Provide URIs with the surfaces R5 flags on them.
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideRoutes(): iterable
    {
        yield 'trailing slash' => ['users/', ['users/']];
        yield 'duplicate slash' => ['users//posts', ['users//posts']];
        yield 'both defects' => ['users//posts/', ['users//posts/']];
        yield 'clean' => ['users/{user}', []];
        yield 'root path' => ['/', []];
        yield 'empty path' => ['', []];
    }

    /**
     * Test that a URI with a trailing or duplicate slash is flagged once, with
     * the URI as the surface.
     *
     * @param  string  $uri
     * @param  list<string>  $surfaces
     * @return void
     */
    #[DataProvider('provideRoutes')]
    public function testFlagsTrailingAndDuplicateSlashes(string $uri, array $surfaces): void
    {
        $route = new NormalisedRoute($uri, ['GET'], null, explode('/', $uri), []);

        $violations = (new SlashSanityRule)->inspect($route, new RuleConfig([], [], [], []));

        self::assertSame(
            array_map(static fn (string $surface): array => ['R5', Severity::ERROR, $surface, null], $surfaces),
            array_map(
                static fn (Violation $violation): array => [$violation->ruleId, $violation->severity, $violation->offendingSurface, $violation->remediationHint],
                $violations,
            ),
        );
    }

    /**
     * Test that the rule reports as R5 at error severity.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        $rule = new SlashSanityRule;

        self::assertSame('R5', $rule->id());
        self::assertSame(Severity::ERROR, $rule->severity());
    }
}
