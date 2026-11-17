<?php

declare(strict_types = 1);

namespace Tests\Integration;

use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Configuration\ConfigRuleConfiguration;
use SineMacula\RouteLinter\Contracts\AggregateRule;
use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Inflection\FrameworkInflector;
use SineMacula\RouteLinter\LintRoutes;
use SineMacula\RouteLinter\RouteLintEngine;
use SineMacula\RouteLinter\RouteLintReport;
use SineMacula\RouteLinter\Rules\ApiResourceAlignmentRule;
use SineMacula\RouteLinter\Rules\DuplicateRouteNameRule;
use SineMacula\RouteLinter\Rules\KebabCaseRule;
use SineMacula\RouteLinter\Rules\LowercaseRule;
use SineMacula\RouteLinter\Rules\NestingDepthRule;
use SineMacula\RouteLinter\Rules\PluralCollectionsRule;
use SineMacula\RouteLinter\Rules\RouteNameRule;
use SineMacula\RouteLinter\Rules\SlashSanityRule;
use SineMacula\RouteLinter\Rules\StandardMethodsRule;
use SineMacula\RouteLinter\Rules\Support\SegmentNormaliser;
use SineMacula\RouteLinter\Rules\Support\VerbDenylist;
use SineMacula\RouteLinter\Rules\VerbInPathRule;
use SineMacula\RouteLinter\Sources\RouterRouteSource;
use SineMacula\RouteLinter\Violation;
use Tests\Fixtures\Controllers\RouteLintController;
use Tests\Fixtures\Rules\GlobalAggregateRule;
use Tests\Fixtures\Rules\ParameterEchoRule;
use Tests\TestCase;

