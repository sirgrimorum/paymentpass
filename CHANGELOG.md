# Changelog

All notable changes to PaymentPass will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.5] - 2026-03-01

### Added
- Laravel 12 support
- Minimum PHP version raised to 8.2

### Fixed
- Added `Schema::defaultStringLength(191)` to prevent MySQL "key too long" error during testing on MySQL 5.7 / strict mode

## [1.4.4] - 2026-03-01

### Added
- Scrutinizer CI configuration using PHP 8.2 and PHPUnit 10

## [1.4.3] - 2026-03-01

### Changed
- Replaced bloated `.gitignore` with a minimal set of entries
- Test suite switched to MySQL (from SQLite) for more realistic integration testing

## [1.4.2] - 2026-02-26

### Added
- Comprehensive test suite using MySQL (orchestra/testbench)

## [1.4.1] - 2026-02-24

### Fixed
- Restored truncated `translateString()` method and its helper methods that had been accidentally removed during a previous edit

## [1.4.0] - 2026-02-23

### Changed
- Updated compatibility to PHP ^8.x and Laravel ^9.0|^10.0|^11.0
- Minimum PHP raised from 7.x to 8.x

## [1.3.16] - 2024-10-08

### Fixed
- Callable execution error in webhook parameter mapping when the callable returned an unexpected type

## [1.3.15] - 2021-11-23

### Fixed
- `booleanAsStr` option now configurable per service in the config file

## [1.3.14] - 2021-11-22

### Added
- `booleanAsStr` config option to serialize boolean values as `"true"`/`"false"` strings instead of `1`/`0` (required by some payment APIs)

## [1.3.13] - 2021-11-22

### Fixed
- Further refinements to `booleanAsStr` handling

## [1.3.12] - 2021-11-22

### Changed
- Internal cleanup of parameter translation engine

## [1.3.11] - 2021-11-22

### Fixed
- Edge case in state code lookup when provider returns unexpected format

## [1.3.10] - 2021-11-22

### Fixed
- Webhook conditional (`if`) evaluation for multi-value arrays

## [1.3.9] - 2021-11-22

### Fixed
- Reference code generation with custom separator

## [1.3.8] - 2021-11-22

### Fixed
- Signature hash generation when fields array contains config prefix values

## [1.3.7] - 2021-11-22

### Fixed
- MercadoPago SDK integration edge case

## [1.3.6] - 2021-11-22

### Fixed
- Webhook URL registration for localized routes

## [1.3.5] - 2021-11-22

### Added
- MercadoPago SDK integration (`type: 'sdk'` in service config)

## [1.3.4] - 2021-11-22

### Added
- `__auto__taxReturnBase` prefix for automatic tax base calculation

## [1.3.3] - 2021-11-22

### Added
- `__auto__tax` prefix for automatic tax amount calculation from percentage and amount

---

> Versions prior to 1.3.3 are not documented here.

[Unreleased]: https://github.com/sirgrimorum/paymentpass/compare/1.4.5...HEAD
[1.4.5]: https://github.com/sirgrimorum/paymentpass/compare/1.4.4...1.4.5
[1.4.4]: https://github.com/sirgrimorum/paymentpass/compare/1.4.3...1.4.4
[1.4.3]: https://github.com/sirgrimorum/paymentpass/compare/1.4.2...1.4.3
[1.4.2]: https://github.com/sirgrimorum/paymentpass/compare/1.4.1...1.4.2
[1.4.1]: https://github.com/sirgrimorum/paymentpass/compare/1.4.0...1.4.1
[1.4.0]: https://github.com/sirgrimorum/paymentpass/compare/1.3.16...1.4.0
[1.3.16]: https://github.com/sirgrimorum/paymentpass/compare/1.3.15...1.3.16
[1.3.15]: https://github.com/sirgrimorum/paymentpass/compare/1.3.14...1.3.15
[1.3.14]: https://github.com/sirgrimorum/paymentpass/compare/1.3.13...1.3.14
[1.3.13]: https://github.com/sirgrimorum/paymentpass/compare/1.3.12...1.3.13
[1.3.12]: https://github.com/sirgrimorum/paymentpass/compare/1.3.11...1.3.12
[1.3.11]: https://github.com/sirgrimorum/paymentpass/compare/1.3.10...1.3.11
[1.3.10]: https://github.com/sirgrimorum/paymentpass/compare/1.3.9...1.3.10
[1.3.9]: https://github.com/sirgrimorum/paymentpass/compare/1.3.8...1.3.9
[1.3.8]: https://github.com/sirgrimorum/paymentpass/compare/1.3.7...1.3.8
[1.3.7]: https://github.com/sirgrimorum/paymentpass/compare/1.3.6...1.3.7
[1.3.6]: https://github.com/sirgrimorum/paymentpass/compare/1.3.5...1.3.6
[1.3.5]: https://github.com/sirgrimorum/paymentpass/compare/1.3.4...1.3.5
[1.3.4]: https://github.com/sirgrimorum/paymentpass/compare/1.3.3...1.3.4
[1.3.3]: https://github.com/sirgrimorum/paymentpass/releases/tag/1.3.3
