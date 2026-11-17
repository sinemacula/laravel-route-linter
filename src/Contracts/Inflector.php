<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Contracts;

/**
 * Word inflection that honours the configured uncountables.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
interface Inflector
{
    /**
     * Return the singular form of a word.
     *
     * @param  string  $word
     * @return string
     */
    public function singular(string $word): string;

    /**
     * Determine whether a word is plural; uncountables count as plural.
     *
     * @param  string  $word
     * @return bool
     */
    public function isPlural(string $word): bool;
}
