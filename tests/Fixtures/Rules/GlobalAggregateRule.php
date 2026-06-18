<?php

namespace Tests\Fixtures\Rules;

use SineMacula\RouteLinter\Contracts\AggregateRule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Severity;
use SineMacula\RouteLinter\Violation;

/**
 * Fixture aggregate rule that emits a single table-level finding not attributed
 * to any live route.
 *
 * Its violation carries a synthetic identity that matches no descriptor, so it
 * exercises the path where an aggregate violation cannot be per-route suppressed
 * and is reported directly.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class GlobalAggregateRule implements AggregateRule
{
    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return 'GLOBAL';
    }

    /**
     * Return the severity tier for this rule.
     *
     * @return \SineMacula\RouteLinter\Severity
     */
    #[\Override]
    public function severity(): Severity
    {
        return Severity::WARNING;
    }

    /**
     * Emit two findings attributed to a synthetic, non-route identity.
     *
     * Two findings (not one) so that exhausting the loop matters: the consuming
     * pipeline must process every unattributed violation, not just the first.
     *
     * @param  array<int, \SineMacula\RouteLinter\NormalisedRoute>  $routes
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    #[\Override]
    public function inspect(array $routes, RuleConfig $config): array
    {
        return [
            new Violation(
                ruleId: $this->id(),
                severity: $this->severity(),
                routeIdentity: 'route-table',
                offendingSurface: 'global-finding-1',
                remediationHint: null,
            ),
            new Violation(
                ruleId: $this->id(),
                severity: $this->severity(),
                routeIdentity: 'route-table',
                offendingSurface: 'global-finding-2',
                remediationHint: null,
            ),
        ];
    }
}
