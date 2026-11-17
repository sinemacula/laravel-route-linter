<?php

declare(strict_types = 1);

namespace Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Configuration\ConfigRuleConfiguration;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;
use Tests\TestCase;

/**
 * Tests for ConfigRuleConfiguration.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(ConfigRuleConfiguration::class)]
final class ConfigRuleConfigurationTest extends TestCase
{
    /**
     * Test that the shipped config has no exemptions or required middleware.
     *
     * @return void
     */
    public function testShippedDefaultsHaveNoExemptionsOrRequiredMiddleware(): void
    {
        $config = (new ConfigRuleConfiguration)->load();

        self::assertSame([], $config->exemptions);
        self::assertSame([], $config->requiredMiddleware);
    }

    /**
     * Test that the word lists, hints and exemptions are read separately.
     *
     * @return void
     */
    public function testReadsEachSurfaceSeparately(): void
    {
        config()->set('route-linter.verb_denylist', ['get', 'fetch']);
        config()->set('route-linter.remediation_hints', [
            'get'   => 'Use a noun resource instead.',
            'fetch' => 'GET the collection instead.',
        ]);
        config()->set('route-linter.exemptions', [
            ['match' => 'users.store', 'reason' => 'Legacy endpoint kept for backward compatibility.'],
        ]);
        config()->set('route-linter.uncountables', ['media', 'data']);

        $result = (new ConfigRuleConfiguration)->load();

        self::assertSame(['get', 'fetch'], $result->verbDenylist);
        self::assertSame([
            'get'   => 'Use a noun resource instead.',
            'fetch' => 'GET the collection instead.',
        ], $result->remediationHints);
        self::assertCount(1, $result->exemptions);
        self::assertSame('users.store', $result->exemptions[0]->match);
        self::assertSame('Legacy endpoint kept for backward compatibility.', $result->exemptions[0]->reason);
        self::assertSame(['media', 'data'], $result->uncountables);
    }

    /**
     * Test that absent config keys load as empty surfaces and the default
     * depth.
     *
     * @return void
     */
    public function testMissingConfigKeysDefaultToEmptyArrays(): void
    {
        config()->set('route-linter', null);

        $result = (new ConfigRuleConfiguration)->load();

        self::assertSame([], $result->verbDenylist);
        self::assertSame([], $result->remediationHints);
        self::assertSame([], $result->exemptions);
        self::assertSame([], $result->uncountables);
        self::assertSame(3, $result->nestingMaxDepth);
        self::assertSame([], $result->verbAllowlist);
        self::assertSame([], $result->singletonResources);
    }

    /**
     * Test that the nesting depth is read from config.
     *
     * @return void
     */
    public function testReadsNestingMaxDepthFromConfig(): void
    {
        config()->set('route-linter.nesting_max_depth', 5);

        self::assertSame(5, (new ConfigRuleConfiguration)->load()->nestingMaxDepth);
    }

    /**
     * Test that the verb allowlist is read from config.
     *
     * @return void
     */
    public function testReadsVerbAllowlistFromConfig(): void
    {
        config()->set('route-linter.verb_allowlist', ['reset', 'transfer']);

        self::assertSame(['reset', 'transfer'], (new ConfigRuleConfiguration)->load()->verbAllowlist);
    }

    /**
     * Test that singleton resource paths are read as given, slashes included.
     *
     * @return void
     */
    public function testReadsSingletonResourcesFromConfig(): void
    {
        config()->set('route-linter.singleton_resources', ['profile', 'connections/{connection}/callback', '/teams/{team}/settings/']);

        self::assertSame(['profile', 'connections/{connection}/callback', '/teams/{team}/settings/'], (new ConfigRuleConfiguration)->load()->singletonResources);
    }

    /**
     * Test that the required-middleware map is read as given.
     *
     * @return void
     */
    public function testReadsRequiredMiddlewareFromConfig(): void
    {
        config()->set('route-linter.required_middleware', [
            'admin/*' => ['auth', 'can:access-admin'],
            'api/*'   => ['auth:sanctum'],
        ]);

        self::assertSame([
            'admin/*' => ['auth', 'can:access-admin'],
            'api/*'   => ['auth:sanctum'],
        ], (new ConfigRuleConfiguration)->load()->requiredMiddleware);
    }

    /**
     * Provide malformed config values with the error each raises.
     *
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function provideMalformedConfig(): iterable
    {
        yield 'non-array word list' => ['verb_denylist', 'get,create,update', 'Config "route-linter.verb_denylist" must be an array'];
        yield 'non-string word' => ['uncountables', ['media', 42], 'Config "route-linter.uncountables" must be a list of strings; got int.'];
        yield 'non-string allowed verb' => ['verb_allowlist', ['reset', 7], 'Config "route-linter.verb_allowlist" must be a list of strings; got int.'];
        yield 'non-string hint' => [
            'remediation_hints',
            ['get' => ['Use a noun resource instead.']],
            'Config "route-linter.remediation_hints" must map strings to strings; got string => array.',
        ];
        yield 'hint without a verb key' => [
            'remediation_hints',
            ['Use a noun resource instead.'],
            'Config "route-linter.remediation_hints" must map strings to strings; got int => string.',
        ];
        yield 'non-integer depth' => ['nesting_max_depth', 'three', 'Config "route-linter.nesting_max_depth" must be an integer'];
        yield 'exemption without reason' => ['exemptions', [['match' => 'users.store']], 'Allowlist entry "users.store" is missing a required reason.'];
        yield 'exemption with blank reason' => ['exemptions', [['match' => 'users.store', 'reason' => '   ']], 'Allowlist entry "users.store" is missing a required reason.'];
        yield 'non-array exemption' => ['exemptions', ['this-is-a-string-not-an-array'], 'Allowlist entry is missing a required match key.'];
        yield 'exemption without match' => ['exemptions', [['reason' => 'Has a reason but no match key.']], 'Allowlist entry is missing a required match key.'];
        yield 'non-string middleware pattern' => ['required_middleware', [['auth']], 'Required-middleware pattern must be a string'];
        yield 'non-array middleware list' => ['required_middleware', ['admin/*' => 'auth'], 'Required middleware for "admin/*" must be an array'];
        yield 'non-string middleware' => ['required_middleware', ['admin/*' => ['auth', 42]], 'Required middleware for "admin/*" must be a list of strings'];
        yield 'empty singleton path' => ['singleton_resources', ['profile', ''], 'Singleton resource "" must end in a literal segment.'];
        yield 'slash-only singleton path' => ['singleton_resources', ['profile', '/'], 'Singleton resource "/" must end in a literal segment.'];
        yield 'singleton path ending in a parameter' => [
            'singleton_resources',
            ['profile', 'connections/{connection}'],
            'Singleton resource "connections/{connection}" must end in a literal segment.',
        ];
        yield 'singleton path ending in a parameter and slash' => [
            'singleton_resources',
            ['profile', 'connections/{connection}/'],
            'Singleton resource "connections/{connection}/" must end in a literal segment.',
        ];
    }

    /**
     * Test that malformed config is rejected with a clear error.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  string  $message
     * @return void
     */
    #[DataProvider('provideMalformedConfig')]
    public function testMalformedConfigIsRejected(string $key, mixed $value, string $message): void
    {
        config()->set('route-linter.' . $key, $value);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        (new ConfigRuleConfiguration)->load();
    }

    /**
     * Provide the `rules` key of an exemption with the rule list it loads as.
     *
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function provideExemptionRules(): iterable
    {
        yield 'listed rules' => [['rules' => ['R9', 'R3']], ['R9', 'R3']];
        yield 'absent' => [[], []];
        yield 'not an array' => [['rules' => 'not-an-array'], []];
        yield 'non-string rules dropped' => [['rules' => ['R1', 42, 'R3', null]], ['R1', 'R3']];
        yield 're-indexed after filtering' => [['rules' => [0 => 99, 1 => 'R1', 2 => 'R3']], ['R1', 'R3']];
    }

    /**
     * Test that an exemption's rules keep only string IDs as a list, and cover
     * every rule when absent or malformed.
     *
     * @param  array<string, mixed>  $rules
     * @param  list<string>  $expected
     * @return void
     */
    #[DataProvider('provideExemptionRules')]
    public function testExemptionRulesAreNormalised(array $rules, array $expected): void
    {
        config()->set('route-linter.exemptions', [['match' => 'users.store', 'reason' => 'Waiver.', ...$rules]]);

        $exemptions = (new ConfigRuleConfiguration)->load()->exemptions;

        self::assertCount(1, $exemptions);
        self::assertSame($expected, $exemptions[0]->rules);
    }

    /**
     * Test that every exemption entry is returned, in order.
     *
     * @return void
     */
    public function testMultipleExemptionEntriesAreAllReturned(): void
    {
        config()->set('route-linter.exemptions', [
            ['match' => 'users.index', 'reason' => 'First waiver.'],
            ['match' => 'orders.index', 'reason' => 'Second waiver.'],
            ['match' => 'products.index', 'reason' => 'Third waiver.'],
        ]);

        $exemptions = (new ConfigRuleConfiguration)->load()->exemptions;

        self::assertCount(3, $exemptions);
        self::assertSame('users.index', $exemptions[0]->match);
        self::assertSame('orders.index', $exemptions[1]->match);
        self::assertSame('products.index', $exemptions[2]->match);
    }
}
