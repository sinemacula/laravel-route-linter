<?php

namespace SineMacula\RouteLinter;

/**
 * Mutable verdict aggregate for a route-linting run.
 *
 * Collects violations and stale-waiver entries as they are discovered, then
 * exposes them in a deterministic total order so two runs over the same inputs
 * produce byte-identical output. The error gate (`hasErrors()`) is driven
 * solely by ERROR-severity violations; WARNING-severity findings and stale
 * waivers are surfaced but do not raise the gate.
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
     * Record an allowlist entry that matched no live route.
     *
     * @param  string  $entry
     * @return void
     */
    public function addStaleWaiver(string $entry): void
    {
        $this->staleWaivers[] = $entry;
    }

    /**
     * Return ERROR-severity violations in a deterministic total order.
     *
     * Sorted by: routeIdentity ASC, then ruleId ASC, then offendingSurface ASC.
     *
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    public function errors(): array
    {
        return $this->sorted(Severity::Error);
    }

    /**
     * Return WARNING-severity violations in a deterministic total order.
     *
     * Sorted by: routeIdentity ASC, then ruleId ASC, then offendingSurface ASC.
     *
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    public function warnings(): array
    {
        return $this->sorted(Severity::Warning);
    }

    /**
     * Return stale allowlist entries sorted ascending.
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
     * Determine whether the report contains any ERROR-severity violations.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return $this->errors() !== [];
    }

    /**
     * Filter violations by severity and sort them by the deterministic
     * composite key.
     *
     * The composite key is: routeIdentity ASC, then ruleId ASC, then
     * offendingSurface ASC. This total order guarantees that two runs over the
     * same inputs produce byte-identical arrays regardless of insertion order
     * (NFR-01).
     *
     * @param  \SineMacula\RouteLinter\Severity  $severity
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
