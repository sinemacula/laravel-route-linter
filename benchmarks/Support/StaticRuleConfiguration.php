<?php

declare(strict_types = 1);

namespace Benchmarks\Support;

use SineMacula\RouteLinter\Contracts\RuleConfiguration;
use SineMacula\RouteLinter\Dto\RuleConfig;

/**
 * In-memory rule configuration that returns a fixed config.
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
    public function __construct(

        /** The pre-built rule configuration returned on every load */
        private RuleConfig $config,
    ) {}

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
