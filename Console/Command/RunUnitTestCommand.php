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

class RunUnitTestCommand extends Command
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
        $this->setName('yireo_extension_validate:unit');
        $this->setDescription('Run PHPUnit unit tests for a specific Magento module');
        $this->addArgument('module', InputArgument::REQUIRED, 'Module name');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output): int
    {
        $moduleName = (string)$input->getArgument('module');
        $modulePath = (string)$this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);

        if (is_dir($modulePath . '/Test/Unit')) {
            try {
                $phpunit = $this->toolRunner->getBinary('phpunit');
            } catch (InvalidArgumentException $invalidArgumentException) {
                $output->writeln('<error>' . $invalidArgumentException->getMessage() . '</error>');
                return Command::FAILURE;
            }

            $this->toolRunner->run(
                '%s %s --colors=always %s',
                [PHP_BINARY, $phpunit, $modulePath . '/Test/Unit/']
            );
            return 1;
        }

        return 0;
    }
}
