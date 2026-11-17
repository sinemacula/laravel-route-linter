<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter;

use SineMacula\RouteLinter\Enums\Severity;

/**
 * The findings of a lint run, returned in a stable order.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class RouteLintReport
{
    /** @var array<int, \SineMacula\RouteLinter\Violation> */
    private array $violations = [];

    /** @var array<int, string> */
    private array $staleWaivers = [];

    /**
     * Add a violation to the report.
     *
     * @param  \SineMacula\RouteLinter\Violation  $violation
     * @return void
     */
    public function addViolation(Violation $violation): void
    {
        $this->violations[] = $violation;
    }

    /**
     * Record a waiver that matched no route or suppressed nothing.
     *
     * @param  string  $entry
     * @return void
     */
    public function addStaleWaiver(string $entry): void
    {
        $this->staleWaivers[] = $entry;
    }

    /**
     * Return the error violations in stable order.
     *
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    public function errors(): array
    {
        return $this->sorted(Severity::ERROR);
    }

    /**
     * Return the warning violations in stable order.
     *
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    public function warnings(): array
    {
        return $this->sorted(Severity::WARNING);
    }

    /**
     * Return the stale waivers, sorted.
     *
     * @return array<int, string>
     */
    public function staleWaivers(): array
    {
        $entries = $this->staleWaivers;
        sort($entries);

        return $entries;
    }

    /**
     * Determine whether the report contains any error violation.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return $this->errors() !== [];
    }

    /**
     * Return the violations of a severity, sorted by route, rule and surface.
     *
     * @param  \SineMacula\RouteLinter\Enums\Severity  $severity
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    private function sorted(Severity $severity): array
    {
        $filtered = array_filter(
            $this->violations,
            fn (Violation $violation) => $violation->severity === $severity,
        );

        usort($filtered, fn (Violation $a, Violation $b): int => $a->routeIdentity <=> $b->routeIdentity
                ?: $a->ruleId                                                      <=> $b->ruleId
                ?: $a->offendingSurface                                            <=> $b->offendingSurface);

        return $filtered;
    }
}
