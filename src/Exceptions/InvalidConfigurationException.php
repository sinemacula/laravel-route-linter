<?php

namespace SineMacula\RouteLinter\Exceptions;

/**
 * Exception thrown when the route-linter configuration is invalid.
 *
 * Raised at config-read or wiring time for every schema violation the linter
 * refuses to absorb silently: a non-array value for an array-typed config key,
 * an exemption entry missing its required match or written reason, an inline
 * suppression registered with an empty reason, or a configured rule class that
 * is not a {@see \SineMacula\RouteLinter\Contracts\Rule}. Failing loud here
 * prevents a misconfiguration from silently weakening the lint verdict.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class InvalidConfigurationException extends \RuntimeException {}
