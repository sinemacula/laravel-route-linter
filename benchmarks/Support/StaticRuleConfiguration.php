<?php

namespace Benchmarks\Support;

use SineMacula\RouteLinter\Contracts\RuleConfiguration;
use SineMacula\RouteLinter\Dto\RuleConfig;

/**
 * In-memory rule-configuration adapter for the benchmark harness.
 *
 * Hands back a pre-built {@see RuleConfig} so the end-to-end LintRoutes
 * benchmark measures the linting hot path in isolation, without touching the
 * Laravel config repository or the `route-linter.*` config section. The config
 * is supplied once at construction and returned verbatim on every load.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class StaticRuleConfiguration implements RuleConfiguration
{
    /**
     * Create a new static rule configuration.
     *
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     */
    public function __construct(private RuleConfig $config) {}

    /**
     * Return the pre-built rule configuration.
     *
     * @return \SineMacula\RouteLinter\Dto\RuleConfig
     */
    #[\Override]
    public function load(): RuleConfig
    {
        return $this->config;
    }
}
