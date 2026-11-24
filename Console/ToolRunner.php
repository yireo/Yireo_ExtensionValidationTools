<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Console;

use Magento\Framework\Shell;

class ToolRunner
{
    private Shell $shell;

    public function __construct(
        Shell $shell
    ) {
        $this->shell = $shell;
    }

    /**
     * @param string $command Command template with %s placeholders
     * @param string[] $arguments Values that are escaped with escapeshellarg()
     * @return string
     */
    public function run(string $command, array $arguments): string
    {
        return $this->shell->execute($command, $arguments);
    }
}
