<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter;

use SineMacula\RouteLinter\Contracts\RouteSource;
use SineMacula\RouteLinter\Contracts\RuleConfiguration;
use SineMacula\RouteLinter\Dto\RouteDescriptor;
use SineMacula\RouteLinter\Dto\RouteSuppression;

/**
 * Lints the application's routes and reports the unsuppressed findings.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class LintRoutes
{
    /**
     * Create a new lint-routes use case.
     *
     * @param  \SineMacula\RouteLinter\Contracts\RouteSource  $routeSource
     * @param  \SineMacula\RouteLinter\Contracts\RuleConfiguration  $configuration
     * @param  \SineMacula\RouteLinter\RouteLintEngine  $engine
     */
    public function __construct(

        /** The source of the routes to lint */
        private RouteSource $routeSource,

        /** The source of the rule configuration */
        private RuleConfiguration $configuration,

        /** The engine that runs the rules */
        private RouteLintEngine $engine,
    ) {}

    /**
     * Lint every app-owned route and return the report.
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
     * Suppress aggregate violations through the route they are attributed to,
     * returning the inline suppressions that fired.
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
     * Record the allowlist entries that matched no route or suppressed nothing.
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
     * Report the violations no inline suppression or allowlist entry covers,
     * returning the inline suppressions that fired, keyed by object id.
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
     * Normalise a descriptor into the route the rules inspect.
     *
     * @param  \SineMacula\RouteLinter\Dto\RouteDescriptor  $descriptor
     * @return \SineMacula\RouteLinter\NormalisedRoute
     */
    private function normalise(RouteDescriptor $descriptor): NormalisedRoute
    {
        $segments   = explode('/', $descriptor->uri);
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
     * Return the names of the parameter segments, without braces.
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
