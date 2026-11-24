<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Test\Unit\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\ExtensionValidationTools\Console\Command\RunPhpStanCommand;

class RunPhpStanCommandTest extends AbstractToolCommandTestCase
{
    public function testRunsPhpStanWithBundledConfiguration(): void
    {
        $this->recordShellCalls();

        $this->assertSame(Command::SUCCESS, $this->getCommandTester()->execute(['module' => 'Yireo_Example', 'level' => 'max']));

        [$command, $arguments] = $this->shellCalls[0];
        $this->assertSame('cd %s && %s analyse --configuration=%s --level=%s --no-progress %s', $command);
        $this->assertSame(
            [
                $this->root,
                $this->root . '/vendor/bin/phpstan',
                dirname(__DIR__, 4) . '/etc/phpstan/default.neon',
                'max',
                realpath($this->modulePath),
            ],
            $arguments
        );
        $this->assertArgumentsAreNotConcatenated($command);
        $this->assertFileDoesNotExist($this->root . '/phpstan.neon');
    }

    public function testUsesConfigurationOfMagentoRoot(): void
    {
        touch($this->root . '/phpstan.neon');
        $this->recordShellCalls();

        $this->getCommandTester()->execute(['module' => 'Yireo_Example']);

        $this->assertSame($this->root . '/phpstan.neon', $this->shellCalls[0][1][2]);
        $this->assertSame('2', $this->shellCalls[0][1][3]);
    }

    #[DataProvider('getInvalidLevels')]
    public function testRejectsInvalidLevel(string $level): void
    {
        $this->shell->expects($this->never())->method('execute');

        $commandTester = $this->getCommandTester();
        $this->assertSame(Command::FAILURE, $commandTester->execute(['module' => 'Yireo_Example', 'level' => $level]));
        $this->assertStringContainsString('Level should be', $commandTester->getDisplay());
    }

    public static function getInvalidLevels(): array
    {
        return [
            'shell injection' => ['2 $(id)'],
            'too high' => ['11'],
            'negative' => ['-1'],
        ];
    }

    public function testReturnsFailureWhenPhpStanFails(): void
    {
        $this->shell->expects($this->once())->method('execute')->willThrowException(
            new LocalizedException(new Phrase('Command returned non-zero exit code'), new \Exception('Found 3 errors', 1))
        );

        $commandTester = $this->getCommandTester();
        $this->assertSame(Command::FAILURE, $commandTester->execute(['module' => 'Yireo_Example']));
        $this->assertStringContainsString('Found 3 errors', $commandTester->getDisplay());
    }

    private function getCommandTester(): CommandTester
    {
        return new CommandTester(new RunPhpStanCommand($this->getModuleResolver(), $this->getToolRunner()));
    }
}
