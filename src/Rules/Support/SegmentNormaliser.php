<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Rules\Support;

use SineMacula\RouteLinter\Contracts\Inflector;

/**
 * Reduces a URI to the candidate words checked against the verb denylist.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class SegmentNormaliser
{
    /**
     * Create a new segment normaliser.
     *
     * @param  \SineMacula\RouteLinter\Contracts\Inflector  $inflector
     */
    public function __construct(

        /** Singularises the candidate words */
        private Inflector $inflector,
    ) {}

    /**
     * Split the URI's literal segments, other than `api` and version tokens,
     * into lowercase singular words, leaving uncountable words as they are.
     *
     * @param  string  $uri
     * @param  array<int, string>  $uncountables
     * @return array<int, string>
     */
    public function normalise(string $uri, array $uncountables): array
    {
        $segments = array_values(array_filter(explode('/', $uri), fn (string $s): bool => $s !== ''));
        $segments = array_values(array_filter($segments, fn (string $s): bool => !preg_match('/^\{[^}]+}$/', $s)));
        $segments = array_values(array_filter($segments, fn (string $s): bool => !preg_match('/^v\d+$/i', $s) && strtolower($s) !== 'api'));

        $words = array_map('strtolower', array_merge(...array_map($this->decompose(...), $segments)));

        return array_map(
            fn (string $word): string => in_array($word, $uncountables, true)
                ? $word
                : $this->inflector->singular($word),
            $words,
        );
    }

    /**
     * Split a segment into words at camelCase, hyphen, underscore and dot
     * boundaries.
     *
     * @param  string  $segment
     * @return array<int, string>
     */
    private function decompose(string $segment): array
    {
        $spaced = preg_replace('/([a-z])([A-Z])/', '$1 $2', $segment) ?? $segment;
        $parts  = preg_split('/[\s\-_.]+/', $spaced) ?: [];

        return array_filter($parts, fn (string $part): bool => $part !== '');
    }
}
