<?php

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Severity;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R8: Route-name convention enforcement.
 *
 * Flags any named route whose name does not follow the `{resource}.{action}`
 * convention, where the action (the substring after the last `.`) must be one
 * of the seven canonical RESTful actions: index, show, store, update, destroy,
 * create, edit. Unnamed routes are silently skipped. A name whose resource
 * part (before the last `.`) is empty (e.g. `.index`) is also flagged.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class RouteNameRule implements Rule
{
    /** @var array<int, string> The canonical RESTful actions that are permitted as the final name segment. */
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
     * @return \SineMacula\RouteLinter\Severity
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
     * Determine whether a route name follows the {resource}.{action}
     * convention.
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

        $action   = substr($name, $lastDot + 1);
        $resource = substr($name, 0, $lastDot);

        return $resource !== '' && in_array($action, self::ALLOWED_ACTIONS, true);
    }
}
