# Yireo ExtensionValidationTools

<!-- badges.specs.start -->
<div class="github-specs-badges">

![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://img.shields.io/packagist/v/yireo/magento2-extension-validation-tools)

</div>
<!-- badges.specs.end -->

This extension validates the code of other extensions and is complementary to static code analysis tools like PHPCS.

## Example 1: Comparing composer versions

    bin/magento extension:validate:version_match /path/to/your/extension/composer.json

This command allows you to scan a given `composer.json` of some extension, without that extension being installed. This allows you to modify the dependencies of that extension, before trying to install the extension (which could save valuable time).

The output should be empty to be successful. When unmatched versions are found, it might look like the following:

    ERROR: "magento/framework:103.0.0" does not match required version "^100.1|^101.0|^102.0"

## Example 3: Creating unit tests (@todo: Move this to another module
```bash
bin/magento extension:validate:generate-unit-test --module Yireo_Webp2 --class '\Yireo\Webp2\Convertor\Convertor'
```

## Current status

<!-- badges.test.start -->
<div class="github-pipeline-badges">

![Static Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ExtensionValidationTools/static-tests.yml?label=static-tests)
![Unit Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ExtensionValidationTools/unit-tests.yml?label=unit-tests)
![Integration Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ExtensionValidationTools/integration-tests.yml?label=integration-tests)
![Playwright](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ExtensionValidationTools/playwright.yml?label=playwright)
![DI Compilation](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ExtensionValidationTools/compile.yml?label=compile)

</div>
<!-- badges.test.end -->
