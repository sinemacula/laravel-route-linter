<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Console;

use Illuminate\Console\Command;
use SineMacula\RouteLinter\Contracts\LintReporter;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;
use SineMacula\RouteLinter\LintRoutes;

/**
 * Artisan command to lint the application route table against RESTful
 * conventions.
 *
 * Invokes the LintRoutes use case, renders the report via the bound
 * LintReporter port, and exits non-zero when the verdict contains any
 * ERROR-severity violations. Warning-severity findings and stale waivers are
 * surfaced but do not gate the exit code. A misconfigured linter
 * (InvalidConfigurationException) is treated as an error-grade configuration
 * failure and exits non-zero immediately.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class LintRoutesCommand extends Command
{
    /** @var string The console command signature. */
    protected $signature = 'route:lint';

    /** @var string The console command description. */
    protected $description = 'Lint the application route table against the RESTful URL conventions';

    /**
     * Execute the console command.
     *
     * @param  \SineMacula\RouteLinter\LintRoutes  $linter
     * @return int
     */
    public function handle(LintRoutes $linter): int
    {
        /** @var \SineMacula\RouteLinter\Contracts\LintReporter $reporter */
        $reporter = $this->laravel->make(LintReporter::class, ['output' => $this->output]);

        try {
            $report = $linter->lint();
        } catch (InvalidConfigurationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $reporter->report($report);

        return $report->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
