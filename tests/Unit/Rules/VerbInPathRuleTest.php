<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Contracts\Inflector;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\Support\SegmentNormaliser;
use SineMacula\RouteLinter\Rules\Support\VerbDenylist;
use SineMacula\RouteLinter\Rules\VerbInPathRule;
use SineMacula\RouteLinter\Violation;
use Tests\TestCase;

/**
 * Tests for VerbInPathRule (R1).
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(VerbInPathRule::class)]
final class VerbInPathRuleTest extends TestCase
{
    /**
     * Provide URIs with the verbs and hints R1 flags on them.
     *
     * @return iterable<string, array{string, list<array{string, string}>}>
     */
    public static function provideRoutes(): iterable
    {
        yield 'compound segment' => ['getUsers', [['get', 'Use GET /resources instead.']]];
        yield 'trailing verb' => ['users/create', [['create', 'Use POST /resources instead.']]];
        yield 'verb after a parameter' => ['order/{id}/cancel', [['cancel', 'Use DELETE /resources/{id} instead.']]];
        yield 'verb alone' => ['login', [['login', 'Use POST /auth instead.']]];
        yield 'plural collection' => ['users', []];
        yield 'parameter' => ['{user}', []];
        yield 'same verb in two camelCase segments' => ['getItems/getDetails', [['get', 'Use GET /resources instead.']]];
        yield 'same verb in two kebab-case segments' => ['get-users/get-items', [['get', 'Use GET /resources instead.']]];
        yield 'two verbs' => ['getUsers/deleteItems', [['get', 'Use GET /resources instead.'], ['delete', 'Use DELETE /resources/{id} instead.']]];
    }

    /**
     * Test that each distinct denylisted verb in the path is flagged once, in
     * path order, with its remediation hint.
     *
     * @param  string  $uri
     * @param  list<array{string, string}>  $verbs
     * @return void
     */
    #[DataProvider('provideRoutes')]
    public function testFlagsEachDistinctVerb(string $uri, array $verbs): void
    {
        $violations = $this->rule()->inspect(new NormalisedRoute($uri, ['GET'], null, explode('/', $uri), []), new RuleConfig([], [], [], []));

        self::assertSame(
            array_map(static fn (array $verb): array => ['R1', Severity::ERROR, 'GET ' . $uri, $verb[0], $verb[1]], $verbs),
            array_map(
                static fn (Violation $v): array => [$v->ruleId, $v->severity, $v->routeIdentity, $v->offendingSurface, $v->remediationHint],
                $violations,
            ),
        );
    }

    /**
     * Test that the rule reports as R1 at error severity.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        $rule = $this->rule();

        self::assertSame('R1', $rule->id());
        self::assertSame(Severity::ERROR, $rule->severity());
    }

    /**
     * Build the rule over a small denylist, with an inflector that strips a
     * trailing `s` to singularise.
     *
     * @return \SineMacula\RouteLinter\Rules\VerbInPathRule
     */
    private function rule(): VerbInPathRule
    {
        $inflector = new class implements Inflector {
            /**
             * Return the singular form of a word.
             *
             * @param  string  $word
             * @return string
             */
            #[\Override]
            public function singular(string $word): string
            {
                return str_ends_with($word, 's') ? substr($word, 0, -1) : $word;
            }

            /**
             * Determine whether the word is plural.
             *
             * @param  string  $word
             * @return bool
             */
            #[\Override]
            public function isPlural(string $word): bool
            {
                return str_ends_with($word, 's');
            }
        };

        $denylist = new VerbDenylist(
            ['get', 'create', 'cancel', 'login', 'logout', 'delete', 'fetch'],
            [
                'get'    => 'Use GET /resources instead.',
                'create' => 'Use POST /resources instead.',
                'cancel' => 'Use DELETE /resources/{id} instead.',
                'login'  => 'Use POST /auth instead.',
                'logout' => 'Use DELETE /auth/session instead.',
                'delete' => 'Use DELETE /resources/{id} instead.',
                'fetch'  => 'Use GET /resources instead.',
            ],
        );

        return new VerbInPathRule(new SegmentNormaliser($inflector), $denylist);
    }
}
