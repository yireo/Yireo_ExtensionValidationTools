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
    private ToolRunner $toolRunner;

    public function __construct(
        ModuleResolver $moduleResolver,
        ToolRunner $toolRunner,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->moduleResolver = $moduleResolver;
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

        return $this->toolRunner->execute(
            '%s analyse --configuration=%s --level=%s --no-progress %s',
            [$phpstan, $this->getConfigurationFile(), $level, $modulePath],
            $output
        );
    }

    private function getLevel(string $level): string
    {
        if (!preg_match('/^(10|[0-9]|max)$/', $level)) {
            throw new InvalidArgumentException('Level should be an integer between 0 and 10, or "max"');
        }

        return $level;
    }

    private function getConfigurationFile(): string
    {
        $configFile = $this->toolRunner->getRootPath('phpstan.neon');
        if (is_file($configFile)) {
            return $configFile;
        }

        return dirname(__DIR__, 2) . '/etc/phpstan/default.neon';
    }
}
