<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Test\Unit\Console;

use InvalidArgumentException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Shell;
use Magento\Framework\Shell\CommandRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Yireo\ExtensionValidationTools\Console\ToolRunner;

class ToolRunnerTest extends TestCase
{
    public function testRunEscapesArgumentsAndRunsFromRoot(): void
    {
        $root = (string)realpath(sys_get_temp_dir());
        $toolRunner = new ToolRunner(new Shell(new CommandRenderer()), $this->getDirectoryList($root));

        $this->assertSame('$(echo pwned);id', $toolRunner->run('printf %s', ['$(echo pwned);id']));
        $this->assertSame($root, $toolRunner->run('pwd', []));
    }

    public function testExecuteReturnsFailureAndPrintsToolOutput(): void
    {
        $toolRunner = new ToolRunner(new Shell(new CommandRenderer()), $this->getDirectoryList(sys_get_temp_dir()));
        $output = new BufferedOutput();

        $this->assertSame(Command::FAILURE, $toolRunner->execute('sh -c %s', ['echo "<error>oops"; exit 3'], $output));
        $this->assertStringContainsString('<error>oops', $output->fetch());
    }

    public function testExecuteReturnsSuccess(): void
    {
        $toolRunner = new ToolRunner(new Shell(new CommandRenderer()), $this->getDirectoryList(sys_get_temp_dir()));
        $output = new BufferedOutput();

        $this->assertSame(Command::SUCCESS, $toolRunner->execute('printf %s', ['done'], $output));
        $this->assertStringContainsString('done', $output->fetch());
    }

    public function testGetBinaryThrowsWhenMissing(): void
    {
        $toolRunner = new ToolRunner($this->createStub(Shell::class), $this->getDirectoryList('/does/not/exist'));

        $this->expectException(InvalidArgumentException::class);
        $toolRunner->getBinary('phpcs');
    }

    private function getDirectoryList(string $root): DirectoryList
    {
        $directoryList = $this->createStub(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn($root);
        return $directoryList;
    }
}
