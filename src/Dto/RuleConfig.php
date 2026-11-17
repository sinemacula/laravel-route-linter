<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Dto;

/**
 * The validated configuration handed to every rule.
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
     * @param  array<int, string>  $verbAllowlist
     * @param  array<int, string>  $singletonResources
     */
    public function __construct(

        /** Action verbs that flag a path segment as non-RESTful */
        public array $verbDenylist,

        /** Per-verb RESTful-rewrite hints, keyed by the denylisted verb */
        public array $remediationHints,

        /** Configured waivers */
        public array $exemptions,

        /** Inflector uncountables honoured by the plural and verb rules */
        public array $uncountables,

        /** Maximum collection nesting levels before R11 warns */
        public int $nestingMaxDepth = 3,

        /** Middleware each matching route must declare, keyed by URI pattern (R10) */
        public array $requiredMiddleware = [],

        /** Words removed from the verb denylist, for domain nouns that share a spelling with a verb */
        public array $verbAllowlist = [],

        /** Resource paths whose last segment is a singleton resource */
        public array $singletonResources = [],
    ) {}
}
