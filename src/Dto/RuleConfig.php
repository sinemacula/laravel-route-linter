<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Dto;

/**
 * Inbound configuration bundle from the rule-configuration adapter.
 *
 * Carries the strictly-separate config surfaces handed to every rule: the verb
 * denylist (action verbs that flag a path segment), the per-verb
 * remediation-hint map, the exemption-allowlist entries, the inflector
 * uncountables, the maximum nesting depth, and the per-pattern required
 * middleware map. Surfaces are kept separate - verbDenylist, exemptions, and
 * uncountables never share storage - so callers can tune each independently.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class RuleConfig
{
    /**
     * Create a new rule config.
     *
     * @param  array<int, string>  $verbDenylist
     * @param  array<string, string>  $remediationHints
     * @param  array<int, \SineMacula\RouteLinter\Dto\AllowlistEntry>  $exemptions
     * @param  array<int, string>  $uncountables
     * @param  int  $nestingMaxDepth
     * @param  array<string, array<int, string>>  $requiredMiddleware
     */
    public function __construct(

        /** Action verbs that flag a path segment as non-RESTful */
        public array $verbDenylist,

        /** Per-verb RESTful-rewrite hints, keyed by the denylisted verb */
        public array $remediationHints,

        /** Per-route exemption waivers; ships empty by default */
        public array $exemptions,

        /** Inflector uncountables honoured by the plural and verb rules */
        public array $uncountables,

        /**
         * Maximum collection nesting levels before the
         * nesting-depth rule (R11) warns.
         */
        public int $nestingMaxDepth = 3,

        /**
         * Required middleware keyed by URI pattern, honoured
         * by the required-middleware rule (R10).
         */
        public array $requiredMiddleware = [],
    ) {}
}
