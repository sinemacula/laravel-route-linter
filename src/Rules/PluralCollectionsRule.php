<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\Inflector;
use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R4: Plural-collections enforcement.
 *
 * Flags every literal URI segment that represents a resource collection but is
 * not plural. A segment is treated as a collection when it is immediately
 * followed by a route-parameter segment (`{param}`), or when it is the last
 * literal segment of the path (a top-level collection). Parameter segments and
 * empty segments are never evaluated. Uncountable nouns configured in the
 * active rule config are treated as plural-safe and never flagged.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class PluralCollectionsRule implements Rule
{
    /**
     * Create a new plural collections rule.
     *
     * @param  \SineMacula\RouteLinter\Contracts\Inflector  $inflector
     */
    public function __construct(

        /**
         * Inflector port used to test whether a collection
         * segment is plural.
         */
        private readonly Inflector $inflector,
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
        $segments   = $route->segments;
        $total      = count($segments);

        for ($i = 0; $i < $total; $i++) {
            $segment = $segments[$i];

            if ($segment === '' || str_starts_with($segment, '{')) {
                continue;
            }

            if (!$this->isCollectionSegment($i, $segments)) {
                continue;
            }

            if (in_array($segment, $config->uncountables, true)) {
                continue;
            }

            if ($this->inflector->isPlural($segment)) {
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

    /**
     * Determine whether the segment at the given index is a collection segment.
     *
     * A segment is a collection when it is immediately followed by a
     * route-parameter segment, or when every subsequent segment is either empty
     * or a route parameter (i.e. it is the final literal segment).
     *
     * @param  int  $index
     * @param  array<int, string>  $segments
     * @return bool
     */
    private function isCollectionSegment(int $index, array $segments): bool
    {
        $next = $segments[$index + 1] ?? null;

        if ($next !== null && str_starts_with($next, '{')) {
            return true;
        }

        // The segment is the final literal segment when every subsequent
        // segment is either empty or a route parameter.
        for ($j = $index + 1; $j < count($segments); $j++) {
            if ($segments[$j] !== '' && !str_starts_with($segments[$j], '{')) {
                return false;
            }
        }

        return true;
    }
}
