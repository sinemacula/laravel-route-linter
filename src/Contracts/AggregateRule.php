<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Contracts;

use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;

/**
 * A rule that inspects the whole route set at once, after the per-route pass.
 *
 * Attribute each violation to a route's identity() so per-route suppression
 * applies; one matching no live route is reported unsuppressed.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
interface AggregateRule
{
    /**
     * Return the stable rule identifier, e.g. 'R6'.
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
     * Inspect the whole route set and return its violations.
     *
     * @param  array<int, \SineMacula\RouteLinter\NormalisedRoute>  $routes
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    public function inspect(array $routes, RuleConfig $config): array;
}
