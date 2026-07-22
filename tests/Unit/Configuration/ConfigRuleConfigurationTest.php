<?php

declare(strict_types = 1);

namespace Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
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
     * Test that load() returns an empty exemptions array when no app overrides
     * are present (the shipped-default zero-exemption state).
     *
     * @return void
     */
    public function testDefaultExemptionsAreEmpty(): void
    {
        $adapter = new ConfigRuleConfiguration;
        $config  = $adapter->load();

        self::assertSame([], $config->exemptions);
    }

    /**
     * Test that load() reads the three separate config surfaces independently
     * and assembles them into a RuleConfig with matching values.
     *
     * @return void
     */
    public function testReadsThreeSeparateSurfaces(): void
    {
        config()->set('route-linter.verb_denylist', ['get', 'fetch']);
        config()->set('route-linter.remediation_hints', ['get' => 'Use a noun resource instead.']);
        config()->set('route-linter.exemptions', [
            ['match' => 'users.store', 'reason' => 'Legacy endpoint kept for backward compatibility.'],
        ]);
        config()->set('route-linter.uncountables', ['media', 'data']);

        $adapter = new ConfigRuleConfiguration;
        $result  = $adapter->load();

        self::assertSame(['get', 'fetch'], $result->verbDenylist);
        self::assertSame(['get' => 'Use a noun resource instead.'], $result->remediationHints);
        self::assertCount(1, $result->exemptions);
        self::assertSame('users.store', $result->exemptions[0]->match);
        self::assertSame('Legacy endpoint kept for backward compatibility.', $result->exemptions[0]->reason);
        self::assertSame(['media', 'data'], $result->uncountables);
    }

    /**
     * Test that load() fails loud when an array-typed config key holds a
     * non-array value, rather than silently coercing it to an empty array
     * (which would weaken the lint verdict - e.g. an empty verb denylist flags
     * nothing).
     *
     * @return void
     */
    public function testNonArrayConfigValueIsRejected(): void
    {
        config()->set('route-linter.verb_denylist', 'get,create,update');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Config "route-linter.verb_denylist" must be an array');

        (new ConfigRuleConfiguration)->load();
    }

    /**
     * Test that load() fails loud when the nesting-depth config key holds a
     * non-integer value.
     *
     * @return void
     */
    public function testNonIntegerNestingMaxDepthIsRejected(): void
    {
        config()->set('route-linter.nesting_max_depth', 'three');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Config "route-linter.nesting_max_depth" must be an integer');

        (new ConfigRuleConfiguration)->load();
    }

    /**
     * Test that load() reads the configured nesting-depth into the RuleConfig.
     *
     * @return void
     */
    public function testReadsNestingMaxDepthFromConfig(): void
    {
        config()->set('route-linter.nesting_max_depth', 5);

        self::assertSame(5, (new ConfigRuleConfiguration)->load()->nestingMaxDepth);
    }

    /**
     * Test that load() throws InvalidConfigurationException when an exemption
     * entry has no reason, enforcing the required-reason invariant.
     *
     * @return void
     */
    public function testExemptionWithoutReasonIsRejected(): void
    {
        config()->set('route-linter.exemptions', [
            ['match' => 'users.store'],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Allowlist entry "users.store" is missing a required reason.');

        $adapter = new ConfigRuleConfiguration;
        $adapter->load();
    }

    /**
     * Test that load() throws InvalidConfigurationException when an exemption
     * entry has an empty (whitespace-only) reason.
     *
     * @return void
     */
    public function testExemptionWithEmptyReasonIsRejected(): void
    {
        config()->set('route-linter.exemptions', [
            ['match' => 'users.store', 'reason' => '   '],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Allowlist entry "users.store" is missing a required reason.');

        $adapter = new ConfigRuleConfiguration;
        $adapter->load();
    }

    /**
     * Test that load() returns empty arrays for all surfaces when config keys
     * are entirely absent.
     *
     * @return void
     */
    public function testMissingConfigKeysDefaultToEmptyArrays(): void
    {
        config()->set('route-linter', null);

        $adapter = new ConfigRuleConfiguration;
        $result  = $adapter->load();

        self::assertSame([], $result->verbDenylist);
        self::assertSame([], $result->remediationHints);
        self::assertSame([], $result->exemptions);
        self::assertSame([], $result->uncountables);
        self::assertSame(3, $result->nestingMaxDepth);
    }

    /**
     * Test that an exemption entry with a `rules` key produces an
     * AllowlistEntry whose covers() is scoped to those rule IDs only.
     *
     * @return void
     */
    public function testExemptionWithRulesKeyProducesScopedEntry(): void
    {
        config()->set('route-linter.exemptions', [
            ['match' => 'users.store', 'reason' => 'Scoped waiver.', 'rules' => ['R9', 'R3']],
        ]);

        $adapter = new ConfigRuleConfiguration;
        $result  = $adapter->load();

        self::assertCount(1, $result->exemptions);

        $entry = $result->exemptions[0];

        self::assertSame(['R9', 'R3'], $entry->rules);
        self::assertTrue($entry->covers('R9'));
        self::assertTrue($entry->covers('R3'));
        self::assertFalse($entry->covers('R1'));
    }

    /**
     * Test that an exemption entry without a `rules` key produces an
     * AllowlistEntry that covers all rules (backward-compatible default).
     *
     * @return void
     */
    public function testExemptionWithoutRulesKeyCoversAllRules(): void
    {
        config()->set('route-linter.exemptions', [
            ['match' => 'orders.index', 'reason' => 'All-rules waiver.'],
        ]);

        $adapter = new ConfigRuleConfiguration;
        $result  = $adapter->load();

        self::assertCount(1, $result->exemptions);

        $entry = $result->exemptions[0];

        self::assertSame([], $entry->rules);
        self::assertTrue($entry->covers('R9'));
        self::assertTrue($entry->covers('R1'));
    }

    /**
     * Test that a non-array `rules` value in an exemption config entry is
     * treated as empty (all rules covered), so a corrupt config key does not
     * crash the adapter.
     *
     * @return void
     */
    public function testNonArrayRulesValueDefaultsToEmpty(): void
    {
        config()->set('route-linter.exemptions', [
            ['match' => 'reports.index', 'reason' => 'Fallback waiver.', 'rules' => 'not-an-array'],
        ]);

        $adapter = new ConfigRuleConfiguration;
        $result  = $adapter->load();

        self::assertCount(1, $result->exemptions);
        self::assertSame([], $result->exemptions[0]->rules);
    }

    /**
     * Test that load() throws InvalidConfigurationException when an exemption
     * entry is a non-array scalar (kills LogicalOr mutant #1:
     * `!is_array($item) && ...`).
     *
     * The original guard `!is_array($item) || !isset($item['match']) || ...`
     * must throw when the item is a plain string - a non-array value can never
     * have a `match` key but the mutant changes `||` to `&&` before the second
     * clause, allowing a non-array to pass silently.
     *
     * @return void
     */
    public function testNonArrayExemptionItemThrowsInvalidConfigurationException(): void
    {
        config()->set('route-linter.exemptions', [
            'this-is-a-string-not-an-array',
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Allowlist entry is missing a required match key.');

        $adapter = new ConfigRuleConfiguration;
        $adapter->load();
    }

    /**
     * Test that an array exemption item with no 'match' key throws
     * InvalidConfigurationException (kills LogicalOr mutant #2: `... &&
     * !is_string($item['match'])`).
     *
     * The original guard requires `isset($item['match'])` as well as
     * `is_string($item['match'])`. The mutant changes the second `||` to `&&`,
     * which lets a missing-match entry through when `is_string` is also false.
     *
     * @return void
     */
    public function testArrayExemptionItemWithoutMatchKeyThrowsInvalidConfigurationException(): void
    {
        config()->set('route-linter.exemptions', [
            ['reason' => 'Has a reason but no match key.'],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Allowlist entry is missing a required match key.');

        $adapter = new ConfigRuleConfiguration;
        $adapter->load();
    }

    /**
     * Test that a `rules` list containing non-string values has those values
     * filtered out, keeping only string entries (kills UnwrapArrayFilter
     * mutant).
     *
     * `array_filter($rawRules, 'is_string')` must remove the integer; without
     * the filter the mutant returns the integer entry inside
     * `AllowlistEntry::$rules`.
     *
     * @return void
     */
    public function testNonStringRuleValuesAreFilteredOut(): void
    {
        config()->set('route-linter.exemptions', [
            ['match' => 'orders.store', 'reason' => 'Mixed rules.', 'rules' => ['R1', 42, 'R3', null]],
        ]);

        $adapter = new ConfigRuleConfiguration;
        $result  = $adapter->load();

        self::assertCount(1, $result->exemptions);

        $rules = $result->exemptions[0]->rules;

        // Only the two string values should survive
        self::assertSame(['R1', 'R3'], $rules);
    }

    /**
     * Test that the filtered rules list is re-indexed from zero (kills
     * UnwrapArrayValues mutant: `array_values(array_filter(...))` vs just the
     * filter).
     *
     * After filtering, `array_values` guarantees integer keys 0, 1, 2... even
     * when the source array had gaps. Without `array_values` the mutant returns
     * a
     * non-contiguous array that fails an assertSame against [0 => 'R1', 1 =>
     * 'R3'].
     *
     * @return void
     */
    public function testFilteredRulesListIsReIndexed(): void
    {
        // Place a non-string at index 0 so after filtering index 1 would remain
        // 1 without array_values - with array_values it becomes index 0.
        config()->set('route-linter.exemptions', [
            ['match' => 'users.index', 'reason' => 'Reindex test.', 'rules' => [0 => 99, 1 => 'R1', 2 => 'R3']],
        ]);

        $adapter = new ConfigRuleConfiguration;
        $result  = $adapter->load();

        self::assertCount(1, $result->exemptions);

        $rules = $result->exemptions[0]->rules;

        // Must be a contiguous 0-based list
        self::assertSame([0 => 'R1', 1 => 'R3'], $rules);
    }

    /**
     * Test that two valid exemption entries both survive (kills ArrayOneItem
     * mutant).
     *
     * The ArrayOneItem mutant returns `array_slice($entries, 0, 1)` when count
     * > 1, truncating to the first entry only.
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

        $adapter = new ConfigRuleConfiguration;
        $result  = $adapter->load();

        self::assertCount(3, $result->exemptions);
        self::assertSame('users.index', $result->exemptions[0]->match);
        self::assertSame('orders.index', $result->exemptions[1]->match);
        self::assertSame('products.index', $result->exemptions[2]->match);
    }

    /**
     * Test that the required-middleware surface defaults to an empty map when
     * the config key is absent.
     *
     * @return void
     */
    public function testDefaultRequiredMiddlewareIsEmpty(): void
    {
        self::assertSame([], (new ConfigRuleConfiguration)->load()->requiredMiddleware);
    }

    /**
     * Test that load() reads the required-middleware map into the RuleConfig,
     * preserving patterns and their middleware lists.
     *
     * @return void
     */
    public function testReadsRequiredMiddlewareFromConfig(): void
    {
        config()->set('route-linter.required_middleware', [
            'admin/*' => ['auth', 'can:access-admin'],
            'api/*'   => ['auth:sanctum'],
        ]);

        $result = (new ConfigRuleConfiguration)->load();

        self::assertSame([
            'admin/*' => ['auth', 'can:access-admin'],
            'api/*'   => ['auth:sanctum'],
        ], $result->requiredMiddleware);
    }

    /**
     * Test that load() fails loud when a required-middleware pattern is not a
     * string (e.g. the config is a list, yielding integer keys).
     *
     * @return void
     */
    public function testNonStringRequiredMiddlewarePatternIsRejected(): void
    {
        config()->set('route-linter.required_middleware', [['auth']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Required-middleware pattern must be a string');

        (new ConfigRuleConfiguration)->load();
    }

    /**
     * Test that load() fails loud when a required-middleware value is not an
     * array.
     *
     * @return void
     */
    public function testNonArrayRequiredMiddlewareValueIsRejected(): void
    {
        config()->set('route-linter.required_middleware', ['admin/*' => 'auth']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Required middleware for "admin/*" must be an array');

        (new ConfigRuleConfiguration)->load();
    }

    /**
     * Test that load() fails loud when a required-middleware list contains a
     * non-string entry.
     *
     * @return void
     */
    public function testNonStringRequiredMiddlewareEntryIsRejected(): void
    {
        config()->set('route-linter.required_middleware', ['admin/*' => ['auth', 42]]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Required middleware for "admin/*" must be a list of strings');

        (new ConfigRuleConfiguration)->load();
    }
}
