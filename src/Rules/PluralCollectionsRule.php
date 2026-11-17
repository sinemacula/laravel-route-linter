<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\Inflector;
use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\Support\CollectionSegments;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R4: collection segments are plural.
 *
 * Collection segments are located by {@see CollectionSegments}; configured
 * uncountables count as plural.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class PluralCollectionsRule implements Rule
{
    /**
     * Create a new plural collections rule.
     *
     * @param  \SineMacula\RouteLinter\Contracts\Inflector  $inflector
     * @param  \SineMacula\RouteLinter\Rules\Support\CollectionSegments  $collections
     */
    public function __construct(

        /** Decides whether a segment is plural */
        private Inflector $inflector,

        /** Locates the route's collection segments */
        private CollectionSegments $collections = new CollectionSegments,
    ) {}

    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return 'R4';
    }

    /**
     * Return the severity tier for this rule.
     *
     * @return \SineMacula\RouteLinter\Enums\Severity
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

        foreach ($this->collections->indexes($route, $config) as $index) {
            $segment = $route->segments[$index];

            if (in_array($segment, $config->uncountables, true) || $this->inflector->isPlural($segment)) {
                continue;
            }

            $violations[] = new Violation(
                ruleId: $this->id(),
                severity: $this->severity(),
                routeIdentity: $route->identity(),
                offendingSurface: $segment,
                remediationHint: null,
            );
        }

        return $violations;
    }
}
