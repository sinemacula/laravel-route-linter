<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
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
 * failure and exits non-zero immediately. While the route table is cached, the
 * command re-runs itself in a child process that bypasses the cache, so the
 * verdict reflects the current route definitions whatever the cache state.
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
     * @return int
     */
    public function handle(): int
    {
        if ($this->laravel instanceof Application && $this->laravel->routesAreCached()) {
            return $this->lintUncached($this->laravel);
        }

        /** @var \SineMacula\RouteLinter\Contracts\LintReporter $reporter */
        $reporter = $this->laravel->make(LintReporter::class, ['output' => $this->output]);

        try {
            $report = $this->laravel->make(LintRoutes::class)->lint();
        } catch (InvalidConfigurationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $reporter->report($report);

        return $report->hasErrors() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Lint the current route definitions in a child process that bypasses the
     * route cache.
     *
     * A child that still finds the routes cached - the application overrides
     * the cache path or the cached state - fails instead of spawning another.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return int
     */
    private function lintUncached(Application $app): int
    {
        if (getenv(UncachedLintProcess::MARKER) !== false) {
            $this->components->error('The route cache could not be bypassed, so the current route definitions cannot be linted.');

            return self::FAILURE;
        }

        $environment = $this->option('env');

        $environmentFile = $app->configurationIsCached() ? null : $app->environmentFilePath();

        return (new UncachedLintProcess($app->basePath(), $environmentFile))->run($this->output->getOutput(), is_string($environment) ? $environment : null);
    }
}
