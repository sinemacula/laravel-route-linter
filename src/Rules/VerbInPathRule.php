<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Rules;

use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\Support\SegmentNormaliser;
use SineMacula\RouteLinter\Rules\Support\VerbDenylist;
use SineMacula\RouteLinter\Violation;

/**
 * Rule R1: no action verb in a path segment.
 *
 * Each distinct verb found among the normalised path words is reported once,
 * with its remediation hint.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class VerbInPathRule implements Rule
{
    /**
     * Create a new verb-in-path rule.
     *
     * @param  \SineMacula\RouteLinter\Rules\Support\SegmentNormaliser  $normaliser
     * @param  \SineMacula\RouteLinter\Rules\Support\VerbDenylist  $denylist
     */
    public function __construct(

        /** Reduces the URI to candidate words */
        private SegmentNormaliser $normaliser,

        /** The denylisted verbs and their hints */
        private VerbDenylist $denylist,
    ) {}

    /**
     * Return the stable rule identifier.
     *
     * @return string
     */
    #[\Override]
    public function id(): string
    {
        return 'R1';
    }

    /**
     * Return the severity tier for this rule.
     *
     * @return \SineMacula\RouteLinter\Enums\Severity
     */
    #[\Override]
    public function severity(): Severity
    {
        return Severity::ERROR;
    }

    /**
     * Inspect one normalised route and return one violation per distinct verb.
     *
     * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
     * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    #[\Override]
    public function inspect(NormalisedRoute $route, RuleConfig $config): array
    {
        $words      = $this->normaliser->normalise($route->uri, $config->uncountables);
        $violations = [];
        $seen       = [];

        foreach ($words as $word) {
            if (($seen[$word] ?? false) || !$this->denylist->contains($word)) {
                continue;
            }

            $seen[$word]  = true;
            $violations[] = new Violation(
                ruleId: $this->id(),
                severity: $this->severity(),
                routeIdentity: $route->identity(),
                offendingSurface: $word,
                remediationHint: $this->denylist->hint($word),
            );
        }

        return $violations;
    }
}
