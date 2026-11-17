<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Rules\Support;

/**
 * The denylisted verbs, less the allowed words, and their remediation hints.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class VerbDenylist
{
    /** @var array<string, true> The denylisted verbs, lowercase, as keys. */
    private array $verbIndex;

    /**
     * Create a new verb denylist.
     *
     * @param  array<int, string>  $verbs
     * @param  array<string, string>  $hints
     * @param  array<int, string>  $allowed
     */
    public function __construct(

        // Indexed lowercase rather than stored as given
        array $verbs,

        /** Remediation hints, keyed by verb */
        private readonly array $hints,

        // Subtracted from the index rather than stored
        array $allowed = [],
    ) {
        $this->verbIndex = array_diff_key(
            array_fill_keys(array_map('strtolower', $verbs), true),
            array_fill_keys(array_map('strtolower', $allowed), true),
        );
    }

    /**
     * Determine whether a word is a denylisted verb.
     *
     * @param  string  $word
     * @return bool
     */
    public function contains(string $word): bool
    {
        return isset($this->verbIndex[strtolower($word)]);
    }

    /**
     * Return the remediation hint for a verb, if one is configured.
     *
     * @param  string  $word
     * @return string|null
     */
    public function hint(string $word): ?string
    {
        return $this->hints[strtolower($word)] ?? null;
    }
}
