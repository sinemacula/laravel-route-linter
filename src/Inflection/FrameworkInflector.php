<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Inflection;

use Illuminate\Support\Str;
use SineMacula\RouteLinter\Contracts\Inflector;

/**
 * Inflector backed by the framework's Str helpers.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class FrameworkInflector implements Inflector
{
    /**
     * Create a new framework inflector.
     *
     * @param  array<int, string>  $uncountables
     */
    public function __construct(

        /** Words treated as already-plural, bypassing singularisation */
        private array $uncountables = [],
    ) {}

    /**
     * Return the singular form of a word; uncountables are left unchanged.
     *
     * @param  string  $word
     * @return string
     */
    #[\Override]
    public function singular(string $word): string
    {
        if ($word === '' || in_array(strtolower($word), $this->uncountables, true)) {
            return $word;
        }

        return Str::singular($word);
    }

    /**
     * Determine whether a word is plural; uncountables count as plural.
     *
     * @param  string  $word
     * @return bool
     */
    #[\Override]
    public function isPlural(string $word): bool
    {
        if ($word === '') {
            return false;
        }

        if (in_array(strtolower($word), $this->uncountables, true)) {
            return true;
        }

        return Str::singular($word) !== $word;
    }
}
