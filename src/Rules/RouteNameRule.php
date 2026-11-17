<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R8: route names follow `{resource}.{action}` with a RESTful action.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class RouteNameRule implements Rule
{
    /** @var array<int, string> The RESTful resource actions. */
    private const array ALLOWED_ACTIONS = ['index', 'show', 'store', 'update', 'destroy', 'create', 'edit'];

    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return 'R8';
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
     * Inspect one normalised route and return zero or more violations.
     *
     * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    #[\Override]
    public function inspect(NormalisedRoute $route, RuleConfig $config): array
    {
        if ($route->name === null) {
            return [];
        }

        return $this->conformsToConvention($route->name)
            ? []
            : [
                new Violation(
                    ruleId: $this->id(),
                    severity: $this->severity(),
                    routeIdentity: $route->identity(),
                    offendingSurface: $route->name,
                    remediationHint: null,
                ),
            ];
    }

    /**
     * Determine whether a route name follows `{resource}.{action}`.
     *
     * @param  string  $name
     * @return bool
     */
    private function conformsToConvention(string $name): bool
    {
        $lastDot = strrpos($name, '.');

        if ($lastDot === false || $lastDot === 0) {
            return false;
        }

        return in_array(substr($name, $lastDot + 1), self::ALLOWED_ACTIONS, true);
    }
}
