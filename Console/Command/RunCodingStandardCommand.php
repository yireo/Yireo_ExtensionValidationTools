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
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Yireo\ExtensionValidationTools\Console\ModuleResolver;
use Yireo\ExtensionValidationTools\Console\OutputSanitizer;
use Yireo\ExtensionValidationTools\Console\ToolRunner;

class RunCodingStandardCommand extends Command
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
        $this->setName('yireo_extension_validate:phpcs');
        $this->setDescription('Run PHPCS for a specific Magento module');
        $this->addArgument('module', InputArgument::REQUIRED, 'Module name');
        $this->addArgument('severity', InputArgument::OPTIONAL, 'PHPCS severity (0-10, default 7)', '7');
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
            $severity = $this->getSeverity((string)$input->getArgument('severity'));
            $phpcs = $this->toolRunner->getBinary('phpcs');
        } catch (InvalidArgumentException $invalidArgumentException) {
            $output->writeln('<error>' . OutputSanitizer::sanitize($invalidArgumentException->getMessage()) . '</error>');
            return Command::FAILURE;
        }

        try {
            if (!$this->checkIfMagento2StandardIsInstalled($phpcs)) {
                $this->toolRunner->run(
                    '%s --config-set installed_paths %s',
                    [$phpcs, $this->toolRunner->getRootPath('vendor/magento/magento-coding-standard/')]
                );
            }
        } catch (LocalizedException $localizedException) {
            $output->writeln('<error>' . OutputSanitizer::sanitize($localizedException->getMessage()) . '</error>');
            return Command::FAILURE;
        }

        return $this->toolRunner->execute(
            '%s --standard=Magento2 --colors --severity=%s %s',
            [$phpcs, $severity, $modulePath],
            $output
        );
    }

    private function getSeverity(string $severity): string
    {
        if (!preg_match('/^(10|[0-9])$/', $severity)) {
            throw new InvalidArgumentException('Severity should be an integer between 0 and 10');
        }

        return $severity;
    }

    /**
     * @param string $phpcs
     * @return bool
     * @throws LocalizedException
     */
    private function checkIfMagento2StandardIsInstalled(string $phpcs): bool
    {
        $output = $this->toolRunner->run('%s -i', [$phpcs]);
        return (bool)stristr($output, 'Magento2');
    }
}
