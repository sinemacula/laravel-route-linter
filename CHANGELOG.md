# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres
to [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-06-11

Initial release of `verifast-org/identity-php` — the shared, framework-agnostic identity model for all Verifast
domain and SDK packages: a canonical, immutable representation of a person built on top of
`verifast-org/foundation-php` and `verifast-org/documents-php`.

### Added

- `Identity` — the aggregate root DTO composing a `Person`, `ContactDetails`, `Nationality`, `SSN`, `AddressHistory`,
  a `DocumentCollection`, and identity-level metadata.
- `Person`, `ContactDetails`, `AddressRecord`, and `AddressHistory` — the high-level immutable (`readonly`) DTOs that
  model a person's name / date of birth / gender, contact channels (with primary-fallback `email()` and `phone()`
  helpers), and labelled residency history (with `current()`, `previous()`, and `history()` helpers).
- `Name`, `Nationality`, and `SSN` — identity value objects, each exposing a `from()` factory (normalize **and**
  validate raw input via the shared data normalizer) and a `make()` factory (validate an already-normalized value).
- Identity-document DTOs — concrete documents extending the `documents-php` `Document` base across `GovernmentId`,
  `Immigration`, `CivilRecords`, and `SpecialCases`, each declaring its `DocumentType` case and serializing to the
  shared `type` / `files` / `payload` / `meta` envelope. Hydration and serialization are intentionally asymmetric:
  `fromArray()` consumes already-constructed value objects, and `toArray()` is an output-only scalar projection.
- `DocumentType` and `Gender` pure enums, and `IdentityException` (`extends \RuntimeException`) as the base
  identity-layer exception.
- Infection mutation-testing gate at 90% MSI; the suite holds 100% MSI with 100% line coverage. A full-repo Infection
  config is available for diagnostic runs. PHPBench suite covering the value object, `Person`, `Identity`, and
  identity-document hydration and serialization hot paths.

### Changed

- Pre-1.0 package; no prior releases.

### Fixed

- Pre-1.0 package; no prior fixes to enumerate.

[1.0.0]: https://github.com/verifast-org/identity-php/releases/tag/v1.0.0
