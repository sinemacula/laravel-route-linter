<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\RouteLintEngine;
use SineMacula\RouteLinter\Severity;
use SineMacula\RouteLinter\Violation;
use Tests\Fixtures\Rules\ParameterEchoRule;
use Tests\TestCase;

/**
 * Tests for the RouteLintEngine pure orchestrator.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(RouteLintEngine::class)]
class RouteLintEngineTest extends TestCase
{
    /** @var \SineMacula\RouteLinter\NormalisedRoute */
    private NormalisedRoute $route;

    /** @var \SineMacula\RouteLinter\Dto\RuleConfig */
    private RuleConfig $config;

    /**
     * Set up shared fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->route = new NormalisedRoute(
            uri: 'users',
            methods: ['GET'],
            name: 'users.index',
            segments: ['users'],
            parameters: [],
        );
        $this->config = new RuleConfig([], [], [], []);
    }

    /**
     * Test that the engine runs all supplied rules in their
     * constructor-supplied order and returns both violations in that order.
     *
     * @return void
     */
    public function testRunsAllRulesInSuppliedOrder(): void
    {
        // Arrange - two stub rules each emitting a canned violation
        $firstViolation  = new Violation('R1', Severity::Error, 'GET users users.index', 'getUsers', null);
        $secondViolation = new Violation('R2', Severity::Error, 'GET users users.index', 'UserProfiles', null);

        $firstRule = new class ($firstViolation) implements Rule {
            /**
             * @param  \SineMacula\RouteLinter\Violation  $violation
             */
            public function __construct(private readonly Violation $violation) {}

            /**
             * @return string
             */
            #[\Override]
            public function id(): string
            {
                return 'R1';
            }

            /**
             * @return \SineMacula\RouteLinter\Severity
             */
            #[\Override]
            public function severity(): Severity
            {
                return Severity::Error;
            }

            /**
             * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
             * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
             * @return array<int, \SineMacula\RouteLinter\Violation>
             */
            #[\Override]
            public function inspect(NormalisedRoute $route, RuleConfig $config): array
            {
                return [$this->violation];
            }
        };

        $secondRule = new class ($secondViolation) implements Rule {
            /**
             * @param  \SineMacula\RouteLinter\Violation  $violation
             */
            public function __construct(private readonly Violation $violation) {}

            /**
             * @return string
             */
            #[\Override]
            public function id(): string
            {
                return 'R2';
            }

            /**
             * @return \SineMacula\RouteLinter\Severity
             */
            #[\Override]
            public function severity(): Severity
            {
                return Severity::Error;
            }

            /**
             * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
             * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
             * @return array<int, \SineMacula\RouteLinter\Violation>
             */
            #[\Override]
            public function inspect(NormalisedRoute $route, RuleConfig $config): array
            {
                return [$this->violation];
            }
        };

        $engine = new RouteLintEngine($firstRule, $secondRule);

        // Act
        $violations = $engine->inspect($this->route, $this->config);

        // Assert - both violations present in supplied rule order
        static::assertCount(2, $violations);
        static::assertSame('R1', $violations[0]->ruleId);
        static::assertSame('R2', $violations[1]->ruleId);
    }

    /**
     * Test that violations from multiple rules with different yield counts are
     * flattened into a single array of the expected total size.
     *
     * Rule A emits 0, Rule B emits 1, Rule C emits 2 - total must be 3.
     *
     * @return void
     */
    public function testAggregatesViolationsAcrossRules(): void
    {
        // Arrange
        $violationB  = new Violation('R2', Severity::Error, 'GET users users.index', 'UserProfiles', null);
        $violationC1 = new Violation('R3', Severity::Error, 'GET users users.index', 'Users', null);
        $violationC2 = new Violation('R3', Severity::Error, 'GET users users.index', 'USERS', null);

        $ruleA = new class implements Rule {
            /**
             * @return string
             */
            #[\Override]
            public function id(): string
            {
                return 'R1';
            }

            /**
             * @return \SineMacula\RouteLinter\Severity
             */
            #[\Override]
            public function severity(): Severity
            {
                return Severity::Error;
            }

            /**
             * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
             * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
             * @return array<int, \SineMacula\RouteLinter\Violation>
             */
            #[\Override]
            public function inspect(NormalisedRoute $route, RuleConfig $config): array
            {
                return [];
            }
        };

        $ruleB = new class ($violationB) implements Rule {
            /**
             * @param  \SineMacula\RouteLinter\Violation  $violation
             */
            public function __construct(private readonly Violation $violation) {}

            /**
             * @return string
             */
            #[\Override]
            public function id(): string
            {
                return 'R2';
            }

            /**
             * @return \SineMacula\RouteLinter\Severity
             */
            #[\Override]
            public function severity(): Severity
            {
                return Severity::Error;
            }

            /**
             * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
             * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
             * @return array<int, \SineMacula\RouteLinter\Violation>
             */
            #[\Override]
            public function inspect(NormalisedRoute $route, RuleConfig $config): array
            {
                return [$this->violation];
            }
        };

        $ruleC = new class ($violationC1, $violationC2) implements Rule {
            /**
             * @param  \SineMacula\RouteLinter\Violation  $violation1
             * @param  \SineMacula\RouteLinter\Violation  $violation2
             */
            public function __construct(
                private readonly Violation $violation1,
                private readonly Violation $violation2,
            ) {}

            /**
             * @return string
             */
            #[\Override]
            public function id(): string
            {
                return 'R3';
            }

            /**
             * @return \SineMacula\RouteLinter\Severity
             */
            #[\Override]
            public function severity(): Severity
            {
                return Severity::Error;
            }

            /**
             * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
             * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
             * @return array<int, \SineMacula\RouteLinter\Violation>
             */
            #[\Override]
            public function inspect(NormalisedRoute $route, RuleConfig $config): array
            {
                return [$this->violation1, $this->violation2];
            }
        };

        $engine = new RouteLintEngine($ruleA, $ruleB, $ruleC);

        // Act
        $violations = $engine->inspect($this->route, $this->config);

        // Assert - 0 + 1 + 2 = 3 violations in a flat array
        static::assertCount(3, $violations);
        static::assertSame('R2', $violations[0]->ruleId);
        static::assertSame('R3', $violations[1]->ruleId);
        static::assertSame('R3', $violations[2]->ruleId);
    }

    /**
     * Test that an engine constructed with no rules returns an empty array.
     *
     * @return void
     */
    public function testNoRulesReturnsEmpty(): void
    {
        // Arrange
        $engine = new RouteLintEngine;

        // Act
        $violations = $engine->inspect($this->route, $this->config);

        // Assert
        static::assertSame([], $violations);
    }

    /**
     * Test that the engine rejects a rule set containing two rules with the
     * same identifier, since a duplicate id makes report ordering and per-rule
     * suppression ambiguous.
     *
     * @return void
     */
    public function testRejectsDuplicateRuleIds(): void
    {
        // Two instances of the same rule class share the id 'TEST-PARAMS'
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Duplicate route-linter rule id "TEST-PARAMS".');

        new RouteLintEngine(new ParameterEchoRule, new ParameterEchoRule);
    }

    /**
     * Test that calling inspect() twice with the same route and config produces
     * byte-identical arrays (NFR-01 repeatability / determinism).
     *
     * @return void
     */
    public function testRepeatableOutputAcrossTwoRuns(): void
    {
        // Arrange - a single stub rule emitting two canned violations
        $violation1 = new Violation('R1', Severity::Error, 'GET users users.index', 'getUsers', null);
        $violation2 = new Violation('R1', Severity::Error, 'GET users users.index', 'listUsers', null);

        $rule = new class ($violation1, $violation2) implements Rule {
            /**
             * @param  \SineMacula\RouteLinter\Violation  $violation1
             * @param  \SineMacula\RouteLinter\Violation  $violation2
             */
            public function __construct(
                private readonly Violation $violation1,
                private readonly Violation $violation2,
            ) {}

            /**
             * @return string
             */
            #[\Override]
            public function id(): string
            {
                return 'R1';
            }

            /**
             * @return \SineMacula\RouteLinter\Severity
             */
            #[\Override]
            public function severity(): Severity
            {
                return Severity::Error;
            }

            /**
             * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
             * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
             * @return array<int, \SineMacula\RouteLinter\Violation>
             */
            #[\Override]
            public function inspect(NormalisedRoute $route, RuleConfig $config): array
            {
                return [$this->violation1, $this->violation2];
            }
        };

        $engine = new RouteLintEngine($rule);

        // Act - two separate calls with identical inputs
        $firstRun  = $engine->inspect($this->route, $this->config);
        $secondRun = $engine->inspect($this->route, $this->config);

        // Assert - identical count, identical element identity per position
        static::assertCount(2, $firstRun);
        static::assertCount(2, $secondRun);

        foreach ($firstRun as $index => $violation) {
            static::assertSame($violation->ruleId, $secondRun[$index]->ruleId);
            static::assertSame($violation->routeIdentity, $secondRun[$index]->routeIdentity);
            static::assertSame($violation->offendingSurface, $secondRun[$index]->offendingSurface);
            static::assertSame($violation->severity, $secondRun[$index]->severity);
        }
    }
}
