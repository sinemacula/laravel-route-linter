<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Contracts;

use SineMacula\RouteLinter\RouteLintReport;

/**
 * Renders a lint report, such as to the console.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
interface LintReporter
{
    /**
     * Render the report.
     *
     * @param  \SineMacula\RouteLinter\RouteLintReport  $report
     * @return void
     */
    public function report(RouteLintReport $report): void;
}
