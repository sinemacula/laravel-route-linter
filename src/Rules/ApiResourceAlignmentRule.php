<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R9: apiResource-alignment warning.
 *
 * Flags HTML-only form actions (`create` and `edit`) that appear as the final
 * literal segment of a URI on an API surface. These segments correspond to
 * Laravel's `create` and `edit` resource actions, which render HTML forms and
 * have no valid place in a JSON API. The check is restricted to the final
 * literal segment to keep precision high and avoid false positives on resource
 * names that happen to contain these strings in a non-terminal position.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class ApiResourceAlignmentRule implements Rule
{
    /** @var array<int, string> HTML-only action segments that must not appear as the final literal URI segment on an API surface. */
    private const array HTML_ONLY_ACTIONS = ['create', 'edit'];

    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return 'R9';
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
        $lastLiteral = $this->lastLiteralSegment($route->segments);

        if ($lastLiteral === null || !in_array($lastLiteral, self::HTML_ONLY_ACTIONS, true)) {
            return [];
        }

        return [
            new Violation(
                ruleId: $this->id(),
                severity: $this->severity(),
                routeIdentity: $route->identity(),
                offendingSurface: $lastLiteral,
                remediationHint: null,
            ),
        ];
    }

    /**
     * Return the final non-empty, non-parameter segment, or null when none
     * exists.
     *
     * @param  array<int, string>  $segments
     * @return string|null
     */
    private function lastLiteralSegment(array $segments): ?string
    {
        foreach (array_reverse($segments) as $segment) {
            if ($segment === '' || str_starts_with($segment, '{')) {
                continue;
            }

            return $segment;
        }

        return null;
    }
}
