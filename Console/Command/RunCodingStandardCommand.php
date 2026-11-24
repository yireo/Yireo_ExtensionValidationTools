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
use Magento\Framework\Component\ComponentRegistrar;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Yireo\ExtensionValidationTools\Console\ToolRunner;

class RunCodingStandardCommand extends Command
{
    private ComponentRegistrar $componentRegistrar;
    private ToolRunner $toolRunner;

    public function __construct(
        ComponentRegistrar $componentRegistrar,
        ToolRunner $toolRunner,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->componentRegistrar = $componentRegistrar;
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
            $severity = $this->getSeverity((string)$input->getArgument('severity'));
        } catch (InvalidArgumentException $invalidArgumentException) {
            $output->writeln('<error>' . $invalidArgumentException->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $modulePath = (string)$this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
        $phpcs = 'vendor/bin/phpcs';

        if (!$this->checkIfMagento2StandardIsInstalled($phpcs)) {
            $this->toolRunner->run('%s --config-set installed_paths vendor/magento/magento-coding-standard/', [$phpcs]);
        }

        $this->toolRunner->run(
            '%s --standard=Magento2 --colors --severity=%s %s',
            [$phpcs, $severity, $modulePath]
        );

        return 0;
    }

    private function getSeverity(string $severity): string
    {
        if (!preg_match('/^(10|[0-9])$/', $severity)) {
            throw new InvalidArgumentException('Severity should be an integer between 0 and 10');
        }

        return $severity;
    }

    private function checkIfMagento2StandardIsInstalled(string $phpcs): bool
    {
        $output = $this->toolRunner->run('%s -i', [$phpcs]);
        return (bool)stristr($output, 'Magento2');
    }
}
