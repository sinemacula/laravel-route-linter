<?php

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\AggregateRule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Severity;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R6: duplicate route name.
 *
 * Flags named routes that reuse a name already taken by an earlier route. URL
 * generation (`route('name')`) resolves a name to whichever route registered it
 * last, so the earlier route's name is silently unreachable. The violation is
 * attributed to the duplicate (later) occurrence; unnamed routes are ignored.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class DuplicateRouteNameRule implements AggregateRule
{
    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return 'R6';
    }

    /**
     * Return the severity tier for this rule.
     *
     * @return \SineMacula\RouteLinter\Severity
     */
    #[\Override]
    public function severity(): Severity
    {
        return Severity::ERROR;
    }

    /**
     * Inspect the whole route set and return one violation per duplicate name.
     *
     * @param  array<int, \SineMacula\RouteLinter\NormalisedRoute>  $routes
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    #[\Override]
    public function inspect(array $routes, RuleConfig $config): array
    {
        $seen       = [];
        $violations = [];

        foreach ($routes as $route) {
            if ($route->name === null) {
                continue;
            }

            if (!isset($seen[$route->name])) {
                $seen[$route->name] = true;

                continue;
            }

            $violations[] = new Violation(
                ruleId: $this->id(),
                severity: $this->severity(),
                routeIdentity: $route->identity(),
                offendingSurface: $route->name,
                remediationHint: null,
            );
        }

        return $violations;
    }
}
