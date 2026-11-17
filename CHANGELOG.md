# Changelog

## [1.3.0](https://github.com/sinemacula/laravel-route-linter/compare/v1.2.1...v1.3.0) (2026-11-17)


### Features

* add a verb allowlist for domain nouns that share a verb's spelling ([#88](https://github.com/sinemacula/laravel-route-linter/issues/88)) ([2bd4103](https://github.com/sinemacula/laravel-route-linter/commit/2bd410348d6e1c2d1e32be6a8346452ce59312da))


### Bug Fixes

* exempt singleton resources and namespace segments from the collection rules ([#90](https://github.com/sinemacula/laravel-route-linter/issues/90)) ([0382202](https://github.com/sinemacula/laravel-route-linter/commit/0382202a0d4f01f08e62844764b1348f80ab483e))
* lint the current route definitions when the route table is cached ([#87](https://github.com/sinemacula/laravel-route-linter/issues/87)) ([b22d887](https://github.com/sinemacula/laravel-route-linter/commit/b22d887b95c89c684ff1f49f37fad5db93558842))

## [1.2.1](https://github.com/sinemacula/laravel-route-linter/compare/v1.2.0...v1.2.1) (2026-11-10)


### Bug Fixes

* clear the qlty findings and drop configuration the shared source provides ([#75](https://github.com/sinemacula/laravel-route-linter/issues/75)) ([a1d83f1](https://github.com/sinemacula/laravel-route-linter/commit/a1d83f1504937ca68ae7aaef3a8e918e83d8530e))

## [1.2.0](https://github.com/sinemacula/laravel-route-linter/compare/v1.1.0...v1.2.0) (2026-07-22)


### Features

* support Laravel 13 ([#26](https://github.com/sinemacula/laravel-route-linter/issues/26)) ([28151f9](https://github.com/sinemacula/laravel-route-linter/commit/28151f995a311bcdee3e043f511c21645bb5f12d))

## [1.1.0](https://github.com/sinemacula/laravel-route-linter/compare/v1.0.0...v1.1.0) (2026-06-18)


### Features

* add route-integrity rules and a cross-route rule pass ([#3](https://github.com/sinemacula/laravel-route-linter/issues/3)) ([6d1c2b6](https://github.com/sinemacula/laravel-route-linter/commit/6d1c2b6a4d4ace9a6f0b0282c9b754298c91853e))

## 1.0.0 (2026-06-18)


### Features

* deterministic, opt-in RESTful route linter for Laravel ([#1](https://github.com/sinemacula/laravel-route-linter/issues/1)) ([91a6b9f](https://github.com/sinemacula/laravel-route-linter/commit/91a6b9f7aef0924b828b0bc3e432e83f8fcd763b))
