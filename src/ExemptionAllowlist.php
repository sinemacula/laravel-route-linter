<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter;

use SineMacula\RouteLinter\Dto\AllowlistEntry;

/**
 * Applies the configured waivers and tracks the ones that went unused.
 *
 * Call observe() for every route before suppresses(), so an entry matching a
 * clean route is not reported as matching nothing.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class ExemptionAllowlist
{
    /** @var array<int, bool> Index positions of entries whose pattern matched at least one live route */
    private array $matched = [];

    /** @var array<int, bool> Index positions of entries that suppressed at least one violation */
    private array $used = [];

    /**
     * Create a new exemption allowlist.
     *
     * @param  array<int, \SineMacula\RouteLinter\Dto\AllowlistEntry>  $entries
     */
    public function __construct(

        /** The configured waivers */
        private readonly array $entries,
    ) {}

    /**
     * Mark every entry matching the route as matched.
     *
     * @param  string|null  $routeName
     * @param  string  $uri
     * @return void
     */
    public function observe(?string $routeName, string $uri): void
    {
        foreach ($this->entries as $index => $entry) {
            if (!$this->matches($entry, $routeName, $uri)) {
                continue;
            }

            $this->matched[$index] = true;
        }
    }

    /**
     * Determine whether an entry waives the rule on the route, marking it used.
     *
     * @param  string|null  $routeName
     * @param  string  $uri
     * @param  string  $ruleId
     * @return bool
     */
    public function suppresses(?string $routeName, string $uri, string $ruleId): bool
    {
        foreach ($this->entries as $index => $entry) {
            if (!$this->matches($entry, $routeName, $uri)) {
                continue;
            }

            $this->matched[$index] = true;

            if ($entry->covers($ruleId)) {
                $this->used[$index] = true;

                return true;
            }
        }

        return false;
    }

    /**
     * Return the match keys of entries that matched no route, sorted.
     *
     * @return array<int, string>
     */
    public function unmatched(): array
    {
        $stale = [];

        foreach ($this->entries as $index => $entry) {
            if ($this->matched[$index] ?? false) {
                continue;
            }

            $stale[] = $entry->match;
        }

        sort($stale);

        return $stale;
    }

    /**
     * Describe the entries that matched a route but suppressed nothing, sorted.
     *
     * @return array<int, string>
     */
    public function unused(): array
    {
        $stale = [];

        foreach ($this->entries as $index => $entry) {
            if (!($this->matched[$index] ?? false) || ($this->used[$index] ?? false)) {
                continue;
            }

            $stale[] = sprintf('%s (suppressed nothing): %s', $entry->match, $entry->reason);
        }

        sort($stale);

        return $stale;
    }

    /**
     * Determine whether an entry names the route or matches its URI pattern.
     *
     * @param  \SineMacula\RouteLinter\Dto\AllowlistEntry  $entry
     * @param  string|null  $routeName
     * @param  string  $uri
     * @return bool
     */
    private function matches(AllowlistEntry $entry, ?string $routeName, string $uri): bool
    {
        if ($routeName !== null && $routeName === $entry->match) {
            return true;
        }

        return fnmatch($entry->match, $uri);
    }
}
