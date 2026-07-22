<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter;

use SineMacula\RouteLinter\Contracts\RouteSource;
use SineMacula\RouteLinter\Contracts\RuleConfiguration;
use SineMacula\RouteLinter\Dto\RouteDescriptor;
use SineMacula\RouteLinter\Dto\RouteSuppression;

/**
 * Application use case that composes the route-linting ports and runs the
 * engine.
 *
 * Sources app-owned routes via the RouteSource port, loads rule configuration
 * via the RuleConfiguration port, normalises each descriptor into a
 * NormalisedRoute, runs the RouteLintEngine over it, suppresses exempt
 * violations via the ExemptionAllowlist, and returns a populated
 * RouteLintReport. Stale allowlist entries - entries that matched no live route
 * - are recorded on the report.
 *
 * This class carries no framework dependency; all I/O is mediated through the
 * injected ports. Determinism (NFR-01) is owned by RouteLintReport, which
 * returns violations and stale entries in a stable total order regardless of
 * the order routes are inspected, so two runs over the same route table and
 * configuration produce byte-identical verdicts.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class LintRoutes
{
    /**
     * Create a new lint-routes use case.
     *
     * @param  \SineMacula\RouteLinter\Contracts\RouteSource  $routeSource
     * @param  \SineMacula\RouteLinter\Contracts\RuleConfiguration  $configuration
     * @param  \SineMacula\RouteLinter\RouteLintEngine  $engine
     */
    public function __construct(

        /** Port that yields the app-owned route descriptors to lint */
        private readonly RouteSource $routeSource,

        /** Port that loads the active rule-configuration bundle */
        private readonly RuleConfiguration $configuration,

        /** Engine that runs the ordered rule set over each route */
        private readonly RouteLintEngine $engine,
    ) {}

    /**
     * Run the linter over every app-owned route and return the verdict.
     *
     * Steps:
     * 1. Load the RuleConfig (may throw InvalidConfigurationException;
     *    propagates to caller).
     * 2. Build the ExemptionAllowlist from config exemptions.
     * 3. Source app-owned RouteDescriptors and observe every one so allowlist
     *    pattern-match tracking covers all live routes.
     * 4. Normalise every descriptor, keeping each paired with its descriptor
     *    for suppression.
     * 5. Per-route pass: run the per-route rules over each route and apply
     *    suppression.
     * 6. Aggregate pass: run the cross-route rules over the whole route set and
     *    apply suppression, attributing each violation to its offending route;
     *    a violation matching no live route is reported unsuppressed.
     * 7. Record stale inline suppressions (those that fired on no violation in
     *    either pass), unmatched allowlist entries, and unused allowlist
     *    entries.
     *
     * @return \SineMacula\RouteLinter\RouteLintReport
     *
     * @throws \SineMacula\RouteLinter\Exceptions\InvalidConfigurationException
     */
    public function lint(): RouteLintReport
    {
        $config    = $this->configuration->load();
        $allowlist = new ExemptionAllowlist($config->exemptions);
        $report    = new RouteLintReport;

        $descriptors = $this->routeSource->appRoutes();

        foreach ($descriptors as $descriptor) {
            $allowlist->observe($descriptor->name, $descriptor->uri);
        }

        $pairs                = [];
        $routes               = [];
        $descriptorByIdentity = [];

        foreach ($descriptors as $descriptor) {
            $route                                    = $this->normalise($descriptor);
            $pairs[]                                  = [$descriptor, $route];
            $routes[]                                 = $route;
            $descriptorByIdentity[$route->identity()] = $descriptor;
        }

        $inlineUsed = [];

        foreach ($pairs as [$descriptor, $route]) {
            $violations = $this->engine->inspect($route, $config);
            $inlineUsed += $this->applyViolations($descriptor, $allowlist, $report, $violations);
        }

        $inlineUsed += $this->suppressAggregate(
            $this->engine->inspectAll($routes, $config),
            $descriptorByIdentity,
            $allowlist,
            $report,
        );

        $this->recordStaleSuppressions($pairs, $inlineUsed, $report);
        $this->recordStaleAllowlist($allowlist, $report);

        return $report;
    }

    /**
     * Apply suppression to the aggregate-pass violations.
     *
     * Each violation is attributed to the route whose identity it carries, so
     * the same inline + allowlist suppression as the per-route pass applies. A
     * violation matching no live route cannot be per-route suppressed and is
     * reported directly. Returns the inline suppressions that fired.
     *
     * @param  array<int, \SineMacula\RouteLinter\Violation>  $violations
     * @param  array<string, \SineMacula\RouteLinter\Dto\RouteDescriptor>  $descriptorByIdentity
     * @param  \SineMacula\RouteLinter\ExemptionAllowlist  $allowlist
     * @param  \SineMacula\RouteLinter\RouteLintReport  $report
     * @return array<int, true>
     */
    private function suppressAggregate(array $violations, array $descriptorByIdentity, ExemptionAllowlist $allowlist, RouteLintReport $report): array
    {
        $inlineUsed = [];

        foreach ($violations as $violation) {
            $descriptor = $descriptorByIdentity[$violation->routeIdentity] ?? null;

            if ($descriptor === null) {
                $report->addViolation($violation);

                continue;
            }

            $inlineUsed += $this->applyViolations($descriptor, $allowlist, $report, [$violation]);
        }

        return $inlineUsed;
    }

    /**
     * Record inline suppressions that fired on no violation in either pass.
     *
     * @param  array<int, array{0: \SineMacula\RouteLinter\Dto\RouteDescriptor, 1: \SineMacula\RouteLinter\NormalisedRoute}>  $pairs
     * @param  array<int, true>  $inlineUsed
     * @param  \SineMacula\RouteLinter\RouteLintReport  $report
     * @return void
     */
    private function recordStaleSuppressions(array $pairs, array $inlineUsed, RouteLintReport $report): void
    {
        foreach ($pairs as [$descriptor, $route]) {
            foreach ($descriptor->suppressions as $suppression) {
                if ($inlineUsed[spl_object_id($suppression)] ?? false) {
                    continue;
                }

                $rules = $suppression->rules === [] ? 'all rules' : implode(', ', $suppression->rules);
                $report->addStaleWaiver(sprintf(
                    '%s (suppressed nothing, rules: %s): %s',
                    $route->identity(),
                    $rules,
                    $suppression->reason,
                ));
            }
        }
    }

    /**
     * Record allowlist entries that matched no live route or suppressed
     * nothing.
     *
     * @param  \SineMacula\RouteLinter\ExemptionAllowlist  $allowlist
     * @param  \SineMacula\RouteLinter\RouteLintReport  $report
     * @return void
     */
    private function recordStaleAllowlist(ExemptionAllowlist $allowlist, RouteLintReport $report): void
    {
        foreach ($allowlist->unmatched() as $key) {
            $report->addStaleWaiver($key);
        }

        foreach ($allowlist->unused() as $entry) {
            $report->addStaleWaiver($entry);
        }
    }

    /**
     * Apply per-rule suppression to a set of violations for one descriptor.
     *
     * For each violation, checks inline suppressions first (in declaration
     * order), then falls back to the config allowlist. Violations that pass
     * both checks are added to the report. Returns an object-ID map of inline
     * suppressions that fired on at least one violation, keyed by
     * `spl_object_id`.
     *
     * @param  \SineMacula\RouteLinter\Dto\RouteDescriptor  $descriptor
     * @param  \SineMacula\RouteLinter\ExemptionAllowlist  $allowlist
     * @param  \SineMacula\RouteLinter\RouteLintReport  $report
     * @param  array<int, \SineMacula\RouteLinter\Violation>  $violations
     * @return array<int, true>
     */
    private function applyViolations(RouteDescriptor $descriptor, ExemptionAllowlist $allowlist, RouteLintReport $report, array $violations): array
    {
        $inlineUsed = [];

        foreach ($violations as $violation) {
            $suppression = $this->matchingSuppression($descriptor, $violation);

            if ($suppression !== null) {
                $inlineUsed[spl_object_id($suppression)] = true;

                continue;
            }

            if ($allowlist->suppresses($descriptor->name, $descriptor->uri, $violation->ruleId)) {
                continue;
            }

            $report->addViolation($violation);
        }

        return $inlineUsed;
    }

    /**
     * Find the first inline suppression that covers the given violation.
     *
     * @param  \SineMacula\RouteLinter\Dto\RouteDescriptor  $descriptor
     * @param  \SineMacula\RouteLinter\Violation  $violation
     * @return \SineMacula\RouteLinter\Dto\RouteSuppression|null
     */
    private function matchingSuppression(RouteDescriptor $descriptor, Violation $violation): ?RouteSuppression
    {
        foreach ($descriptor->suppressions as $suppression) {
            if ($suppression->covers($violation->ruleId)) {
                return $suppression;
            }
        }

        return null;
    }

    /**
     * Normalise a RouteDescriptor into a NormalisedRoute.
     *
     * Splits the URI on `/` (preserving empty segments for slash-sanity
     * detection) and extracts parameter names by stripping the `{` and `}`
     * braces from any segment that starts with `{`.
     *
     * @param  \SineMacula\RouteLinter\Dto\RouteDescriptor  $descriptor
     * @return \SineMacula\RouteLinter\NormalisedRoute
     */
    private function normalise(RouteDescriptor $descriptor): NormalisedRoute
    {
        $segments   = $this->splitSegments($descriptor->uri);
        $parameters = $this->extractParameters($segments);

        return new NormalisedRoute(
            uri: $descriptor->uri,
            methods: $descriptor->methods,
            name: $descriptor->name,
            segments: $segments,
            parameters: $parameters,
            handler: $descriptor->handler,
            middleware: $descriptor->middleware,
        );
    }

    /**
     * Split a URI string into segments on the `/` delimiter.
     *
     * Empty segments are preserved so that trailing-slash and duplicate-slash
     * defects remain detectable by the SlashSanityRule.
     *
     * @param  string  $uri
     * @return array<int, string>
     */
    private function splitSegments(string $uri): array
    {
        return explode('/', $uri);
    }

    /**
     * Extract route parameter names from a list of URI segments.
     *
     * A segment is a route parameter when it starts with `{`. The surrounding
     * `{` and `}` braces are stripped from the returned names.
     *
     * @param  array<int, string>  $segments
     * @return array<int, string>
     */
    private function extractParameters(array $segments): array
    {
        $parameters = [];

        foreach ($segments as $segment) {
            if (!str_starts_with($segment, '{')) {
                continue;
            }

            $parameters[] = trim($segment, '{}');
        }

        return $parameters;
    }
}
