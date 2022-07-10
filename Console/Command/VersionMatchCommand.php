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

use Composer\Semver\VersionParser;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Input\InputOption;
use Yireo\ExtensionChecker\Scan\Composer;

class VersionMatchCommand extends Command
{
    /**
     * @var Composer
     */
    private $composer;

    /**
     * VersionMatchCommand constructor.
     * @param Composer $composer
     * @param string|null $name
     */
    public function __construct(
        Composer $composer,
        string $name = null
    ) {
        parent::__construct($name);
        $this->composer = $composer;
    }

    /**
     * Configure this command
     */
    protected function configure()
    {
        $this->setName('yireo_extension_validate:version_match');
        $this->setDescription('See if given composer.json would be installable in current Magento instance');

        $this->addArgument(
            'composer_file',
            InputOption::VALUE_REQUIRED,
            'Module composer.json file'
        );
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output): int
    {
        $composerFile = (string)$input->getArgument('composer_file');
        try {
            $composerRequirements = $this->composer->getRequirementsFromFile($composerFile);
        } catch (Exception $e) {
            $output->writeln('ERROR: ' . $e->getMessage());
            return 1;
        }

        $installedPackages = $this->composer->getInstalledPackages();
        foreach ($composerRequirements as $composerRequirement => $composerRequiredVersion) {
            if (preg_match('/^ext-/', $composerRequirement) || $composerRequirement === 'php') {
                continue;
            }

            $matchedInProject = false;
            foreach ($installedPackages as $installedPackage) {
                if ($installedPackage['name'] === $composerRequirement) {
                    $matchedInProject = $installedPackage;
                }
            }

            if ($matchedInProject === false) {
                $output->writeln('ERROR: "' . $composerRequirement . '" is not found in project');
                continue;
            }

            $installedVersion = $matchedInProject['version'];
            if ($installedVersion === $composerRequiredVersion) {
                continue;
            }

            $composerConstraint = (new VersionParser)->parseConstraints($composerRequiredVersion);
            $projectConstraint = (new VersionParser)->parseConstraints($installedVersion);
            if ($composerConstraint->matches($projectConstraint)) {
                continue;
            }

            $output->writeln(
                sprintf(
                    'ERROR: "%s:%s" does not match required version "%s"',
                    $composerRequirement,
                    $installedVersion,
                    $composerRequiredVersion
                )
            );
        }
        
        return 1;
    }
}
