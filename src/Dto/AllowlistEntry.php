<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Dto;

/**
 * A configured waiver for the routes matching a name or URI pattern.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class AllowlistEntry
{
    /**
     * Create a new allowlist entry.
     *
     * @param  string  $match
     * @param  string  $reason
     * @param  list<string>  $rules
     */
    public function __construct(

        /** The route name or URI pattern to waive */
        public string $match,

        /** The written justification for the waiver */
        public string $reason,

        /** The rule IDs covered by this entry; empty means all rules */
        public array $rules = [],
    ) {}

    /**
     * Determine whether the entry waives the given rule.
     *
     * @param  string  $ruleId
     * @return bool
     */
    public function covers(string $ruleId): bool
    {
        return $this->rules === [] || in_array($ruleId, $this->rules, true);
    }
}
