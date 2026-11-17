<?php

declare(strict_types = 1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\RouteLintEngine;
use SineMacula\RouteLinter\Violation;
use Tests\Fixtures\Rules\ParameterEchoRule;
use Tests\Fixtures\Rules\StaticAggregateRule;
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
final class RouteLintEngineTest extends TestCase
{
    /** @var \SineMacula\RouteLinter\NormalisedRoute */
    private NormalisedRoute $route;

    /** @var \SineMacula\RouteLinter\Dto\RuleConfig */
    private RuleConfig $config;

    /**
     * Set up the shared route and config.
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->route  = new NormalisedRoute('users', ['GET'], 'users.index', ['users'], []);
        $this->config = new RuleConfig([], [], [], []);
    }

    /**
     * Test that every rule runs, in the order supplied.
     *
     * @return void
     */
    public function testRunsAllRulesInSuppliedOrder(): void
    {
        $engine = new RouteLintEngine(
            $this->cannedRule('R1', new Violation('R1', Severity::ERROR, 'GET users users.index', 'getUsers', null)),
            $this->cannedRule('R2', new Violation('R2', Severity::ERROR, 'GET users users.index', 'UserProfiles', null)),
        );

        $violations = $engine->inspect($this->route, $this->config);

        self::assertCount(2, $violations);
        self::assertSame('R1', $violations[0]->ruleId);
        self::assertSame('R2', $violations[1]->ruleId);
    }

    /**
     * Test that violations from rules yielding none, one and two are flattened
     * into one list.
     *
     * @return void
     */
    public function testAggregatesViolationsAcrossRules(): void
    {
        $engine = new RouteLintEngine(
            $this->cannedRule('R1'),
            $this->cannedRule('R2', new Violation('R2', Severity::ERROR, 'GET users users.index', 'UserProfiles', null)),
            $this->cannedRule(
                'R3',
                new Violation('R3', Severity::ERROR, 'GET users users.index', 'Users', null),
                new Violation('R3', Severity::ERROR, 'GET users users.index', 'USERS', null),
            ),
        );

        $violations = $engine->inspect($this->route, $this->config);

        self::assertCount(3, $violations);
        self::assertSame('R2', $violations[0]->ruleId);
        self::assertSame('R3', $violations[1]->ruleId);
        self::assertSame('R3', $violations[2]->ruleId);
    }

    /**
     * Test that an engine with no rules finds nothing.
     *
     * @return void
     */
    public function testNoRulesReturnsEmpty(): void
    {
        self::assertSame([], (new RouteLintEngine)->inspect($this->route, $this->config));
    }

    /**
     * Test that two rules sharing an ID are rejected.
     *
     * @return void
     */
    public function testRejectsDuplicateRuleIds(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Duplicate route-linter rule id "TEST-PARAMS".');

        new RouteLintEngine(new ParameterEchoRule, new ParameterEchoRule);
    }

    /**
     * Test that a per-route and an aggregate rule sharing an ID are rejected.
     *
     * @return void
     */
    public function testRejectsDuplicateRuleIdAcrossKinds(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Duplicate route-linter rule id "TEST-PARAMS".');

        new RouteLintEngine(new ParameterEchoRule, new StaticAggregateRule('TEST-PARAMS'));
    }

    /**
     * Test that inspecting the same route twice gives the same violations.
     *
     * @return void
     */
    public function testRepeatableOutputAcrossTwoRuns(): void
    {
        $engine = new RouteLintEngine($this->cannedRule(
            'R1',
            new Violation('R1', Severity::ERROR, 'GET users users.index', 'getUsers', null),
            new Violation('R1', Severity::ERROR, 'GET users users.index', 'listUsers', null),
        ));

        $firstRun  = $engine->inspect($this->route, $this->config);
        $secondRun = $engine->inspect($this->route, $this->config);

        self::assertCount(2, $firstRun);
        self::assertCount(2, $secondRun);

        foreach ($firstRun as $index => $violation) {
            self::assertSame($violation->ruleId, $secondRun[$index]->ruleId);
            self::assertSame($violation->routeIdentity, $secondRun[$index]->routeIdentity);
            self::assertSame($violation->offendingSurface, $secondRun[$index]->offendingSurface);
            self::assertSame($violation->severity, $secondRun[$index]->severity);
        }
    }

    /**
     * Test that inspectAll() runs aggregate rules over the whole route set.
     *
     * @return void
     */
    public function testInspectAllRunsAggregateRulesOverTheWholeSet(): void
    {
        $engine = new RouteLintEngine(new StaticAggregateRule('AGG-1'));
        $orders = new NormalisedRoute('orders', ['GET'], 'orders.index', ['orders'], []);

        $violations = $engine->inspectAll([$this->route, $orders], $this->config);

        self::assertCount(2, $violations);
        self::assertSame('AGG-1', $violations[0]->ruleId);
        self::assertSame('GET users users.index', $violations[0]->routeIdentity);
        self::assertSame('GET orders orders.index', $violations[1]->routeIdentity);
    }

    /**
     * Test that per-route rules run only in inspect() and aggregate rules only
     * in inspectAll().
     *
     * @return void
     */
    public function testRulesArePartitionedByKind(): void
    {
        $engine = new RouteLintEngine(new ParameterEchoRule, new StaticAggregateRule('AGG-2'));

        $perRoute  = $engine->inspect($this->route, $this->config);
        $aggregate = $engine->inspectAll([$this->route], $this->config);

        self::assertCount(1, $perRoute);
        self::assertSame('TEST-PARAMS', $perRoute[0]->ruleId);
        self::assertCount(1, $aggregate);
        self::assertSame('AGG-2', $aggregate[0]->ruleId);
    }

    /**
     * Test that inspectAll() finds nothing without aggregate rules.
     *
     * @return void
     */
    public function testInspectAllReturnsEmptyWithoutAggregateRules(): void
    {
        self::assertSame([], (new RouteLintEngine(new ParameterEchoRule))->inspectAll([$this->route], $this->config));
    }

    /**
     * Build a per-route rule that returns the given violations for any route.
     *
     * @param  string  $id
     * @param  \SineMacula\RouteLinter\Violation  ...$violations
     * @return \SineMacula\RouteLinter\Contracts\Rule
     */
    private function cannedRule(string $id, Violation ...$violations): Rule
    {
        return new class ($id, array_values($violations)) implements Rule {
            /**
             * Create the rule.
             *
             * @param  string  $ruleId
             * @param  list<\SineMacula\RouteLinter\Violation>  $violations
             */
            public function __construct(

                /** The rule identifier */
                private readonly string $ruleId,

                /** The violations returned for every route */
                private readonly array $violations,
            ) {}

            /**
             * Return the rule identifier.
             *
             * @return string
             */
            #[\Override]
            public function id(): string
            {
                return $this->ruleId;
            }

            /**
             * Return the severity tier.
             *
             * @return \SineMacula\RouteLinter\Enums\Severity
             */
            #[\Override]
            public function severity(): Severity
            {
                return Severity::ERROR;
            }

            /**
             * Return the canned violations.
             *
             * @param  \SineMacula\RouteLinter\NormalisedRoute  $route
             * @param  \SineMacula\RouteLinter\Dto\RuleConfig  $config
             * @return array<int, \SineMacula\RouteLinter\Violation>
             */
            #[\Override]
            public function inspect(NormalisedRoute $route, RuleConfig $config): array
            {
                return $this->violations;
            }
        };
    }
}