/**
 * End-to-end integration tests for the LintRoutes use case.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(LintRoutes::class)]
final class LintRoutesTest extends TestCase
{
    /** @var array<int, string> Verb denylist covering the fixture offenders. */
    private const array VERB_DENYLIST = [
        'get', 'list', 'create', 'add', 'update', 'edit', 'delete',
        'remove', 'cancel', 'login', 'logout', 'search', 'fetch',
        'transfer', 'check', 'process', 'submit',
    ];

    /**
     * Test that the common offenders are flagged and /users is clean.
     *
     * @return void
     */
    public function testFlagsCommonOffendersWithDefaults(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('getUsers', fn () => [])->name('get-users');
        $router->post('users/create', fn () => [])->name('users.create-action');
        $router->post('order/{id}/cancel', fn () => [])->name('order.cancel');
        $router->post('login', fn () => [])->name('auth.login');
        $router->get('userProfiles', fn () => [])->name('user-profiles');
        $router->get('users', fn () => [])->name('users.index');

        $report = $this->buildUseCase($router)->lint();

        self::assertNotEmpty($report->errors(), 'Expected at least one error-severity violation.');
        self::assertTrue($this->hasViolationForUri($report, 'getUsers'), '/getUsers should be flagged');
        self::assertTrue($this->hasViolationForUri($report, 'users/create'), '/users/create should be flagged');
        self::assertTrue($this->hasViolationForUri($report, 'order/{id}/cancel'), '/order/{id}/cancel should be flagged');
        self::assertTrue($this->hasViolationForUri($report, 'login'), '/login should be flagged');
        self::assertTrue($this->hasViolationForUri($report, 'userProfiles'), '/userProfiles should be flagged');
        self::assertNotContains('GET,HEAD users users.index', $this->collectOffendingIdentities($report), '/users should be clean');
    }

    /**
     * Test that an exempted route's violation is suppressed and not stale.
     *
     * @return void
     */
    public function testExemptedViolationIsSuppressed(): void
    {
        $this->seedDefaultConfig([
            [
                'match'  => 'login',
                'reason' => 'Legacy endpoint kept for backward compatibility.',
            ],
        ]);

        $router = $this->getRouter();
        $router->post('login', fn () => [])->name('auth.login');

        $report = $this->buildUseCase($router)->lint();

        self::assertSame([], $report->errors(), 'Violation for exempted route should be suppressed.');
        self::assertSame([], $report->staleWaivers(), 'Matched entry must not appear as stale.');
    }

    /**
     * Test that an allowlist entry matching no live route is stale.
     *
     * @return void
     */
    public function testStaleWaiverIsReported(): void
    {
        $this->seedDefaultConfig([
            [
                'match'  => 'no-such-route',
                'reason' => 'Was needed for the old API; route no longer exists.',
            ],
        ]);

        $router = $this->getRouter();
        $router->get('users', fn () => [])->name('users.index');

        $report = $this->buildUseCase($router)->lint();

        self::assertContains('no-such-route', $report->staleWaivers(), 'Unmatched entry must be reported as stale.');
    }

    /**
     * Test that dropping a verb from the denylist clears R1 without a waiver.
     *
     * @return void
     */
    public function testDenylistTuningClearsVerbFindingWithoutExemption(): void
    {
        $this->seedDefaultConfig([], array_values(array_filter(
            self::VERB_DENYLIST,
            fn (string $v): bool => $v !== 'transfer',
        )));

        $router = $this->getRouter();
        $router->get('transfers', fn () => [])->name('transfers.index');

        $report = $this->buildUseCase($router)->lint();

        self::assertSame([], $this->violationsOf($report, 'R1'), 'No R1 violation expected after homograph removal.');
        self::assertSame([], $report->staleWaivers(), 'No exemption entry should be stale when using denylist tuning.');
    }

    /**
     * Test that an inline suppression drops only the rule it names.
     *
     * @return void
     */
    public function testInlineSuppressionDropsOnlyTargetedRule(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('getUsers', fn () => [])
            ->name('get-users')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint(['R1'], 'Verb in path is intentional for this legacy endpoint.');

        $report = $this->buildUseCase($router)->lint();

        self::assertSame([], $this->violationsOf($report, 'R1'), 'R1 must be suppressed by the inline suppression.');
        self::assertNotEmpty($this->violationsOf($report, 'R2'), 'R2 must still be reported on the same route.');
    }

    /**
     * Test that a config exemption with a rules list waives only those rules.
     *
     * @return void
     */
    public function testConfigExemptionWithRulesIsPerRule(): void
    {
        $this->seedDefaultConfig([
            [
                'match'  => 'login',
                'reason' => 'Legacy auth endpoint; R1 only waived.',
                'rules'  => ['R1'],
            ],
        ]);

        $router = $this->getRouter();
        $router->post('login', fn () => [])->name('auth.login');

        $report = $this->buildUseCase($router)->lint();

        $others = array_filter($this->allViolations($report), static fn (Violation $v): bool => $v->ruleId !== 'R1');

        self::assertSame([], $this->violationsOf($report, 'R1'), 'R1 must be suppressed by the config exemption.');
        self::assertNotEmpty($others, 'Non-R1 violations on /login must still be reported.');
    }

    /**
     * Provide inline suppression rule lists with their stale-entry label.
     *
     * @return iterable<string, array{list<string>, string}>
     */
    public static function provideUnusedInlineSuppressions(): iterable
    {
        yield 'one rule' => [['R1'], 'rules: R1'];
        yield 'several rules' => [['R1', 'R2'], 'rules: R1, R2'];
        yield 'all rules' => [[], 'rules: all rules'];
    }

    /**
     * Test that an unused inline suppression is stale, naming its rules.
     *
     * @param  list<string>  $rules
     * @param  string  $label
     * @return void
     */
    #[DataProvider('provideUnusedInlineSuppressions')]
    public function testUnusedInlineSuppressionIsReportedAsStale(array $rules, string $label): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('users', fn () => [])
            ->name('users.index')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint($rules, 'Suppression on a clean route.');

        $report = $this->buildUseCase($router)->lint();

        $staleWaivers = $report->staleWaivers();
        $staleString  = implode("\n", $staleWaivers);

        self::assertNotEmpty($staleWaivers, 'An unused inline suppression must appear as a stale entry.');
        self::assertStringContainsString('suppressed nothing', $staleString);
        self::assertStringContainsString($label, $staleString);
        self::assertStringContainsString('Suppression on a clean route.', $staleString);
    }

    /**
     * Test that a config exemption without a rules list waives every rule.
     *
     * @return void
     */
    public function testConfigExemptionWithoutRulesSuppressesAllViolations(): void
    {
        $this->seedDefaultConfig([
            [
                'match'  => 'login',
                'reason' => 'Legacy endpoint kept for backward compatibility.',
            ],
        ]);

        $router = $this->getRouter();
        $router->post('login', fn () => [])->name('auth.login');

        $report = $this->buildUseCase($router)->lint();

        $loginViolations = array_values(array_filter(
            $this->allViolations($report),
            static fn (Violation $v): bool => str_contains($v->routeIdentity, 'login'),
        ));

        self::assertSame([], $loginViolations, 'All violations on an all-rules-waived route must be suppressed.');
        self::assertSame([], $report->staleWaivers(), 'A used config entry must not appear as stale.');
    }

    /**
     * Test that two runs over the same inputs give identical verdicts.
     *
     * @return void
     */
    public function testRepeatableVerdictAcrossTwoRuns(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('getUsers', fn () => [])->name('get-users');
        $router->post('users/create', fn () => [])->name('users.create-action');
        $router->get('users', fn () => [])->name('users.index');

        $useCase = $this->buildUseCase($router);

        self::assertSame(
            $this->serialiseReport($useCase->lint()),
            $this->serialiseReport($useCase->lint()),
            'Two runs over the same inputs must produce identical verdicts.',
        );
    }

    /**
     * Test that a matched config exemption that suppresses nothing is stale.
     *
     * @return void
     */
    public function testMatchedConfigExemptionThatSuppressesNothingIsStale(): void
    {
        $this->seedDefaultConfig([
            [
                'match'  => 'users.index',
                'reason' => 'Pre-emptive waiver that turns out to serve no purpose.',
            ],
        ]);

        $router = $this->getRouter();
        $router->get('users', fn () => [])->name('users.index');

        $report = $this->buildUseCase($router)->lint();

        $staleWaivers = $report->staleWaivers();
        $staleString  = implode("\n", $staleWaivers);

        self::assertSame([], $report->errors(), 'Clean route must produce no errors.');
        self::assertNotEmpty($staleWaivers, 'A matched-but-unused config entry must appear as stale.');
        self::assertStringContainsString('users.index', $staleString, 'Stale entry must name the exemption match key.');
        self::assertStringContainsString('suppressed nothing', $staleString, 'Stale entry must indicate nothing was suppressed.');
        self::assertStringContainsString('Pre-emptive waiver that turns out to serve no purpose.', $staleString);
    }

    /**
     * Test that an inline suppression that fires is not stale.
     *
     * @return void
     */
    public function testUsedInlineSuppressionProducesNoStaleEntry(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('getUsers', fn () => [])
            ->name('get-users')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint(['R1'], 'Suppression that actually fires on R1.');

        $report = $this->buildUseCase($router)->lint();

        self::assertSame([], $this->violationsOf($report, 'R1'), 'R1 must be suppressed by the inline suppression.');
        self::assertStringNotContainsString('Suppression that actually fires on R1.', implode("\n", $report->staleWaivers()), 'A used suppression must not appear as stale.');
    }

    /**
     * Test that only the first inline suppression covering a violation is used.
     *
     * @return void
     */
    public function testOnlyTheFirstMatchingInlineSuppressionIsUsed(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('getUsers', fn () => [])
            ->name('get-users')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint(['R1'], 'First R1 waiver.')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint(['R1'], 'Second R1 waiver.');

        $report = $this->buildUseCase($router)->lint();

        $staleString = implode("\n", $report->staleWaivers());

        self::assertSame([], $this->violationsOf($report, 'R1'), 'R1 must be suppressed.');
        self::assertStringContainsString('Second R1 waiver.', $staleString, 'Second suppression must be stale.');
        self::assertStringNotContainsString('First R1 waiver.', $staleString, 'First suppression must not be stale because it fired.');
    }

    /**
     * Test that every inline suppression that fires on a route is used.
     *
     * @return void
     */
    public function testEveryFiringInlineSuppressionOnARouteIsUsed(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('getUsers', fn () => [])
            ->name('get-users')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint(['R1'], 'Suppresses verb-in-path violation.')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint(['R2'], 'Suppresses kebab-case violation.');

        $report = $this->buildUseCase($router)->lint();

        $staleString = implode("\n", $report->staleWaivers());

        self::assertSame([], $this->violationsOf($report, 'R1'), 'R1 must be suppressed by the first inline suppression.');
        self::assertSame([], $this->violationsOf($report, 'R2'), 'R2 must be suppressed by the second inline suppression.');
        self::assertStringNotContainsString('Suppresses verb-in-path violation.', $staleString, 'First suppression must not be stale.');
        self::assertStringNotContainsString('Suppresses kebab-case violation.', $staleString, 'Second suppression must not be stale.');
    }

    /**
     * Test that the verdict does not depend on route registration order.
     *
     * @return void
     */
    public function testVerdictIsIndependentOfRouteRegistrationOrder(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('getZzz', fn () => [])->name('zzz.index');
        $router->get('getAaa', fn () => [])->name('aaa.index');

        $report1 = $this->buildUseCase($router)->lint();

        $router2 = $this->getRouter();
        $router2->get('getAaa', fn () => [])->name('aaa.index');
        $router2->get('getZzz', fn () => [])->name('zzz.index');

        $report2 = $this->buildUseCase($router2)->lint();

        self::assertSame(
            $this->serialiseReport($report1),
            $this->serialiseReport($report2),
            'RouteLintReport must return a stable total order independent of route registration order.',
        );
    }

    /**
     * Provide routes with two parameters ending in a create segment.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function provideParameterisedCreateRoutes(): iterable
    {
        yield 'organisation users' => ['organisations/{organisation}/users/{user}/create', 'organisations.users.create'];
        yield 'order items' => ['orders/{order}/items/{item}/create', 'orders.items.create'];
    }

    /**
     * Test that parameter segments are never treated as literal segments.
     *
     * @param  string  $uri
     * @param  string  $name
     * @return void
     */
    #[DataProvider('provideParameterisedCreateRoutes')]
    public function testParameterSegmentsAreNotLiterals(string $uri, string $name): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get($uri, fn () => [])->name($name);

        $report = $this->buildUseCase($router)->lint();

        $r9Violations   = $this->violationsOf($report, 'R9');
        $r4OnParameters = array_filter($this->violationsOf($report, 'R4'), static fn (Violation $v): bool => str_contains($v->offendingSurface, '{'));

        self::assertNotEmpty($r9Violations, 'R9 must fire: "create" is an HTML-only segment.');
        self::assertSame('create', $r9Violations[0]->offendingSurface, 'Offending surface must be the literal "create" segment, not a parameter.');
        self::assertSame([], $r4OnParameters, 'Parameter segments wrapped in braces must never be flagged by R4.');
    }

    /**
     * Test that rules receive the route's brace-stripped parameter names.
     *
     * @return void
     */
    public function testExposesBraceStrippedParametersToRules(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('teams/{team}/members/{member}', fn () => [])->name('teams.members.index');

        $report = $this->buildUseCaseWithRules($router, new ParameterEchoRule)->lint();

        $surfaces = array_map(static fn (Violation $violation): string => $violation->offendingSurface, $report->warnings());

        self::assertContains('team,member', $surfaces, 'Rules must receive brace-stripped parameter names extracted by the use case.');
    }

    /**
     * Test that a duplicate route name is flagged on the second registration.
     *
     * @return void
     */
    public function testDuplicateRouteNameIsFlagged(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('people', fn () => [])->name('shared.index');
        $router->get('teams', fn () => [])->name('shared.index');

        $report = $this->buildUseCaseWithRules($router, new DuplicateRouteNameRule)->lint();

        $errors = $report->errors();

        self::assertCount(1, $errors);
        self::assertSame('R6', $errors[0]->ruleId);
        self::assertSame('GET,HEAD teams shared.index', $errors[0]->routeIdentity);
        self::assertSame('shared.index', $errors[0]->offendingSurface);
    }

    /**
     * Test that an inline waiver suppresses an aggregate violation.
     *
     * @return void
     */
    public function testAggregateViolationIsSuppressedInline(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('people', fn () => [])->name('shared.index');
        $router->get('teams', fn () => [])->name('shared.index')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint(['R6'], 'Intentional alias kept during migration.');

        $report = $this->buildUseCaseWithRules($router, new DuplicateRouteNameRule)->lint();

        self::assertSame([], $report->errors(), 'Aggregate violation should be suppressed by the inline waiver.');
        self::assertSame([], $report->staleWaivers(), 'A waiver that suppressed an aggregate violation is not stale.');
    }

    /**
     * Test that an aggregate violation matching no route is reported as is.
     *
     * @return void
     */
    public function testUnattributedAggregateViolationIsReported(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('users', fn () => [])->name('users.index');

        $report = $this->buildUseCaseWithRules($router, new GlobalAggregateRule)->lint();

        $warnings = $report->warnings();

        self::assertCount(2, $warnings);
        self::assertSame('GLOBAL', $warnings[0]->ruleId);
        self::assertSame('route-table', $warnings[0]->routeIdentity);
        self::assertSame('global-finding-1', $warnings[0]->offendingSurface);
        self::assertSame('global-finding-2', $warnings[1]->offendingSurface);
    }

    /**
     * Test that per-route inline waivers on several routes are all used.
     *
     * @return void
     */
    public function testPerRouteInlineWaiversAcrossRoutesAreAllTracked(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('getUsers', fn () => [])->name('get-users')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint(['R1'], 'First route waiver.');
        $router->get('fetchData', fn () => [])->name('fetch-data')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint(['R1'], 'Second route waiver.');

        $report = $this->buildUseCaseWithRules(
            $router,
            new VerbInPathRule(new SegmentNormaliser(new FrameworkInflector), $this->buildVerbDenylist()),
        )->lint();

        self::assertSame([], $report->errors(), 'Both R1 violations should be suppressed inline.');
        self::assertSame([], $report->staleWaivers(), 'Both inline waivers fired and must not be stale.');
    }

    /**
     * Test that aggregate inline waivers on several routes are all used.
     *
     * @return void
     */
    public function testAggregateInlineWaiversAcrossRoutesAreAllTracked(): void
    {
        $this->seedDefaultConfig();

        $router = $this->getRouter();
        $router->get('alpha', fn () => [])->name('shared.name');
        $router->get('beta', fn () => [])->name('shared.name')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint(['R6'], 'Second alias waiver.');
        $router->get('gamma', fn () => [])->name('shared.name')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint(['R6'], 'Third alias waiver.');

        $report = $this->buildUseCaseWithRules($router, new DuplicateRouteNameRule)->lint();

        self::assertSame([], $report->errors(), 'Both duplicate R6 violations should be suppressed.');
        self::assertSame([], $report->staleWaivers(), 'Both aggregate waivers fired and must not be stale.');
    }

    /**
     * Test that framework-registered singletons and namespaced resources pass
     * R4 and R11, while a singular collection is still flagged.
     *
     * @return void
     */
    public function testSingletonAndNamespacedResourcesPassTheCollectionRules(): void
    {
        $this->seedDefaultConfig();
        config()->set('route-linter.singleton_resources', ['profile']);

        $router = $this->getRouter();
        $router->apiResource('connections', RouteLintController::class)->only('index');
        $router->apiSingleton('connections.callback', RouteLintController::class)->only('show');
        $router->apiSingleton('profile', RouteLintController::class)->creatable();
        $router->group(['prefix' => 'auth/sso'], static function (Router $router): void {
            $router->apiResource('connections.domains', RouteLintController::class)->only('store');
            $router->apiSingleton('connections.metadata', RouteLintController::class)->only('show');
        });
        $router->get('setting', fn () => [])->name('setting.index');

        $report = $this->buildUseCase($router)->lint();

        $findings = array_map(
            static fn (Violation $violation): string => $violation->ruleId . ' ' . $violation->routeIdentity,
            array_values(array_filter(
                $this->allViolations($report),
                static fn (Violation $violation): bool => in_array($violation->ruleId, ['R4', 'R11'], true),
            )),
        );

        self::assertSame(['R4 GET,HEAD setting setting.index'], $findings);
    }

    /**
     * Build the use case with the given rules against the router.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @param  \SineMacula\RouteLinter\Contracts\AggregateRule|\SineMacula\RouteLinter\Contracts\Rule  ...$rules
     * @return \SineMacula\RouteLinter\LintRoutes
     */
    private function buildUseCaseWithRules(Router $router, AggregateRule|Rule ...$rules): LintRoutes
    {
        return new LintRoutes(
            new RouterRouteSource($router),
            new ConfigRuleConfiguration,
            new RouteLintEngine(...$rules),
        );
    }

    /**
     * Build the use case with the built-in per-route rules.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return \SineMacula\RouteLinter\LintRoutes
     */
    private function buildUseCase(Router $router): LintRoutes
    {
        $inflector = new FrameworkInflector;

        return $this->buildUseCaseWithRules(
            $router,
            new VerbInPathRule(new SegmentNormaliser($inflector), $this->buildVerbDenylist()),
            new KebabCaseRule,
            new LowercaseRule,
            new PluralCollectionsRule($inflector),
            new SlashSanityRule,
            new StandardMethodsRule,
            new RouteNameRule,
            new ApiResourceAlignmentRule,
            new NestingDepthRule,
        );
    }

    /**
     * Build the verb denylist from the seeded config.
     *
     * @return \SineMacula\RouteLinter\Rules\Support\VerbDenylist
     */
    private function buildVerbDenylist(): VerbDenylist
    {
        $config = (new ConfigRuleConfiguration)->load();

        return new VerbDenylist($config->verbDenylist, $config->remediationHints);
    }

    /**
     * Seed the route-linter config section.
     *
     * @param  array<int, array<string, list<string>|string>>  $exemptions
     * @param  array<int, string>|null  $verbDenylist
     * @return void
     */
    private function seedDefaultConfig(array $exemptions = [], ?array $verbDenylist = null): void
    {
        config()->set('route-linter.verb_denylist', $verbDenylist ?? self::VERB_DENYLIST);
        config()->set('route-linter.remediation_hints', []);
        config()->set('route-linter.exemptions', $exemptions);
        config()->set('route-linter.uncountables', []);
    }

    /**
     * Get the router bound to the booted application.
     *
     * @return \Illuminate\Routing\Router
     */
    private function getRouter(): Router
    {
        assert($this->app !== null);

        /** @var \Illuminate\Routing\Router */
        return $this->app->make('router');
    }

    /**
     * Get every error and warning in the report.
     *
     * @param  \SineMacula\RouteLinter\RouteLintReport  $report
     * @return list<\SineMacula\RouteLinter\Violation>
     */
    private function allViolations(RouteLintReport $report): array
    {
        return array_merge($report->errors(), $report->warnings());
    }

    /**
     * Get the report's violations of the given rule.
     *
     * @param  \SineMacula\RouteLinter\RouteLintReport  $report
     * @param  string  $ruleId
     * @return list<\SineMacula\RouteLinter\Violation>
     */
    private function violationsOf(RouteLintReport $report, string $ruleId): array
    {
        return array_values(array_filter($this->allViolations($report), static fn (Violation $v): bool => $v->ruleId === $ruleId));
    }

    /**
     * Collect the distinct identities of routes with a violation.
     *
     * @param  \SineMacula\RouteLinter\RouteLintReport  $report
     * @return array<int, string>
     */
    private function collectOffendingIdentities(RouteLintReport $report): array
    {
        $identities = [];

        foreach ($this->allViolations($report) as $violation) {
            $identities[$violation->routeIdentity] = true;
        }

        return array_keys($identities);
    }

    /**
     * Determine whether any violation's route identity has the given URI.
     *
     * @param  \SineMacula\RouteLinter\RouteLintReport  $report
     * @param  string  $uri
     * @return bool
     */
    private function hasViolationForUri(RouteLintReport $report, string $uri): bool
    {
        foreach ($this->allViolations($report) as $violation) {
            if (str_contains($violation->routeIdentity, ' ' . $uri . ' ') || str_ends_with($violation->routeIdentity, ' ' . $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Serialise a report to a stable string for comparison.
     *
     * @param  \SineMacula\RouteLinter\RouteLintReport  $report
     * @return string
     */
    private function serialiseReport(RouteLintReport $report): string
    {
        $lines = [];

        foreach ($report->errors() as $v) {
            $lines[] = 'E|' . $v->ruleId . '|' . $v->routeIdentity . '|' . $v->offendingSurface;
        }

        foreach ($report->warnings() as $v) {
            $lines[] = 'W|' . $v->ruleId . '|' . $v->routeIdentity . '|' . $v->offendingSurface;
        }

        foreach ($report->staleWaivers() as $key) {
            $lines[] = 'S|' . $key;
        }

        return implode("\n", $lines);
    }
}
