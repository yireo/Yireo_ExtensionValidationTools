<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Console;

use InvalidArgumentException;
use Magento\Framework\Component\ComponentRegistrar;

class ModuleResolver
{
    private ComponentRegistrar $componentRegistrar;

    public function __construct(
        ComponentRegistrar $componentRegistrar
    ) {
        $this->componentRegistrar = $componentRegistrar;
    }

    public function getPath(string $moduleName): string
    {
        $this->validateModuleName($moduleName);

        $path = (string)$this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
        $realPath = $path !== '' ? realpath($path) : false;
        if ($realPath === false || !is_dir($realPath)) {
            throw new InvalidArgumentException('Module "' . $moduleName . '" is not found');
        }

        return $realPath;
    }

    public function getNamespacePrefix(string $moduleName): string
    {
        $this->validateModuleName($moduleName);

        return str_replace('_', '\\', $moduleName) . '\\';
    }

    private function validateModuleName(string $moduleName): void
    {
        if (!preg_match('/^[A-Z][A-Za-z0-9]*_[A-Z][A-Za-z0-9]*$/', $moduleName)) {
            throw new InvalidArgumentException('Invalid module name "' . $moduleName . '"');
        }
    }
}
