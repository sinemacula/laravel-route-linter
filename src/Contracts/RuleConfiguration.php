<?php

namespace SineMacula\RouteLinter\Contracts;

use SineMacula\RouteLinter\Dto\RuleConfig;

/**
 * Outbound port for supplying the rule-configuration DTO.
 *
 * Implementers read the three strictly-separate config surfaces (verb denylist,
 * exemption allowlist, inflector uncountables) and assemble them
 * into a single {@see RuleConfig} DTO for consumption by the rule engine.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
interface RuleConfiguration
{
    /**
     * Build the rule-config DTO from the three separate config surfaces.
     *
     * @return \SineMacula\RouteLinter\Dto\RuleConfig
     *
     * @throws \SineMacula\RouteLinter\Exceptions\InvalidConfigurationException
     */
    public function load(): RuleConfig;
}
