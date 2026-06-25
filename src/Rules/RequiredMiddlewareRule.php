<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R10: required middleware.
 *
 * Flags routes that match a configured URI pattern but do not declare
 * a required middleware. The policy lives in
 * `route-linter.required_middleware`, keyed by
 * `fnmatch` URI pattern; each pattern maps to the middleware names a matching
 * route must carry. Matching is an exact token comparison against the route's
 * gathered middleware, so parameterised middleware must be configured exactly
 * (e.g. `auth:sanctum`). Ships empty, so the rule is a no-op until configured.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class RequiredMiddlewareRule implements Rule
{
    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return 'R10';
    }

    /**
     * Return the severity tier for this rule.
     *
     * @return \SineMacula\RouteLinter\Enums\Severity
     */
    #[\Override]
    public function severity(): Severity
    {
        return Severity::WARNING;
    }

    /**
     * Inspect one normalised route and return one violation per
     * missing required middleware across every matching pattern.
     *
     * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    #[\Override]
    public function inspect(NormalisedRoute $route, RuleConfig $config): array
    {
        $violations = [];

        foreach ($config->requiredMiddleware as $pattern => $required) {
            if (!fnmatch($pattern, $route->uri)) {
                continue;
            }

            foreach ($required as $middleware) {
                if (in_array($middleware, $route->middleware, true)) {
                    continue;
                }

                $violations[] = new Violation(
                    ruleId: $this->id(),
                    severity: $this->severity(),
                    routeIdentity: $route->identity(),
                    offendingSurface: $middleware,
                    remediationHint: sprintf('add the `%s` middleware (route matches `%s`)', $middleware, $pattern),
                );
            }
        }

        return $violations;
    }
}
