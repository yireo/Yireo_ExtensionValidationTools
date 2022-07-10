# Yireo ExtensionValidationTools
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
