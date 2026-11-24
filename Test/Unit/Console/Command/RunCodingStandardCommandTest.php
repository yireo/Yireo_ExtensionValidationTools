<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Test\Unit\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\ExtensionValidationTools\Console\Command\RunCodingStandardCommand;

class RunCodingStandardCommandTest extends AbstractToolCommandTestCase
{
    public function testRunsPhpcsWithEscapedArguments(): void
    {
        $this->recordShellCalls(['The installed coding standards are PSR12 and Magento2']);

        $commandTester = $this->getCommandTester();
        $this->assertSame(Command::SUCCESS, $commandTester->execute(['module' => 'Yireo_Example', 'severity' => '9']));

        $this->assertCount(2, $this->shellCalls);
        [$command, $arguments] = $this->shellCalls[1];
        $this->assertSame('cd %s && %s --standard=Magento2 --colors --severity=%s %s', $command);
        $this->assertSame([$this->root, $this->root . '/vendor/bin/phpcs', '9', realpath($this->modulePath)], $arguments);
        $this->assertArgumentsAreNotConcatenated($command);
    }

    public function testUsesRuntimeSetWhenStandardIsMissing(): void
    {
        $this->recordShellCalls(['The installed coding standards are PSR12']);

        $this->assertSame(Command::SUCCESS, $this->getCommandTester()->execute(['module' => 'Yireo_Example']));

        $this->assertCount(2, $this->shellCalls);
        [$command, $arguments] = $this->shellCalls[1];
        $this->assertStringContainsString('--runtime-set installed_paths %s', $command);
        $this->assertStringNotContainsString('--config-set', $command);
        $this->assertContains($this->root . '/vendor/magento/magento-coding-standard', $arguments);
        $this->assertContains('7', $arguments);
    }

    public function testRejectsInjectedSeverity(): void
    {
        $this->shell->expects($this->never())->method('execute');

        $commandTester = $this->getCommandTester();
        $this->assertSame(Command::FAILURE, $commandTester->execute(['module' => 'Yireo_Example', 'severity' => '7; id']));
        $this->assertStringContainsString('Severity should be an integer', $commandTester->getDisplay());
    }

    public function testRejectsUnknownModule(): void
    {
        $this->shell->expects($this->never())->method('execute');

        $commandTester = $this->getCommandTester();
        $this->assertSame(Command::FAILURE, $commandTester->execute(['module' => 'Yireo_Unknown']));
        $this->assertStringContainsString('is not found', $commandTester->getDisplay());
    }

    private function getCommandTester(): CommandTester
    {
        return new CommandTester(new RunCodingStandardCommand($this->getModuleResolver(), $this->getToolRunner()));
    }
}
