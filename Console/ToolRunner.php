<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Console;

use InvalidArgumentException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Shell;

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
     */
    public function run(string $command, array $arguments): string
    {
        array_unshift($arguments, $this->directoryList->getRoot());
        return $this->shell->execute('cd %s && ' . $command, $arguments);
    }
}
