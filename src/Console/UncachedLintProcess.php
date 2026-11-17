<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Console;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

/**
 * Runs `route:lint` in a child process that bypasses the route cache.
 *
 * A cached table serialises closures and names unnamed routes randomly, and a
 * second in-process boot would leak global state into the caller.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class UncachedLintProcess
{
    /** @var string Environment variable marking the child process. */
    public const string MARKER = 'ROUTE_LINTER_UNCACHED';

    /** @var array<int, string> The child's flag for each output verbosity. */
    private const array VERBOSITY_FLAGS = [
        OutputInterface::VERBOSITY_SILENT       => '--silent',
        OutputInterface::VERBOSITY_QUIET        => '--quiet',
        OutputInterface::VERBOSITY_VERBOSE      => '-v',
        OutputInterface::VERBOSITY_VERY_VERBOSE => '-vv',
        OutputInterface::VERBOSITY_DEBUG        => '-vvv',
    ];

    /**
     * Create a new uncached lint process.
     *
     * @param  string  $basePath
     * @param  string|null  $environmentFile
     * @param  \Symfony\Component\Console\Terminal  $terminal
     */
    public function __construct(

        /** The application base path, holding the artisan script the child runs */
        private string $basePath,

        /** The environment file the caller loaded, or null when config is cached */
        private ?string $environmentFile,

        /** The caller's terminal, whose width the child renders at */
        private Terminal $terminal = new Terminal,
    ) {}

    /**
     * Run the child, streaming its output, and return its exit code.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  string|null  $environment
     * @return int
     */
    public function run(OutputInterface $output, ?string $environment): int
    {
        $process = new Process($this->command($output, $environment), $this->basePath, $this->environment(), null, null);

        try {
            return $process->run(fn (string $type, string $chunk) => $this->stream($output, $type, $chunk));
        } catch (ProcessSignaledException $exception) {
            $this->errorOutput($output)->writeln(sprintf('<error>The route-lint process was terminated by signal %d.</error>', $exception->getSignal()));

            return 128 + $exception->getSignal();
        }
    }

    /**
     * Build the child's command line from the caller's output and environment.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  string|null  $environment
     * @return list<string>
     */
    private function command(OutputInterface $output, ?string $environment): array
    {
        $command = [PHP_BINARY, $this->basePath . DIRECTORY_SEPARATOR . 'artisan', 'route:lint', '--no-interaction', $output->isDecorated() ? '--ansi' : '--no-ansi'];

        if (isset(self::VERBOSITY_FLAGS[$output->getVerbosity()])) {
            $command[] = self::VERBOSITY_FLAGS[$output->getVerbosity()];
        }

        if ($environment !== null) {
            $command[] = '--env=' . $environment;
        }

        return $command;
    }

    /**
     * Build the child's environment from the caller's.
     *
     * An inherited APP_ENV would make the child also load `.env.{APP_ENV}`, so
     * it is withheld when that file exists. The cache path lies beneath the
     * artisan file, so it cannot exist.
     *
     * @return array<string, false|string>
     */
    private function environment(): array
    {
        $environment = getenv();

        if ($this->environmentFile !== null && isset($environment['APP_ENV']) && is_file($this->environmentFile . '.' . $environment['APP_ENV'])) {
            $environment['APP_ENV'] = false;
        }

        return [
            ...$environment,
            'APP_ROUTES_CACHE' => 'artisan' . DIRECTORY_SEPARATOR . 'routes.php',
            'COLUMNS'          => (string) $this->terminal->getWidth(),
            self::MARKER       => '1',
        ];
    }

    /**
     * Write a chunk of the child's output as rendered.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  string  $type
     * @param  string  $chunk
     * @return void
     */
    private function stream(OutputInterface $output, string $type, string $chunk): void
    {
        $target = $type === Process::ERR ? $this->errorOutput($output) : $output;

        // The child already applied the verbosity
        $target->write($chunk, false, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);
    }

    /**
     * Return the caller's error output, or its output when it has none.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    private function errorOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
