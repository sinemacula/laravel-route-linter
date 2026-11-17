<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Contracts;

use SineMacula\RouteLinter\Dto\RuleConfig;

/**
 * Supplies the validated rule configuration.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
interface RuleConfiguration
{
    /**
     * Load and validate the rule configuration.
     *
     * @return \SineMacula\RouteLinter\Dto\RuleConfig
     *
     * @throws \SineMacula\RouteLinter\Exceptions\InvalidConfigurationException
     */
    public function load(): RuleConfig;
}
