<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Output;

use Illuminate\Console\OutputStyle;
use SineMacula\RouteLinter\Contracts\LintReporter;
use SineMacula\RouteLinter\RouteLintReport;
use SineMacula\RouteLinter\Violation;

/**
 * Writes the lint report to the console, one section per severity.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class ConsoleLintReporter implements LintReporter
{
    /**
     * Create a new console lint reporter.
     *
     * @param  \Illuminate\Console\OutputStyle  $output
     */
    public function __construct(

        /** The console output to write to */
        private OutputStyle $output,
    ) {}

    /**
     * Render the report, or a success line when it is clean.
     *
     * @param  \SineMacula\RouteLinter\RouteLintReport  $report
     * @return void
     */
    #[\Override]
    public function report(RouteLintReport $report): void
    {
        $errors       = $report->errors();
        $warnings     = $report->warnings();
        $staleWaivers = $report->staleWaivers();

        if ($errors === [] && $warnings === [] && $staleWaivers === []) {
            $this->output->info('All routes conform to the RESTful conventions.');

            return;
        }

        $this->renderErrors($errors);
        $this->renderWarnings($warnings);
        $this->renderStaleWaivers($staleWaivers);
    }

    /**
     * Render the error violations, if any.
     *
     * @param  array<int, \SineMacula\RouteLinter\Violation>  $errors
     * @return void
     */
    private function renderErrors(array $errors): void
    {
        if ($errors === []) {
            return;
        }

        $this->output->error('Route linting errors:');

        foreach ($errors as $violation) {
            $this->output->writeln($this->formatViolation($violation));
        }
    }

    /**
     * Render the warning violations, if any.
     *
     * @param  array<int, \SineMacula\RouteLinter\Violation>  $warnings
     * @return void
     */
    private function renderWarnings(array $warnings): void
    {
        if ($warnings === []) {
            return;
        }

        $this->output->warning('Route linting warnings:');

        foreach ($warnings as $violation) {
            $this->output->writeln($this->formatViolation($violation));
        }
    }

    /**
     * Render the stale waivers, if any.
     *
     * @param  array<int, string>  $staleWaivers
     * @return void
     */
    private function renderStaleWaivers(array $staleWaivers): void
    {
        if ($staleWaivers === []) {
            return;
        }

        $this->output->warning('Stale waivers / unused suppressions:');

        foreach ($staleWaivers as $entry) {
            $this->output->writeln(sprintf('  - %s', $entry));
        }
    }

    /**
     * Format a violation, e.g. `  [R1] GET users (get) -- Hint: GET /users`.
     *
     * @param  \SineMacula\RouteLinter\Violation  $violation
     * @return string
     */
    private function formatViolation(Violation $violation): string
    {
        $line = sprintf(
            '  [%s] %s (%s)',
            $violation->ruleId,
            $violation->routeIdentity,
            $violation->offendingSurface,
        );

        if ($violation->remediationHint !== null) {
            $line .= sprintf(' -- Hint: %s', $violation->remediationHint);
        }

        return $line;
    }
}
