<?php

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Severity;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R3: Lowercase-only segment enforcement.
 *
 * Flags any literal URI segment that contains one or more uppercase ASCII
 * letters (A–Z). Route-parameter segments (those wrapped in `{...}`) and empty
 * segments are ignored.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class LowercaseRule implements Rule
{
    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return 'R3';
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
     * Inspect one normalised route and return zero or more violations.
     *
     * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    #[\Override]
    public function inspect(NormalisedRoute $route, RuleConfig $config): array
    {
        $violations = [];

        foreach ($route->segments as $segment) {
            if ($segment === '' || str_starts_with($segment, '{')) {
                continue;
            }

            if ($segment !== strtolower($segment)) {
                $violations[] = new Violation(
                    ruleId: $this->id(),
                    severity: $this->severity(),
                    routeIdentity: $route->identity(),
                    offendingSurface: $segment,
                    remediationHint: null,
                );
            }
        }

        return $violations;
    }
}
