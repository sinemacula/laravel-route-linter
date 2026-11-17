<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter;

use SineMacula\RouteLinter\Enums\Severity;

/**
 * A finding raised by a rule against one route.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class Violation
{
    /**
     * Create a new violation.
     *
     * @param  string  $ruleId
     * @param  \SineMacula\RouteLinter\Enums\Severity  $severity
     * @param  string  $routeIdentity
     * @param  string  $offendingSurface
     * @param  string|null  $remediationHint
     */
    public function __construct(

        /** Stable rule identifier, e.g. `R1` */
        public string $ruleId,

        /** The severity of the finding */
        public Severity $severity,

        /** The `NormalisedRoute::identity()` of the offending route */
        public string $routeIdentity,

        /** The offending segment, route name, or method+URI shape */
        public string $offendingSurface,

        /** A RESTful-rewrite hint where the rule provides one, or null */
        public ?string $remediationHint,
    ) {}
}
