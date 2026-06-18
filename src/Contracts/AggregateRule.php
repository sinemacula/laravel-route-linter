<?php

namespace SineMacula\RouteLinter\Contracts;

use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Severity;

/**
 * Domain contract for a cross-route ("aggregate") linting rule.
 *
 * Unlike {@see Rule}, which judges one route in isolation, an aggregate rule
 * receives the whole normalised route set at once so it can detect defects that
 * only exist in relation to other routes - duplicate URIs, duplicate names, or
 * any custom whole-table invariant. The engine runs aggregate rules in a single
 * pass after the per-route pass. Rules are pure: they read their inputs and
 * return findings without side effects.
 *
 * Each violation should carry the {@see NormalisedRoute::identity()} of the
 * route it is attributed to, so per-route suppression still applies; a violation
 * whose identity matches no live route is reported unsuppressed.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
interface AggregateRule
{
    /**
     * Stable rule identifier, e.g. 'R6', used for ordering and reporting.
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
     * Inspect the whole normalised route set and return zero or more violations.
     *
     * Rules are pure and side-effect free. Any exception a rule raises
     * propagates uncaught to the caller (fail-loud).
     *
     * @param  array<int, \SineMacula\RouteLinter\NormalisedRoute>  $routes
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    public function inspect(array $routes, RuleConfig $config): array;
}
