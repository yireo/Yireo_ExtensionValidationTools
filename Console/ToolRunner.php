<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Console;

use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Shell;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class ToolRunner
{
    private Shell $shell;
    private DirectoryList $directoryList;

    public function __construct(
        Shell $shell,
        DirectoryList $directoryList
    ) {
        $this->shell = $shell;
        $this->directoryList = $directoryList;
    }

    public function getRootPath(string $relativePath): string
    {
        return rtrim($this->directoryList->getRoot(), '/') . '/' . $relativePath;
    }

    public function getBinary(string $name): string
    {
        $binary = $this->getRootPath('vendor/bin/' . $name);
        if (!is_file($binary)) {
            throw new InvalidArgumentException('Binary "vendor/bin/' . $name . '" not found in Magento root');
        }

        return $binary;
    }

    /**
     * Run a command from within the Magento root, so that no configuration of the current directory is picked up
     *
     * @param string $command Command template with %s placeholders
     * @param string[] $arguments Values that are escaped with escapeshellarg()
     * @return string
     * @throws LocalizedException
     */
    public function run(string $command, array $arguments): string
    {
        array_unshift($arguments, $this->directoryList->getRoot());
        return $this->shell->execute('cd %s && ' . $command, $arguments);
    }

    /**
     * Run a command, print its output and return a console exit code
     *
     * @param string $command Command template with %s placeholders
     * @param string[] $arguments Values that are escaped with escapeshellarg()
     * @param OutputInterface $output
     * @return int
     */
    public function execute(string $command, array $arguments, OutputInterface $output): int
    {
        try {
            $output->writeln($this->run($command, $arguments), OutputInterface::OUTPUT_RAW);
        } catch (LocalizedException $localizedException) {
            $previous = $localizedException->getPrevious();
            $toolOutput = $previous ? $previous->getMessage() : $localizedException->getMessage();
            $output->writeln($toolOutput, OutputInterface::OUTPUT_RAW);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
