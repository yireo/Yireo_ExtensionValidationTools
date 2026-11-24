<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Test\Unit\Console\Command;

use Magento\Framework\Filesystem\Driver\File;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\ExtensionChecker\Composer\ComposerFile;
use Yireo\ExtensionChecker\Composer\ComposerFileFactory;
use Yireo\ExtensionChecker\Composer\ComposerProvider;
use Yireo\ExtensionValidationTools\Console\Command\VersionMatchCommand;

class VersionMatchCommandTest extends TestCase
{
    private string $folder = '';

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/yireo-evt-composer-' . uniqid();
        mkdir($this->folder);
        file_put_contents($this->folder . '/composer.json', '{}');
        file_put_contents($this->folder . '/env.php', '<?php return [];');
    }

    protected function tearDown(): void
    {
        (new File())->deleteDirectory($this->folder);
    }

    public function testRejectsFileThatIsNotComposerJson(): void
    {
        $composerFileFactory = $this->createMock(ComposerFileFactory::class);
        $composerFileFactory->expects($this->never())->method('create');
        $command = new VersionMatchCommand($composerFileFactory, $this->createStub(ComposerProvider::class));

        $commandTester = new CommandTester($command);
        $this->assertSame(Command::FAILURE, $commandTester->execute(['composer_file' => $this->folder . '/env.php']));
        $this->assertSame(Command::FAILURE, $commandTester->execute(['composer_file' => $this->folder . '/missing/composer.json']));
    }

    public function testSucceedsWhenAllRequirementsMatch(): void
    {
        $commandTester = $this->getCommandTester(['php' => '^8.1', 'ext-json' => '*', 'foo/bar' => '^1.0']);
        $this->assertSame(Command::SUCCESS, $commandTester->execute(['composer_file' => $this->folder . '/composer.json']));
        $this->assertSame('', $commandTester->getDisplay());
    }

    public function testFailsWhenRequirementDoesNotMatch(): void
    {
        $commandTester = $this->getCommandTester(['foo/bar' => '^2.0', 'foo/missing' => '*']);
        $this->assertSame(Command::FAILURE, $commandTester->execute(['composer_file' => $this->folder . '/composer.json']));
        $this->assertStringContainsString('"foo/bar:1.2.3" does not match required version "^2.0"', $commandTester->getDisplay());
        $this->assertStringContainsString('"foo/missing" is not found in project', $commandTester->getDisplay());
    }

    public function testHandlesInvalidConstraint(): void
    {
        $commandTester = $this->getCommandTester(['foo/bar' => 'not a version!!']);
        $this->assertSame(Command::FAILURE, $commandTester->execute(['composer_file' => $this->folder . '/composer.json']));
        $this->assertStringContainsString('Invalid version constraint for "foo/bar"', $commandTester->getDisplay());
    }

    public function testEscapesConsoleMarkup(): void
    {
        $commandTester = $this->getCommandTester(["<error>evil\e[2J" => '*']);
        $this->assertSame(Command::FAILURE, $commandTester->execute(['composer_file' => $this->folder . '/composer.json']));
        $this->assertStringContainsString('"<error>evil[2J" is not found in project', $commandTester->getDisplay());
    }

    private function getCommandTester(array $requirements): CommandTester
    {
        $composerFile = $this->createMock(ComposerFile::class);
        $composerFile->expects($this->once())->method('get')->with('require')->willReturn($requirements);

        $composerFileFactory = $this->createMock(ComposerFileFactory::class);
        $composerFileFactory->expects($this->once())->method('create')->with(realpath($this->folder . '/composer.json'))->willReturn($composerFile);

        $composerProvider = $this->createStub(ComposerProvider::class);
        $composerProvider->method('getComposerPackages')->willReturn([['name' => 'foo/bar', 'version' => '1.2.3']]);

        return new CommandTester(new VersionMatchCommand($composerFileFactory, $composerProvider));
    }
}
