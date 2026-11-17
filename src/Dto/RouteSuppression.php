<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Dto;

/**
 * A suppression declared on a route with the `ignoreRouteLint` macro.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class RouteSuppression
{
    /**
     * Create a new route suppression.
     *
     * @param  list<string>  $rules
     * @param  string  $reason
     */
    public function __construct(

        /** The rule IDs covered by this suppression; empty means all rules */
        public array $rules,

        /** The written justification for the suppression */
        public string $reason,
    ) {}

    /**
     * Determine whether the suppression covers the given rule.
     *
     * @param  string  $ruleId
     * @return bool
     */
    public function covers(string $ruleId): bool
    {
        return $this->rules === [] || in_array($ruleId, $this->rules, true);
    }
}
