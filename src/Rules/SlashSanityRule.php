<?php

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Severity;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R5: Slash-sanity enforcement.
 *
 * Flags any route URI that contains a trailing slash or a duplicate (empty)
 * slash. One violation is emitted per offending route regardless of how many
 * defects are present. The root path and empty URIs are excluded from
 * inspection because they are not REST collection URIs.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class SlashSanityRule implements Rule
{
    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return 'R5';
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
        $uri = $route->uri;

        if ($uri === '' || $uri === '/') {
            return [];
        }

        $hasTrailingSlash  = str_ends_with($uri, '/');
        $hasDuplicateSlash = str_contains($uri, '//');

        if (!$hasTrailingSlash && !$hasDuplicateSlash) {
            return [];
        }

        return [
            new Violation(
                ruleId: $this->id(),
                severity: $this->severity(),
                routeIdentity: $route->identity(),
                offendingSurface: $uri,
                remediationHint: null,
            ),
        ];
    }
}
