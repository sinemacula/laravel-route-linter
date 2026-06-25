<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Output;

use Illuminate\Console\OutputStyle;
use SineMacula\RouteLinter\Contracts\LintReporter;
use SineMacula\RouteLinter\RouteLintReport;
use SineMacula\RouteLinter\Violation;

/**
 * Console adapter for the LintReporter port.
 *
 * Renders a RouteLintReport to the Artisan command's output: error violations
 * are grouped under an error header, warning violations under a warning header,
 * and stale-waiver entries under a dedicated stale-waivers header. When the
 * report is clean (no errors, warnings, or stale entries) a single success line
 * is written instead. Empty sections are silently skipped; the reporter never
 * influences the exit code.
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

        /** Console output channel the report is rendered to */
        private OutputStyle $output,
    ) {}

    /**
     * Render the report (findings grouped by severity, plus stale-waiver
     * findings) to the invoking surface.
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
     * Render error-severity violations, skipping the section when the list is
     * empty.
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
     * Render warning-severity violations, skipping the section when the list is
     * empty.
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
     * Render stale-waiver entries, skipping the section when the list is empty.
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
     * Format a single violation as a console line.
     *
     * Produces:
     * `  [R1] GET /users (getUsers) -- Hint: use a noun-based path instead`
     * When remediationHint is null the hint segment is omitted.
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
