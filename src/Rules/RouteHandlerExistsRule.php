<?php

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Severity;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R12: route handler exists.
 *
 * Flags controller-backed routes whose handler class or method cannot be
 * resolved - a route pointing at a renamed or deleted controller action, which
 * would fail at request time. The handler is a `Class@method` string, or a bare
 * `Class` for an invokable controller (resolved against `__invoke`). Closure
 * routes carry no handler and are skipped.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class RouteHandlerExistsRule implements Rule
{
    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return 'R12';
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
        if ($route->handler === null) {
            return [];
        }

        [$class, $method] = $this->split($route->handler);

        // method_exists() resolves the class (autoloading it) and returns false
        // for both an unknown class and a known class missing the method.
        if (method_exists($class, $method)) {
            return [];
        }

        return [
            new Violation(
                ruleId: $this->id(),
                severity: $this->severity(),
                routeIdentity: $route->identity(),
                offendingSurface: $route->handler,
                remediationHint: null,
            ),
        ];
    }

    /**
     * Split a handler string into its class and method parts, defaulting the
     * method to `__invoke` when the handler is a bare invokable class.
     *
     * @param  string  $handler
     * @return array{0: string, 1: string}
     */
    private function split(string $handler): array
    {
        $parts = explode('@', $handler);

        return [$parts[0], $parts[1] ?? '__invoke'];
    }
}
