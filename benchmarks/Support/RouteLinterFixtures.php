<?php

namespace Benchmarks\Support;

use SineMacula\RouteLinter\Contracts\Inflector;
use SineMacula\RouteLinter\Dto\AllowlistEntry;
use SineMacula\RouteLinter\Dto\RouteDescriptor;
use SineMacula\RouteLinter\Dto\RouteSuppression;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Inflection\FrameworkInflector;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\RouteLintEngine;
use SineMacula\RouteLinter\Rules\ApiResourceAlignmentRule;
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
use SineMacula\RouteLinter\Severity;
use SineMacula\RouteLinter\Violation;

/**
 * Shared fixture builders for the route-linter benchmark suite.
 *
 * Mirrors the production wiring assembled by the service provider - the same
 * rule set, in the same fixed order, fed the same shipped default config (verb
 * denylist, remediation hints, uncountables) - so the benchmarks measure the
 * real hot paths rather than a toy configuration. All builders are pure and
 * deterministic: the generated route tables, allowlists, and violation sets are
 * a fixed function of their size argument, so two runs measure identical work.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
trait RouteLinterFixtures
{
    /** @var array<int, string> The shipped default verb denylist (mirrors config/route-linter.php). */
    private const array VERB_DENYLIST = [
        'get', 'create', 'update', 'delete', 'edit', 'store', 'fetch', 'list',
        'show', 'cancel', 'activate', 'deactivate', 'login', 'logout', 'register',
        'search', 'add', 'remove', 'check', 'process', 'submit', 'send', 'transfer',
        'download', 'upload', 'import', 'export', 'validate', 'generate', 'refresh',
    ];

    /** @var array<string, string> The shipped default per-verb remediation hints (mirrors config/route-linter.php). */
    private const array REMEDIATION_HINTS = [
        'login'    => 'POST /auth',
        'logout'   => 'DELETE /auth',
        'register' => 'POST /users',
        'get'      => 'GET /{resources}',
        'list'     => 'GET /{resources}',
        'fetch'    => 'GET /{resources}/{resource}',
        'show'     => 'GET /{resources}/{resource}',
        'create'   => 'POST /{resources} (create a new resource)',
        'cancel'   => 'PATCH /{resources}/{resource} (set a status field)',
        'search'   => 'GET /{resources}?q=',
        'transfer' => 'PATCH /{resources}/{resource}, or model the transfer as its own resource',
    ];

    /** @var array<int, string> The shipped default inflector uncountables (mirrors config/route-linter.php). */
    private const array UNCOUNTABLES = ['media', 'data', 'series', 'information', 'news', 'feedback', 'metadata'];

    /** @var array<int, string> Plural resource nouns cycled to vary the generated route tables. */
    private const array RESOURCES = [
        'users', 'orders', 'products', 'invoices', 'payments',
        'addresses', 'documents', 'sessions', 'categories', 'teams',
    ];

    /** @var string Conventional API version prefix prepended to the generated route URIs. */
    private const string API_PREFIX = 'api/v1/';

    /** @var string Conventional resource-identifier parameter segment. */
    private const string ID_PARAM = '/{id}';

    /**
     * Build a framework inflector primed with the shipped uncountables.
     *
     * @return \SineMacula\RouteLinter\Inflection\FrameworkInflector
     */
    private function inflector(): FrameworkInflector
    {
        return new FrameworkInflector(self::UNCOUNTABLES);
    }

    /**
     * Build a verb denylist primed with the shipped default verbs and hints.
     *
     * @return \SineMacula\RouteLinter\Rules\Support\VerbDenylist
     */
    private function verbDenylist(): VerbDenylist
    {
        return new VerbDenylist(self::VERB_DENYLIST, self::REMEDIATION_HINTS);
    }

    /**
     * Build the rule-config DTO from the shipped defaults.
     *
     * @param  array<int, \SineMacula\RouteLinter\Dto\AllowlistEntry>  $exemptions
     * @return \SineMacula\RouteLinter\Dto\RuleConfig
     */
    private function ruleConfig(array $exemptions = []): RuleConfig
    {
        return new RuleConfig(
            verbDenylist: self::VERB_DENYLIST,
            remediationHints: self::REMEDIATION_HINTS,
            exemptions: $exemptions,
            uncountables: self::UNCOUNTABLES,
        );
    }

    /**
     * Assemble the full rule engine in the same fixed order as the service
     * provider.
     *
     * @param  \SineMacula\RouteLinter\Contracts\Inflector  $inflector
     * @return \SineMacula\RouteLinter\RouteLintEngine
     */
    private function engine(Inflector $inflector): RouteLintEngine
    {
        return new RouteLintEngine(
            new VerbInPathRule(new SegmentNormaliser($inflector), $this->verbDenylist()),
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
     * Normalise a raw URI into a NormalisedRoute the same way the use case
     * does.
     *
     * Splits on `/` preserving empty segments, then extracts brace-wrapped
     * parameter names - identical to the private pipeline in {@see LintRoutes}.
     *
     * @param  string  $uri
     * @param  array<int, string>  $methods
     * @param  string|null  $name
     * @return \SineMacula\RouteLinter\NormalisedRoute
     */
    private function normalisedRoute(string $uri, array $methods, ?string $name): NormalisedRoute
    {
        $segments   = explode('/', $uri);
        $parameters = [];

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '{')) {
                $parameters[] = trim($segment, '{}');
            }
        }

        return new NormalisedRoute(
            uri: $uri,
            methods: $methods,
            name: $name,
            segments: $segments,
            parameters: $parameters,
        );
    }

    /**
     * Build a route table of fully RESTful, violation-free routes.
     *
     * Represents the steady-state cost a passing CI run pays: every route is
     * inspected by all nine rules but none produce a finding.
     *
     * @param  int  $size
     * @return array<int, \SineMacula\RouteLinter\Dto\RouteDescriptor>
     */
    private function cleanRouteTable(int $size): array
    {
        $descriptors = [];

        for ($i = 0; $i < $size; $i++) {
            $descriptors[] = $this->cleanDescriptor(self::RESOURCES[$i % count(self::RESOURCES)], $i);
        }

        return $descriptors;
    }

    /**
     * Build a realistic route table mixing clean routes with recurring defects.
     *
     * Roughly one route in four is a defect drawn from a fixed catalogue that
     * exercises every error and warning rule, plus an inline suppression - so
     * the benchmark covers the violation, suppression, and reporting paths the
     * clean table never reaches.
     *
     * @param  int  $size
     * @return array<int, \SineMacula\RouteLinter\Dto\RouteDescriptor>
     */
    private function mixedRouteTable(int $size): array
    {
        $descriptors = [];

        for ($i = 0; $i < $size; $i++) {
            $descriptors[] = $i % 4 === 3
                ? $this->dirtyDescriptor($i)
                : $this->cleanDescriptor(self::RESOURCES[$i % count(self::RESOURCES)], $i);
        }

        return $descriptors;
    }

    /**
     * Build the exemption allowlist paired with the mixed route table.
     *
     * One entry waives a live violation (exercising the matched-and-used path);
     * the other matches no live route (exercising the stale-waiver path).
     *
     * @return array<int, \SineMacula\RouteLinter\Dto\AllowlistEntry>
     */
    private function mixedExemptions(): array
    {
        return [
            new AllowlistEntry('things.index', 'BL-1 non-standard verb is a vendor health probe', ['R7']),
            new AllowlistEntry('legacy.*', 'BL-2 retired surface awaiting removal'),
        ];
    }

    /**
     * Build a representative allowlist of mixed name and URI-glob entries.
     *
     * @param  int  $size
     * @return array<int, \SineMacula\RouteLinter\Dto\AllowlistEntry>
     */
    private function allowlistEntries(int $size): array
    {
        $entries = [];

        for ($i = 0; $i < $size; $i++) {
            $resource  = self::RESOURCES[$i % count(self::RESOURCES)];
            $entries[] = $i % 2 === 0
                ? new AllowlistEntry($resource . '.index', 'BL-' . $i . ' named-route waiver', ['R4'])
                : new AllowlistEntry(self::API_PREFIX . $resource . '/*', 'BL-' . $i . ' glob waiver');
        }

        return $entries;
    }

    /**
     * Build a representative set of violations for the report-ordering
     * benchmark.
     *
     * Identities, rule IDs, and severities are spread across the set so the
     * deterministic composite-key sort has real comparison work to do.
     *
     * @param  int  $count
     * @return array<int, \SineMacula\RouteLinter\Violation>
     */
    private function violations(int $count): array
    {
        $rules      = ['R1', 'R2', 'R3', 'R4', 'R8', 'R9', 'R11'];
        $violations = [];

        for ($i = 0; $i < $count; $i++) {
            $resource     = self::RESOURCES[$i % count(self::RESOURCES)];
            $ruleId       = $rules[$i % count($rules)];
            $violations[] = new Violation(
                ruleId: $ruleId,
                severity: $i % 3 === 0 ? Severity::Warning : Severity::Error,
                routeIdentity: 'GET api/v1/' . $resource . '/' . ($count - $i),
                offendingSurface: $resource . '-' . $i,
                remediationHint: null,
            );
        }

        return $violations;
    }

    /**
     * Build one fully RESTful, violation-free route for the given resource.
     *
     * Cycles the five core resource actions so the generated table carries a
     * realistic spread of methods, depths, and route names.
     *
     * @param  string  $resource
     * @param  int  $i
     * @return \SineMacula\RouteLinter\Dto\RouteDescriptor
     */
    private function cleanDescriptor(string $resource, int $i): RouteDescriptor
    {
        return match ($i % 5) {
            0       => new RouteDescriptor(self::API_PREFIX . $resource, ['GET', 'HEAD'], $resource . '.index', false),
            1       => new RouteDescriptor(self::API_PREFIX . $resource, ['POST'], $resource . '.store', false),
            2       => new RouteDescriptor(self::API_PREFIX . $resource . self::ID_PARAM, ['GET', 'HEAD'], $resource . '.show', false),
            3       => new RouteDescriptor(self::API_PREFIX . $resource . self::ID_PARAM, ['PATCH'], $resource . '.update', false),
            default => new RouteDescriptor(self::API_PREFIX . $resource . self::ID_PARAM, ['DELETE'], $resource . '.destroy', false),
        };
    }

    /**
     * Build one defective route from a fixed catalogue of recurring violations.
     *
     * Cycles six defects spanning verb-in-path, casing, plurality, non-standard
     * methods, HTML-form actions, and excessive nesting; the HTML-form variant
     * also carries an inline suppression that fires.
     *
     * @param  int  $i
     * @return \SineMacula\RouteLinter\Dto\RouteDescriptor
     */
    private function dirtyDescriptor(int $i): RouteDescriptor
    {
        return match ($i % 6) {
            0 => new RouteDescriptor('api/v1/getUsers', ['GET', 'HEAD'], 'get-users', false),
            1 => new RouteDescriptor('api/v1/orders/{order}/cancel', ['POST'], 'orders.cancel', false),
            2 => new RouteDescriptor('api/v1/user/{user}', ['GET', 'HEAD'], 'user.show', false),
            3 => new RouteDescriptor('api/v1/things', ['GET', 'PURGE'], 'things.index', false),
            4 => new RouteDescriptor('api/v1/products/edit', ['GET', 'HEAD'], 'products.edit', false, [
                new RouteSuppression(['R9'], 'BL-3 legacy HTML form pending migration'),
            ]),
            default => new RouteDescriptor(
                'api/v1/teams/{team}/projects/{project}/tasks/{task}/comments',
                ['GET', 'HEAD'],
                'teams.projects.tasks.comments.index',
                false,
            ),
        };
    }
}
