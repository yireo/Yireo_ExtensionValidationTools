<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Test\Unit\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\ExtensionValidationTools\Console\Command\RunUnitTestCommand;

class RunUnitTestCommandTest extends AbstractToolCommandTestCase
{
    public function testSucceedsWithoutUnitTests(): void
    {
        $this->shell->expects($this->never())->method('execute');

        $commandTester = $this->getCommandTester();
        $this->assertSame(Command::SUCCESS, $commandTester->execute(['module' => 'Yireo_Example']));
        $this->assertStringContainsString('No unit tests found', $commandTester->getDisplay());
    }

    public function testRunsPhpUnitWithEscapedArguments(): void
    {
        mkdir($this->modulePath . '/Test/Unit', 0777, true);
        $this->recordShellCalls();

        $this->assertSame(Command::SUCCESS, $this->getCommandTester()->execute(['module' => 'Yireo_Example']));

        [$command, $arguments] = $this->shellCalls[0];
        $this->assertSame('cd %s && %s %s --colors=always %s', $command);
        $this->assertSame(
            [$this->root, PHP_BINARY, $this->root . '/vendor/bin/phpunit', realpath($this->modulePath) . '/Test/Unit/'],
            $arguments
        );
    }

    public function testRejectsInvalidModuleName(): void
    {
        $this->shell->expects($this->never())->method('execute');

        $this->assertSame(Command::FAILURE, $this->getCommandTester()->execute(['module' => 'Yireo_Example;id']));
    }

    private function getCommandTester(): CommandTester
    {
        return new CommandTester(new RunUnitTestCommand($this->getModuleResolver(), $this->getToolRunner()));
    }
}
