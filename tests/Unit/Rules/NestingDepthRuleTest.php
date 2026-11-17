<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\NestingDepthRule;
use SineMacula\RouteLinter\Violation;
use Tests\TestCase;

/**
 * Tests for the NestingDepthRule (R11) warning rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(NestingDepthRule::class)]
final class NestingDepthRuleTest extends TestCase
{
    /**
     * Provide routes with whether R11 flags them at the default depth.
     *
     * @return iterable<string, array{string, string|null, bool}>
     */
    public static function provideRoutes(): iterable
    {
        yield 'four levels' => ['users/{user}/posts/{post}/comments/{comment}/likes/{like}', null, true];
        yield 'three levels' => ['users/{user}/posts/{post}/comments/{comment}', null, false];
        yield 'api and version tokens' => ['api/{tenant}/v1/{locale}/users/{user}/posts/{post}/comments', null, false];
        yield 'api and version tokens over the limit' => ['api/{tenant}/v1/{locale}/users/{user}/posts/{post}/comments/{comment}/likes', null, true];
        yield 'uppercase version token' => ['V2/{locale}/users/{user}/posts/{post}/comments', null, false];
        yield 'segment containing a version token' => ['users/{user}/posts/{post}/comments/{comment}/xv2', null, true];
        yield 'version token with a suffix' => ['users/{user}/posts/{post}/comments/{comment}/v2extra', null, true];
        yield 'namespace segments' => ['admin/settings/users/{user}/posts/{post}/comments/{comment}', null, false];
        yield 'parameters' => ['{a}/{b}/users/{c}/posts/{d}/comments/{e}', null, false];
        yield 'inferred singleton' => ['users/{user}/posts/{post}/comments/{comment}/summary', 'users.posts.comments.summary.show', false];
        yield 'singleton route inference misses' => ['users/{user}/posts/{post}/comments/{comment}/summary', 'users.posts.comments.summary.store', true];
    }

    /**
     * Test that a route nesting more than three collection levels is flagged,
     * with the URI as the surface.
     *
     * @param  string  $uri
     * @param  string|null  $name
     * @param  bool  $flagged
     * @return void
     */
    #[DataProvider('provideRoutes')]
    public function testFlagsRoutesNestedBeyondTheDefaultDepth(string $uri, ?string $name, bool $flagged): void
    {
        self::assertSame(
            $flagged ? [['R11', Severity::WARNING, $uri, null]] : [],
            array_map(
                static fn (Violation $violation): array => [$violation->ruleId, $violation->severity, $violation->offendingSurface, $violation->remediationHint],
                $this->inspect($uri, $name, new RuleConfig([], [], [], [])),
            ),
        );
    }

    /**
     * Test that a configured singleton resource is not counted as a level.
     *
     * @return void
     */
    public function testDeclaredSingletonResourceIsNotALevel(): void
    {
        $config = new RuleConfig([], [], [], [], singletonResources: ['comments/{comment}/summary']);

        self::assertSame([], $this->inspect('users/{user}/posts/{post}/comments/{comment}/summary', 'users.posts.comments.summary.store', $config));
    }

    /**
     * Test that the configured maximum depth replaces the default.
     *
     * @return void
     */
    public function testHonoursTheConfiguredMaxDepth(): void
    {
        self::assertCount(1, $this->inspect('users/{user}/posts', null, new RuleConfig([], [], [], [], nestingMaxDepth: 1)));
        self::assertCount(0, $this->inspect('users/{user}/posts', null, new RuleConfig([], [], [], [], nestingMaxDepth: 5)));
    }

    /**
     * Test that the rule reports as R11 at warning severity.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        $rule = new NestingDepthRule;

        self::assertSame('R11', $rule->id());
        self::assertSame(Severity::WARNING, $rule->severity());
    }

    /**
     * Inspect a GET route built from the given URI and name.
     *
     * @param  string  $uri
     * @param  string|null  $name
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    private function inspect(string $uri, ?string $name, RuleConfig $config): array
    {
        return (new NestingDepthRule)->inspect(new NormalisedRoute($uri, ['GET'], $name, explode('/', $uri), []), $config);
    }
}
