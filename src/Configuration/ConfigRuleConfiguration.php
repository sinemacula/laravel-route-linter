<?php

namespace SineMacula\RouteLinter\Configuration;

use Illuminate\Support\Facades\Config;
use SineMacula\RouteLinter\Contracts\RuleConfiguration;
use SineMacula\RouteLinter\Dto\AllowlistEntry;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;

/**
 * Config-backed adapter for the RuleConfiguration port.
 *
 * Reads the `route-linter.*` config section and assembles the strictly-separate
 * surfaces — verb denylist, remediation hints, exemption allowlist, inflector
 * uncountables, and nesting depth — into a {@see RuleConfig} DTO. The adapter
 * is the single place that validates the config schema: a non-array value for
 * an array-typed key, or an exemption entry missing its match or written
 * reason, raises an {@see InvalidConfigurationException} immediately rather
 * than being silently coerced to a value that would weaken the lint verdict.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class ConfigRuleConfiguration implements RuleConfiguration
{
    /**
     * Build the rule-config DTO from the config surfaces.
     *
     * @return \SineMacula\RouteLinter\Dto\RuleConfig
     *
     * @throws \SineMacula\RouteLinter\Exceptions\InvalidConfigurationException
     */
    #[\Override]
    public function load(): RuleConfig
    {
        return new RuleConfig(
            verbDenylist: $this->arrayConfig('route-linter.verb_denylist'),
            remediationHints: $this->arrayConfig('route-linter.remediation_hints'),
            exemptions: $this->buildExemptions($this->arrayConfig('route-linter.exemptions')),
            uncountables: $this->arrayConfig('route-linter.uncountables'),
            nestingMaxDepth: $this->intConfig('route-linter.nesting_max_depth', 3),
        );
    }

    /**
     * Read an array-typed config key, failing loud on a non-array value.
     *
     * A missing (null) value yields an empty array — absence is the shipped
     * default. A present-but-non-array value is a misconfiguration that would
     * silently disable a rule surface, so it is rejected.
     *
     * @param  string  $key
     * @return array<int|string, mixed>
     *
     * @throws \SineMacula\RouteLinter\Exceptions\InvalidConfigurationException
     */
    private function arrayConfig(string $key): array
    {
        $value = Config::get($key);

        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            throw new InvalidConfigurationException(sprintf('Config "%s" must be an array; got %s.', $key, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * Read an integer-typed config key, failing loud on a non-integer value.
     *
     * @param  string  $key
     * @param  int  $default
     * @return int
     *
     * @throws \SineMacula\RouteLinter\Exceptions\InvalidConfigurationException
     */
    private function intConfig(string $key, int $default): int
    {
        $value = Config::get($key, $default);

        if (!is_int($value)) {
            throw new InvalidConfigurationException(sprintf('Config "%s" must be an integer; got %s.', $key, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * Map raw config exemption entries to AllowlistEntry DTOs.
     *
     * Rejects any entry where `match` is absent or `reason` is missing,
     * not a string, or consists only of whitespace.
     *
     * @param  array<int|string, mixed>  $raw
     * @return array<int, \SineMacula\RouteLinter\Dto\AllowlistEntry>
     *
     * @throws \SineMacula\RouteLinter\Exceptions\InvalidConfigurationException
     */
    private function buildExemptions(array $raw): array
    {
        $entries = [];

        foreach ($raw as $item) {
            if (!is_array($item) || !isset($item['match']) || !is_string($item['match'])) {
                throw new InvalidConfigurationException('Allowlist entry is missing a required match key.');
            }

            $match  = $item['match'];
            $reason = $item['reason'] ?? null;

            if (!is_string($reason) || trim($reason) === '') {
                throw new InvalidConfigurationException(sprintf('Allowlist entry "%s" is missing a required reason.', $match));
            }

            $rawRules = $item['rules'] ?? [];
            $rules    = is_array($rawRules) ? array_values(array_filter($rawRules, 'is_string')) : [];

            $entries[] = new AllowlistEntry($match, $reason, $rules);
        }

        return $entries;
    }
}
