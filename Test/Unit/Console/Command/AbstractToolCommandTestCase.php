<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Test\Unit\Console\Command;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Shell;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Yireo\ExtensionValidationTools\Console\ModuleResolver;
use Yireo\ExtensionValidationTools\Console\ToolRunner;

abstract class AbstractToolCommandTestCase extends TestCase
{
    protected string $root = '';
    protected string $modulePath = '';
    protected Shell&MockObject $shell;

    /**
     * @var array<int, array{0: string, 1: array}>
     */
    protected array $shellCalls = [];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yireo-evt-' . uniqid();
        mkdir($this->root . '/vendor/bin', 0777, true);
        foreach (['phpcs', 'phpstan', 'phpunit'] as $binary) {
            touch($this->root . '/vendor/bin/' . $binary);
        }

        $this->modulePath = $this->root . '/module';
        mkdir($this->modulePath);

        $this->shellCalls = [];
        $this->shell = $this->createMock(Shell::class);
    }

    protected function tearDown(): void
    {
        (new File())->deleteDirectory($this->root);
    }

    protected function getModuleResolver(): ModuleResolver
    {
        $componentRegistrar = $this->createStub(ComponentRegistrar::class);
        $componentRegistrar->method('getPath')->willReturnCallback(
            fn ($type, $name) => $name === 'Yireo_Example' ? $this->modulePath : null
        );

        return new ModuleResolver($componentRegistrar);
    }

    protected function getToolRunner(): ToolRunner
    {
        $directoryList = $this->createStub(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn($this->root);

        return new ToolRunner($this->shell, $directoryList);
    }

    /**
     * @param string[] $results Output per call, in order
     */
    protected function recordShellCalls(array $results = []): void
    {
        $this->shell->expects($this->atLeastOnce())->method('execute')->willReturnCallback(
            function (string $command, array $arguments) use ($results) {
                $this->shellCalls[] = [$command, $arguments];
                return $results[count($this->shellCalls) - 1] ?? '';
            }
        );
    }

    protected function assertArgumentsAreNotConcatenated(string $command): void
    {
        $this->assertStringNotContainsString($this->root, $command);
        $this->assertStringNotContainsString($this->modulePath, $command);
    }
}
