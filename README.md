# Laravel Route Linter

[![Latest Stable Version](https://img.shields.io/packagist/v/sinemacula/laravel-route-linter.svg)](https://packagist.org/packages/sinemacula/laravel-route-linter)
[![Build Status](https://github.com/sinemacula/laravel-route-linter/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/sinemacula/laravel-route-linter/actions/workflows/tests.yml)
[![Quality Gates](https://github.com/sinemacula/laravel-route-linter/actions/workflows/quality-gates.yml/badge.svg?branch=master)](https://github.com/sinemacula/laravel-route-linter/actions/workflows/quality-gates.yml)
[![Maintainability](https://qlty.sh/gh/sinemacula/projects/laravel-route-linter/maintainability.svg)](https://qlty.sh/gh/sinemacula/projects/laravel-route-linter)
[![Code Coverage](https://qlty.sh/gh/sinemacula/projects/laravel-route-linter/coverage.svg)](https://qlty.sh/gh/sinemacula/projects/laravel-route-linter)
[![Total Downloads](https://img.shields.io/packagist/dt/sinemacula/laravel-route-linter.svg)](https://packagist.org/packages/sinemacula/laravel-route-linter)

A deterministic, opt-in Artisan command that lints a Laravel application's route table against a fixed catalogue of
RESTful URL conventions and route-integrity checks, and exits non-zero on error-severity violations so CI can gate on
it.

It reads the live route table (`Router::getRoutes()` after a full boot) plus its own config - no model versions, no
probabilistic inference - so the same routes and config always produce the same verdict. It enforces the
mechanically-checkable convention subset only; it is not a proof of true RESTfulness.

## How It Works

The linter is built around a small set of ports and adapters, so the rule logic carries no framework dependency. One
invocation walks the whole route table once:

1. **Source** the app-owned routes from the live router, excluding vendor routes (the same set `route:list
   --except-vendor` reports).
2. **Normalise** each route into a framework-free value object - its URI split into segments, its parameter names, its
   HTTP methods, its controller handler, and its gathered middleware.
3. **Inspect** every route with the ordered per-route rules, then run the cross-route (aggregate) rules over the whole
   set; each rule returns zero or more violations tagged `error` or `warning`.
4. **Suppress** any violation covered by an inline waiver or a config allowlist entry.
5. **Report** the findings in a deterministic total order and exit non-zero when any `error`-severity violation
   survives.

A few principles hold across the surface:

- **Opt-in and deterministic.** Nothing runs until you call `route:lint`, and the same route table plus the same config
  yields a byte-identical verdict on every run, independent of route-cache state.
- **Every waiver is justified and per-rule.** Waivers require a written reason and target specific rules. Unused
  waivers - and allowlist entries matching no live route - are surfaced as stale entries so they cannot rot (reported,
  but they do not gate).
- **Misconfiguration fails loud.** A malformed config value (a non-array where an array is expected, an exemption
  missing its reason) raises an `InvalidConfigurationException` rather than silently weakening the verdict.

## Rules

| Rule | Severity | Checks                                                                                      |
| ---- | -------- | ------------------------------------------------------------------------------------------- |
| R1   | error    | No action verb in a path segment (incl. compound / pluralised), with a RESTful-rewrite hint |
| R2   | error    | Segments are kebab-case                                                                     |
| R3   | error    | Segments are lowercase                                                                      |
| R4   | error    | Collection segments are plural (honours configured uncountables)                            |
| R5   | error    | No trailing or duplicate slashes                                                            |
| R6   | error    | No duplicate route name (would break `route()` URL generation)                              |
| R7   | error    | Standard HTTP methods only                                                                  |
| R8   | warning  | Named routes follow `{resource}.{action}`                                                   |
| R9   | warning  | No HTML-only `create` / `edit` action as the final literal segment on an API surface        |
| R10  | warning  | Routes matching a configured pattern declare the required middleware                        |
| R11  | warning  | Resource nesting no deeper than the configured number of collection levels (default three)  |
| R12  | error    | Route handler (controller class / method) exists                                            |

> [!NOTE]
> Rule IDs are stable across releases - a rule keeps its ID for life, so waivers and CI gates that pin
> an ID stay valid on upgrade.

## Installation

```bash
composer require --dev sinemacula/laravel-route-linter
```

The service provider is auto-discovered. Publish the config to tune it:

```bash
php artisan vendor:publish --tag=route-linter-config
```

## Usage

```bash
php artisan route:lint
```

Exits non-zero when any **error-severity** violation is present (warnings are reported but do not gate). Run it as a
step in CI.

## Waiving a Violation

Every waiver requires a written reason and is **per-rule**. Unused waivers (and allowlist entries matching no live
route) are surfaced as stale entries so they cannot rot - these are reported but do not gate.

**Inline (preferred) - co-located at the route:**

```php
Route::patch('photos/{photo}/edit', [PhotoController::class, 'edit'])
    ->ignoreRouteLint(['R9'], 'legacy admin UI - BL-123');   // waives only R9 on this route

Route::get('legacy/getStats', LegacyStatsController::class)
    ->ignoreRouteLint([], 'frozen v1 contract - BL-200');    // [] = all rules
```

Stored in the route action (survives `route:cache`).

**Config allowlist - for routes you cannot annotate:**

```php
// config/route-linter.php
'exemptions' => [
    ['match' => 'photos.edit', 'rules' => ['R9'], 'reason' => 'BL-123'],  // per-rule
    ['match' => 'legacy.*',                       'reason' => 'BL-200'],  // rules omitted = all
],
```

## Tuning

Removing a word from `verb_denylist` is **rule tuning**, not a per-route waiver - use it for legitimate domain-noun
homographs (e.g. a real `transfer` resource). This is global and needs no reason. The maximum nesting depth enforced by
R11 is set with `nesting_max_depth` (default `3`).

R10 (required middleware) is opt-in and ships empty. Map an `fnmatch` URI pattern to the middleware a matching route
must declare; matching is an exact token comparison, so write parameterised middleware in full:

```php
// config/route-linter.php
'required_middleware' => [
    'admin/*' => ['auth', 'can:access-admin'],
    'api/*'   => ['auth:sanctum'],
],
```

## Extending

The rule set is the product surface, and it is configurable. The `rules` key lists the rules the engine runs, in order;
each is a class implementing the `Rule` contract and is resolved from the container, so rules may declare constructor
dependencies. Remove a built-in by deleting its line, or append your own:

```php
// config/route-linter.php
'rules' => [
    \SineMacula\RouteLinter\Rules\VerbInPathRule::class,
    // …the built-in rules…
    \App\RouteLinting\NoSnakeCaseRule::class,    // your custom rule
],
```

A custom rule receives the normalised route - its segments, brace-stripped parameter names, controller handler
(`Class@method`, or `null` for closures), and gathered middleware - and the active config, and returns zero or more
violations:

```php
use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\Violation;

class NoSnakeCaseRule implements Rule
{
    public function id(): string { return 'APP1'; }

    public function severity(): Severity { return Severity::ERROR; }

    public function inspect(NormalisedRoute $route, RuleConfig $config): array
    {
        $offenders = array_filter($route->segments, static fn (string $s): bool => str_contains($s, '_'));

        return array_map(fn (string $s): Violation => new Violation(
            ruleId: $this->id(),
            severity: $this->severity(),
            routeIdentity: $route->identity(),
            offendingSurface: $s,
            remediationHint: null,
        ), array_values($offenders));
    }
}
```

For checks that span the whole route table rather than one route at a time - duplicate detection, table-wide invariants

- implement `AggregateRule` instead. Its `inspect(array $routes, RuleConfig $config)` receives every normalised route at
once and runs in a single pass after the per-route rules. List it in the same `rules` key; the engine partitions the two
kinds by contract. Attribute each violation to the offending route's `identity()` so per-rule waivers still apply.

Rule IDs must be unique across both kinds - the engine rejects a duplicate at boot. Output rendering is a port too: bind
your own `LintReporter` implementation (for example, to emit JSON or SARIF for CI) to replace the default console
reporter.

## Determinism

The same route table plus the same config yields a byte-identical verdict on every run, independent of route-cache
state. It enforces the mechanically-checkable convention subset only - it is not a proof of true RESTfulness.

## Requirements

- PHP ^8.3
- Laravel ^12.9

## Testing

```bash
composer test                # Run the test suite in parallel using Paratest
composer test:coverage       # With clover coverage report
composer test:mutation       # Mutation-testing gate (Infection) - the enforced MSI floor
composer test:mutation:full  # Full mutation suite, no thresholds (scheduled audit run)
composer check               # Static analysis and lint checks via qlty
composer format              # Format the codebase via qlty
composer smells              # Advisory code smells (duplication, complexity)
composer bench               # Run the PHPBench benchmarks
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of notable changes.

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on branching, commits, code
quality, and pull requests.

## Security

If you discover a security vulnerability, please report it responsibly. See [SECURITY.md](SECURITY.md) for the
disclosure policy and contact details.

## License

Licensed under the [Apache License, Version 2.0](https://www.apache.org/licenses/LICENSE-2.0).
