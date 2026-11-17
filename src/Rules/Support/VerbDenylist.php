<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Rules\Support;

/**
 * Membership oracle and remediation-hint lookup for the verb denylist.
 *
 * Answers two questions: is a given normalised candidate word a denylisted
 * action verb, and what RESTful rewrite hint is associated with it? Allowed
 * words are subtracted from the denylist at construction, so a domain noun that
 * shares a spelling with a verb can be cleared globally without redefining the
 * curated list or adding per-route exemptions.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class VerbDenylist
{
    /** @var array<string, true> Lowercase-indexed set of denylisted verbs, keyed by verb for O(1) lookup. */
    private array $verbIndex;

    /**
     * Create a new verb denylist.
     *
     * @param  array<int, string>  $verbs
     * @param  array<string, string>  $hints
     * @param  array<int, string>  $allowed
     */
    public function __construct(

        // The raw verbs to index for O(1) case-insensitive membership lookup
        array $verbs,

        /** Per-verb RESTful-rewrite hints, keyed by the denylisted verb */
        private readonly array $hints,

        // Words removed from the denylist, matched case-insensitively
        array $allowed = [],
    ) {
        $this->verbIndex = array_diff_key(
            array_fill_keys(array_map('strtolower', $verbs), true),
            array_fill_keys(array_map('strtolower', $allowed), true),
        );
    }

    /**
     * Determine whether the given normalised candidate word is a denylisted
     * action verb.
     *
     * @param  string  $word
     * @return bool
     */
    public function contains(string $word): bool
    {
        return isset($this->verbIndex[strtolower($word)]);
    }

    /**
     * Return the remediation hint for a denylisted verb, or null when none is
     * configured.
     *
     * @param  string  $word
     * @return string|null
     */
    public function hint(string $word): ?string
    {
        return $this->hints[strtolower($word)] ?? null;
    }
}
