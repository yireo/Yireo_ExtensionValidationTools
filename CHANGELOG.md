# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.0.3] - 25 November 2026
### Fixed
- Shell command injection via the module name, PHPCS severity and PHPStan level arguments; arguments are now escaped and severity/level validated
- PHPCS, PHPStan and PHPUnit binaries were picked up from the current working directory instead of the Magento root
- The unit test generator instantiated arbitrary classes via the ObjectManager; it now only inspects classes of the given module via reflection and never overwrites an existing test
- `version_match` accepted any file path; it now only reads an existing `composer.json` file
- Untrusted values in console output could inject terminal control characters or formatting tags
- Commands returned wrong exit codes and hid the output of PHPCS, PHPStan and PHPUnit
- Running the tools wrote a `phpstan.neon` into the Magento root and changed the global PHPCS `installed_paths` setting
- Broken with recent ExtensionChecker versions (removed `Scan\Composer` class), Symfony 7 and PHP 8.4

### Changed
- Require `nette/php-generator` and `composer/semver` explicitly and replace wildcard version constraints of `magento/framework` and `yireo/magento2-extensionchecker`
