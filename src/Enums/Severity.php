<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Enums;

/**
 * The severity of a finding; only errors fail the command.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
enum Severity: string
{
    /** Fails the command */
    case ERROR = 'error';

    /** Reported without failing the command */
    case WARNING = 'warning';
}
