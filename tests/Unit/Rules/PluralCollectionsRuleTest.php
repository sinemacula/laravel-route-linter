<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Contracts\Inflector;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\PluralCollectionsRule;
use SineMacula\RouteLinter\Violation;
use Tests\TestCase;

/**
 * Tests for the PluralCollectionsRule (R4) error rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(PluralCollectionsRule::class)]
final class PluralCollectionsRuleTest extends TestCase
{
    /**
     * Provide URIs and uncountables with the segments R4 flags.
     *
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function provideRoutes(): iterable
    {
        yield 'singular before a parameter' => ['user/{user}', [], ['user']];
        yield 'plural before a parameter' => ['users/{user}', [], []];
        yield 'uncountable' => ['media/{item}', ['media'], []];
        yield 'singular top-level' => ['user', [], ['user']];
        yield 'plural top-level' => ['users', [], []];
        yield 'parameter' => ['{user}', [], []];
        yield 'leading slash' => ['/user/{user}', [], ['user']];
        yield 'singular after an uncountable' => ['media/{item}/user', ['media'], ['user']];
        yield 'singular after a plural' => ['users/{user}/comment', [], ['comment']];
        yield 'two singular members' => ['user/{user}/comment/{comment}', [], ['user', 'comment']];
        yield 'singular member then collection' => ['user/{user}/comment', [], ['user', 'comment']];
        yield 'literal followed by a literal' => ['user/page/{user}', [], ['page']];
        yield 'literal after a parameter' => ['{id}/user/extra', [], ['extra']];
        yield 'singular namespace before a plural' => ['user/users', [], []];
        yield 'singular before a trailing slash' => ['comment/', [], ['comment']];
    }

    /**
     * Test that every singular collection segment is flagged, in path order.
     *
     * @param  string  $uri
     * @param  list<string>  $uncountables
     * @param  list<string>  $surfaces
     * @return void
     */
    #[DataProvider('provideRoutes')]
    public function testFlagsEachSingularCollectionSegment(string $uri, array $uncountables, array $surfaces): void
    {
        $violations = $this->inspect($uri, null, 'GET', new RuleConfig([], [], [], $uncountables));

        self::assertSame(
            array_map(static fn (string $surface): array => ['R4', Severity::ERROR, $surface, null], $surfaces),
            array_map(
                static fn (Violation $violation): array => [$violation->ruleId, $violation->severity, $violation->offendingSurface, $violation->remediationHint],
                $violations,
            ),
        );
    }

    /**
     * Provide routes beside a configured `profile` singleton with the segments
     * R4 flags.
     *
     * @return iterable<string, array{string, string|null, string, list<string>}>
     */
    public static function provideSingletonRoutes(): iterable
    {
        yield 'inferred nested singleton' => ['connections/{connection}/callback', 'connections.callback.show', 'GET', []];
        yield 'inferred update route' => ['profile', 'profile.update', 'PATCH', []];
        yield 'declared store route' => ['profile', 'profile.store', 'POST', []];
        yield 'singular parent' => ['connection/{connection}/callback', 'connection.callback.show', 'GET', ['connection']];
        yield 'ordinary singular collection' => ['setting', null, 'GET', ['setting']];
    }

    /**
     * Test that singleton resource routes are not flagged while their singular
     * parent and an ordinary singular collection are.
     *
     * @param  string  $uri
     * @param  string|null  $name
     * @param  string  $method
     * @param  list<string>  $surfaces
     * @return void
     */
    #[DataProvider('provideSingletonRoutes')]
    public function testDoesNotFlagSingletonResources(string $uri, ?string $name, string $method, array $surfaces): void
    {
        $violations = $this->inspect($uri, $name, $method, new RuleConfig([], [], [], [], singletonResources: ['profile']));

        self::assertSame($surfaces, array_map(static fn (Violation $violation): string => $violation->offendingSurface, $violations));
    }

    /**
     * Test that the rule reports as R4 at error severity.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        $rule = $this->rule();

        self::assertSame('R4', $rule->id());
        self::assertSame(Severity::ERROR, $rule->severity());
    }

    /**
     * Inspect a route built from the given parts.
     *
     * @param  string  $uri
     * @param  string|null  $name
     * @param  string  $method
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    private function inspect(string $uri, ?string $name, string $method, RuleConfig $config): array
    {
        return $this->rule()->inspect(new NormalisedRoute($uri, [$method], $name, explode('/', $uri), []), $config);
    }

    /**
     * Build the rule with an inflector that treats words ending in `s` as
     * plural.
     *
     * @return \SineMacula\RouteLinter\Rules\PluralCollectionsRule
     */
    private function rule(): PluralCollectionsRule
    {
        return new PluralCollectionsRule(new class implements Inflector {
            /**
             * Return the singular form of a value.
             *
             * @param  string  $value
             * @return string
             */
            #[\Override]
            public function singular(string $value): string
            {
                return rtrim($value, 's');
            }

            /**
             * Determine whether the value is plural.
             *
             * @param  string  $value
             * @return bool
             */
            #[\Override]
            public function isPlural(string $value): bool
            {
                return str_ends_with($value, 's');
            }
        });
    }
}
