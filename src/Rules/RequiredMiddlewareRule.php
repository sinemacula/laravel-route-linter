<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R10: routes matching a configured pattern declare its middleware.
 *
 * Middleware is compared as exact tokens, so `auth:sanctum` must be configured
 * in full.
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
     * Return one violation per required middleware the route lacks.
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
