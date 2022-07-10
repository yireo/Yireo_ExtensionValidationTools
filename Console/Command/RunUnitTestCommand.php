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

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Shell;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;

class RunUnitTestCommand extends Command
{
    /**
     * @var ComponentRegistrar
     */
    private $componentRegistrar;
    
    /**
     * @var Shell
     */
    private $shell;
    
    /**
     * @param ComponentRegistrar $componentRegistrar
     * @param string|null $name
     */
    public function __construct(
        ComponentRegistrar $componentRegistrar,
        Shell $shell,
        string $name = null
    ) {
        parent::__construct($name);
        $this->componentRegistrar = $componentRegistrar;
        $this->shell = $shell;
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
     * @return int|void
     */
    protected function execute(Input $input, Output $output)
    {
        $moduleName = $input->getArgument('module');
        $modulePath = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);

        if (is_dir($modulePath . '/Test/Unit')) {
            $this->shell->execute($_SERVER['_'] . ' ./vendor/bin/phpunit --colors=always ' . $modulePath . '/Test/Unit/');
            return 1;
        }

        return 0;
    }
}
