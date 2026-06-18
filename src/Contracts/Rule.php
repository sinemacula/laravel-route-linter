<?php

namespace SineMacula\RouteLinter\Contracts;

use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Severity;
use SineMacula\RouteLinter\Violation;

/**
 * Domain contract for a single route-linting rule.
 *
 * Each rule carries a stable identifier, a fixed severity tier, and an
 * inspection method that maps one normalised route plus the active rule
 * configuration to zero or more violation value objects. Rules are pure: they
 * read their inputs and return findings without side effects.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
interface Rule
{
    /**
     * Stable rule identifier, e.g. 'R1', used for ordering and reporting.
     *
     * @return string
     */
    public function id(): string;

    /**
     * The severity this rule emits (error gates CI; warning is reported only).
     *
     * @return \SineMacula\RouteLinter\Severity
     */
    public function severity(): Severity;

    /**
     * Inspect one normalised route and return zero or more violations.
     *
     * Rules are pure and side-effect free. Any exception a rule raises
     * propagates uncaught to the caller (fail-loud): a misbehaving rule
     * surfaces as an error rather than silently passing a route through the
     * gate.
     *
     * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    public function inspect(NormalisedRoute $route, RuleConfig $config): array;
}
