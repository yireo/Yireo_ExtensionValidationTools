<?php declare(strict_types=1);
/**
 * Yireo ExtensionValidationTools for Magento
 *
 * @package     Yireo_ExtensionValidationTools
 * @author      Yireo (https://www.yireo.com/)
 * @copyright   Copyright 2022 Yireo (https://www.yireo.com/)
 * @license     Open Source License (OSL v3)
 */

namespace Yireo\ExtensionValidationTools\Console\Command;

use InvalidArgumentException;
use Magento\Framework\Filesystem\DirectoryList;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Yireo\ExtensionValidationTools\Console\ModuleResolver;
use Yireo\ExtensionValidationTools\Console\OutputSanitizer;
use Yireo\ExtensionValidationTools\Console\ToolRunner;

class RunPhpStanCommand extends Command
{
    private ModuleResolver $moduleResolver;
    private DirectoryList $directoryList;
    private ToolRunner $toolRunner;

    public function __construct(
        ModuleResolver $moduleResolver,
        DirectoryList $directoryList,
        ToolRunner $toolRunner,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->moduleResolver = $moduleResolver;
        $this->directoryList = $directoryList;
        $this->toolRunner = $toolRunner;
    }

    protected function configure()
    {
        $this->setName('yireo_extension_validate:phpstan');
        $this->setDescription('Run PHPStan for a specific Magento module');
        $this->addArgument('module', InputArgument::REQUIRED, 'Module name');
        $this->addArgument('level', InputArgument::OPTIONAL, 'PHPStan level (0-10 or max, default 2)', '2');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output): int
    {
        $moduleName = (string)$input->getArgument('module');
        try {
            $modulePath = $this->moduleResolver->getPath($moduleName);
            $level = $this->getLevel((string)$input->getArgument('level'));
            $phpstan = $this->toolRunner->getBinary('phpstan');
        } catch (InvalidArgumentException $invalidArgumentException) {
            $output->writeln('<error>' . OutputSanitizer::sanitize($invalidArgumentException->getMessage()) . '</error>');
            return Command::FAILURE;
        }

        $this->generatePhpStanConfigurationIfMissing();
        $this->toolRunner->run(
            '%s analyse --configuration=%s --level=%s --no-progress %s',
            [$phpstan, $this->directoryList->getRoot() . '/phpstan.neon', $level, $modulePath]
        );

        return 1;
    }

    private function getLevel(string $level): string
    {
        if (!preg_match('/^(10|[0-9]|max)$/', $level)) {
            throw new InvalidArgumentException('Level should be an integer between 0 and 10, or "max"');
        }

        return $level;
    }

    private function generatePhpStanConfigurationIfMissing()
    {
        $configFile = $this->directoryList->getRoot().'/phpstan.neon';
        if (file_exists($configFile)) {
            return;
        }

        file_put_contents($configFile, <<<HEREDOC
parameters:
    excludes_analyse:
        - */Test/*/*

HEREDOC
        );
    }
}
