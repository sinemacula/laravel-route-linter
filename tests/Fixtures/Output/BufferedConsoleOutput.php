<?php

declare(strict_types = 1);

namespace Tests\Fixtures\Output;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Fixture console output that buffers its standard and error streams
 * separately.
 *
 * Lets a test read what was written to each stream without touching the real
 * process streams.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class BufferedConsoleOutput extends BufferedOutput implements ConsoleOutputInterface
{
    /** @var \Symfony\Component\Console\Output\BufferedOutput The buffered error stream. */
    public readonly BufferedOutput $errors;

    /**
     * Create a new buffered console output with an empty error buffer.
     */
    public function __construct()
    {
        parent::__construct();

        $this->errors = new BufferedOutput;
    }

    /**
     * Get the buffered error output.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    #[\Override]
    public function getErrorOutput(): OutputInterface
    {
        return $this->errors;
    }

    /**
     * Reject replacing the error output, which would bypass the buffer.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $error
     * @return void
     *
     * @throws \LogicException
     */
    #[\Override]
    public function setErrorOutput(OutputInterface $error): void
    {
        throw new \LogicException('The error output of a buffered console output is fixed.');
    }

    /**
     * Reject creating a section, which this fixture does not support.
     *
     * @return \Symfony\Component\Console\Output\ConsoleSectionOutput
     *
     * @throws \LogicException
     */
    #[\Override]
    public function section(): ConsoleSectionOutput
    {
        throw new \LogicException('A buffered console output does not support sections.');
    }
}
