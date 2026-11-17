<h1 align="center">Laravel Route Linter</h1>

<div align="center">

[![Latest Stable Version](https://img.shields.io/packagist/v/sinemacula/laravel-route-linter.svg)](https://packagist.org/packages/sinemacula/laravel-route-linter)
[![PHP Version](https://img.shields.io/packagist/dependency-v/sinemacula/laravel-route-linter/php.svg)](https://packagist.org/packages/sinemacula/laravel-route-linter)
[![Laravel Version](https://img.shields.io/packagist/dependency-v/sinemacula/laravel-route-linter/laravel/framework.svg?label=laravel)](https://packagist.org/packages/sinemacula/laravel-route-linter)
[![Build Status](https://github.com/sinemacula/laravel-route-linter/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/sinemacula/laravel-route-linter/actions/workflows/tests.yml)
[![Quality Gates](https://github.com/sinemacula/laravel-route-linter/actions/workflows/quality-gates.yml/badge.svg?branch=master)](https://github.com/sinemacula/laravel-route-linter/actions/workflows/quality-gates.yml)
[![Maintainability](https://qlty.sh/gh/sinemacula/projects/laravel-route-linter/maintainability.svg)](https://qlty.sh/gh/sinemacula/projects/laravel-route-linter)
[![Code Coverage](https://qlty.sh/gh/sinemacula/projects/laravel-route-linter/coverage.svg)](https://qlty.sh/gh/sinemacula/projects/laravel-route-linter)
[![Total Downloads](https://img.shields.io/packagist/dt/sinemacula/laravel-route-linter.svg)](https://packagist.org/packages/sinemacula/laravel-route-linter)
[![License](https://img.shields.io/github/license/sinemacula/laravel-route-linter.svg)](LICENSE)

</div>

An Artisan command that lints a Laravel application's route table against a fixed set of RESTful URL conventions and
route-integrity checks. It exits non-zero on error-severity violations, so CI can gate on it. It checks only the
mechanically-checkable subset of the conventions; passing it does not prove an API is RESTful.

## How It Works

The rules run against framework-free value objects behind a small set of ports, so they carry no Laravel dependency. One
run:

1. **Sources** the app-owned routes from the router, excluding vendor routes (the set `route:list --except-vendor`
   reports).
2. **Normalises** each route: its URI split into segments, parameter names, HTTP methods, controller handler and
   gathered middleware.
3. **Inspects** every route with the per-route rules, then runs the cross-route (aggregate) rules over the whole set.
   Each violation is tagged `error` or `warning`.
4. **Suppresses** violations covered by an inline waiver or a config allowlist entry.
5. **Reports** the findings in a fixed order and exits non-zero when any `error` survives.

A malformed config value - a non-array where an array is expected, a non-string word or hint, an exemption without a
reason - raises an `InvalidConfigurationException` instead of silently weakening the verdict.

## Rules

| Rule | Severity | Checks                                                                                      |
| ---- | -------- | ------------------------------------------------------------------------------------------- |
| R1   | error    | No action verb in a path segment (incl. compound / pluralised), with a RESTful-rewrite hint |
| R2   | error    | Segments are kebab-case                                                                     |
| R3   | error    | Segments are lowercase                                                                      |
| R4   | error    | Collection segments are plural (honours configured uncountables; singletons are exempt)     |
| R5   | error    | No trailing or duplicate slashes                                                            |
| R6   | error    | No duplicate route name (would break `route()` URL generation)                              |
| R7   | error    | Standard HTTP methods only                                                                  |
| R8   | warning  | Named routes follow `{resource}.{action}`                                                   |
| R9   | warning  | No HTML-only `create` / `edit` action as the final literal segment                          |
| R10  | warning  | Routes matching a configured pattern declare the required middleware                        |
| R11  | warning  | Resource nesting no deeper than the configured number of collection levels (default three)  |
| R12  | error    | Route handler (controller class / method) exists                                            |

Rule IDs are stable across releases, so waivers that name a rule stay valid on upgrade.

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

Exits non-zero when any error-severity violation is present or the config is invalid. Warnings are reported but do
not fail the run.

## Waiving a Violation

Every waiver needs a written reason and can be scoped to specific rules. Waivers that suppress nothing, and allowlist
entries that match no live route, are reported as stale entries. Stale entries are reported but do not fail the run.

**Inline, at the route:**

```php
Route::patch('photos/{photo}/edit', [PhotoController::class, 'edit'])
    ->ignoreRouteLint(['R9'], 'Legacy admin UI');      // waives only R9 on this route

Route::get('legacy/getStats', LegacyStatsController::class)
    ->ignoreRouteLint([], 'Frozen v1 contract');       // [] = all rules
```

The waiver is stored in the route action, so it survives `route:cache`.

**Config allowlist, for routes you cannot annotate:**

```php
// config/route-linter.php
'exemptions' => [
    ['match' => 'photos.edit', 'rules' => ['R9'], 'reason' => 'Legacy admin UI'],     // per-rule
    ['match' => 'legacy/*',                       'reason' => 'Frozen v1 contract'],  // rules omitted = all
],
```

`match` is an exact route name or an `fnmatch` pattern matched against the URI.

## Tuning

Listing a word in `verb_allowlist` removes it from the verb denylist without redefining the curated list. It is rule
tuning, not a per-route waiver: use it for domain nouns that share a verb's spelling (e.g. a `password-resets` or
`transfers` resource). It applies to every route and needs no reason. A published `verb_denylist` replaces the shipped
list wholesale, so leave it out of your published config to keep receiving the package's additions. The maximum nesting
depth enforced by R11 is set with `nesting_max_depth` (default `3`); only segments that name a collection count, so
namespace prefixes such as `auth/` or `admin/` add no depth.

Singleton resources (`Route::singleton()` / `Route::apiSingleton()`) are singular by design, so R4 and R11 skip their
segment. Their `show`, `update` and `destroy` routes are recognised automatically; list the resource path in
`singleton_resources` (e.g. `'profile'` or `'connections/{connection}/callback'`) to cover the rest, such as a creatable
singleton's `store` route.

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

The `rules` key lists the rules the engine runs, in order. Each is a class implementing the `Rule` contract, resolved
from the container so it may declare constructor dependencies. Remove a built-in rule by deleting its line, or append
your own:

```php
// config/route-linter.php
'rules' => [
    \SineMacula\RouteLinter\Rules\VerbInPathRule::class,
    // ...the built-in rules...
    \App\RouteLinting\NoSnakeCaseRule::class,
],
```

A custom rule receives the normalised route - its segments, brace-stripped parameter names, controller handler
(`Class@method`, or `null` for closures) and gathered middleware - with the active config, and returns zero or more
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

For checks that span the whole route table, such as duplicate detection, implement `AggregateRule` instead. Its
`inspect(array $routes, RuleConfig $config)` receives every normalised route at once and runs after the per-route rules.
List it in the same `rules` key. Attribute each violation to the offending route's `identity()` so waivers still apply.

Rule IDs must be unique across both kinds; a duplicate raises an `InvalidConfigurationException` when the engine is
built. To change the output, for example to emit JSON or SARIF, bind your own `LintReporter` implementation.

## Determinism

The same route definitions and config give the same output on every run, whether or not routes are cached. A cached
table has lost what the rules depend on: closures are serialised and unnamed routes carry random generated names. So
while routes are cached, `route:lint` re-runs itself in a child process with the route cache bypassed. The child boots
as a fresh `php artisan` would, with the same process environment and any `--env` you pass. The cache file is never read
or modified.

## Requirements

- PHP ^8.3
- Laravel ^12.9 || ^13.0

## Testing

```bash
composer test                # Test suite, in parallel via Paratest
composer test:coverage       # Test suite with a clover coverage report
composer test:mutation       # Mutation-testing gate (Infection), scoped to the diff against master
composer test:mutation:full  # Full mutation run, no thresholds
composer check               # Static analysis and linting via qlty
composer format              # Formatting via qlty
composer smells              # Code smells (duplication, complexity) via qlty
composer bench               # PHPBench benchmarks
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Report vulnerabilities privately; see [SECURITY.md](SECURITY.md).

## License

Licensed under the [Apache License, Version 2.0](https://www.apache.org/licenses/LICENSE-2.0).
