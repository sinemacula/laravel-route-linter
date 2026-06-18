<?php

namespace Tests\Fixtures\Rules;

use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Severity;
use SineMacula\RouteLinter\Violation;

/**
 * Fixture rule that echoes a route's extracted parameter names.
 *
 * Used to prove that custom rules are resolved from the `route-linter.rules`
 * config (rule-set extensibility) and that NormalisedRoute::$parameters is
 * populated for custom rule authors. It emits one warning per route whose
 * offending surface is the comma-joined parameter names the rule observed.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class ParameterEchoRule implements Rule
{
    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return 'TEST-PARAMS';
    }

    /**
     * Return the severity tier for this rule.
     *
     * @return \SineMacula\RouteLinter\Severity
     */
    #[\Override]
    public function severity(): Severity
    {
        return Severity::Warning;
    }

    /**
     * Emit a single warning whose surface is the route's parameter names.
     *
     * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    #[\Override]
    public function inspect(NormalisedRoute $route, RuleConfig $config): array
    {
        return [
            new Violation(
                ruleId: $this->id(),
                severity: $this->severity(),
                routeIdentity: $route->identity(),
                offendingSurface: implode(',', $route->parameters),
                remediationHint: null,
            ),
        ];
    }
}
