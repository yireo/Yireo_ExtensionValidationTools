<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Test\Unit\Console;

use InvalidArgumentException;
use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yireo\ExtensionValidationTools\Console\ModuleResolver;

class ModuleResolverTest extends TestCase
{
    public function testGetPathReturnsRealModulePath(): void
    {
        $moduleResolver = new ModuleResolver($this->getComponentRegistrar(__DIR__ . '/../../..'));
        $this->assertSame(realpath(__DIR__ . '/../../..'), $moduleResolver->getPath('Yireo_Example'));
    }

    #[DataProvider('getInvalidModuleNames')]
    public function testGetPathRejectsInvalidModuleName(string $moduleName): void
    {
        $componentRegistrar = $this->createMock(ComponentRegistrar::class);
        $componentRegistrar->expects($this->never())->method('getPath');

        $this->expectException(InvalidArgumentException::class);
        (new ModuleResolver($componentRegistrar))->getPath($moduleName);
    }

    public static function getInvalidModuleNames(): array
    {
        return [
            'empty' => [''],
            'shell injection' => ['Foo_Bar;id'],
            'path traversal' => ['../Foo_Bar'],
            'no underscore' => ['FooBar'],
            'lowercase vendor' => ['foo_Bar'],
        ];
    }

    public function testGetPathRejectsUnknownModule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ModuleResolver($this->getComponentRegistrar(null)))->getPath('Yireo_Unknown');
    }

    public function testGetPathRejectsMissingFolder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ModuleResolver($this->getComponentRegistrar('/does/not/exist')))->getPath('Yireo_Example');
    }

    public function testGetNamespacePrefix(): void
    {
        $moduleResolver = new ModuleResolver($this->createStub(ComponentRegistrar::class));
        $this->assertSame('Yireo\\Example\\', $moduleResolver->getNamespacePrefix('Yireo_Example'));
    }

    private function getComponentRegistrar(?string $path): ComponentRegistrar
    {
        $componentRegistrar = $this->createStub(ComponentRegistrar::class);
        $componentRegistrar->method('getPath')->willReturn($path);
        return $componentRegistrar;
    }
}
