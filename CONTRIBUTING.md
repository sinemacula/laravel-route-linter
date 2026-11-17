# Contributing

Contributions are welcome as GitHub pull requests.

## Requirements

- PHP 8.3+
- Composer 2

## Getting Started

```bash
git clone git@github.com:sinemacula/laravel-route-linter.git
cd laravel-route-linter
composer install
```

## Development Workflow

### Branching

Branch from `master` using the appropriate prefix:

| Prefix      | Purpose                          |
|-------------|----------------------------------|
| `feature/`  | New functionality                |
| `bugfix/`   | Bug fixes                        |
| `hotfix/`   | Urgent production fixes          |
| `refactor/` | Refactoring without new features |
| `chore/`    | Tooling, CI, dependencies        |

### Commits

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/):

```text
feat: add a custom route-linting rule
fix: preserve brace-stripped parameters through normalisation
test: cover the verb-in-path compound-segment boundary
chore: update qlty configuration
```

### Code Quality

Run these through qlty before opening a pull request:

```bash
composer check    # Static analysis and linting
composer format   # Formatting
composer smells   # Code smells (duplication, complexity)
```

### Testing

```bash
composer test                # Test suite, in parallel via Paratest
composer test:coverage       # Test suite with a clover coverage report
composer test:mutation       # Mutation-testing gate (Infection), scoped to the diff against master
composer test:mutation:full  # Full mutation run, no thresholds
```

Single test file or method:

```bash
vendor/bin/phpunit tests/Unit/Rules/VerbInPathRuleTest.php
vendor/bin/phpunit --filter testFlagsGetUsersWithRemediationHint tests/Unit/Rules/VerbInPathRuleTest.php
```

### Standards

- PHPStan level 9, as run by qlty
- Full type hints on parameters and return types
- PHPDoc on all classes, methods, properties and constants
- Tests for new behaviour; the mutation gate (`composer test:mutation`) requires every mutant in the diff to be killed

## Pull Requests

- Keep each pull request to a single concern
- Discuss changes to static analysis or formatting configuration first
- Include tests for new or changed behaviour
- `composer check` and `composer test` must pass

## Security

Report vulnerabilities privately, not as public issues; see [SECURITY.md](SECURITY.md).

## License

By contributing, you agree that your contributions will be licensed under the [Apache License 2.0](LICENSE).
