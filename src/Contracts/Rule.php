<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Contracts;

use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;

/**
 * A rule that inspects one route at a time.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
interface Rule
{
    /**
     * Return the stable rule identifier, e.g. 'R1'.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Return the severity this rule emits.
     *
     * @return \SineMacula\RouteLinter\Enums\Severity
     */
    public function severity(): Severity;

    /**
     * Inspect one route and return its violations.
     *
     * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    public function inspect(NormalisedRoute $route, RuleConfig $config): array;
}
