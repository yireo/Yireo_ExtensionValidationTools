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
use UnexpectedValueException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Yireo\ExtensionChecker\Composer\ComposerFileFactory;
use Yireo\ExtensionChecker\Composer\ComposerProvider;
use Yireo\ExtensionValidationTools\Console\OutputSanitizer;

class VersionMatchCommand extends Command
{
    private ComposerFileFactory $composerFileFactory;
    private ComposerProvider $composerProvider;

    /**
     * VersionMatchCommand constructor.
     * @param ComposerFileFactory $composerFileFactory
     * @param ComposerProvider $composerProvider
     * @param string|null $name
     */
    public function __construct(
        ComposerFileFactory $composerFileFactory,
        ComposerProvider $composerProvider,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->composerFileFactory = $composerFileFactory;
        $this->composerProvider = $composerProvider;
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
            InputArgument::REQUIRED,
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
            $composerRequirements = $this->composerFileFactory->create($composerFile)->get('require');
        } catch (Exception $e) {
            $output->writeln('ERROR: ' . OutputSanitizer::sanitize($e->getMessage()));
            return Command::FAILURE;
        }

        if (!is_array($composerRequirements)) {
            $output->writeln('ERROR: The "require" section is not a list of packages');
            return Command::FAILURE;
        }

        $installedPackages = $this->composerProvider->getComposerPackages();
        $hasErrors = false;
        foreach ($composerRequirements as $composerRequirement => $composerRequiredVersion) {
            $error = $this->checkRequirement($composerRequirement, $composerRequiredVersion, $installedPackages);
            if ($error !== '') {
                $output->writeln('ERROR: ' . $error);
                $hasErrors = true;
            }
        }

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param mixed $composerRequirement
     * @param mixed $composerRequiredVersion
     * @param array $installedPackages
     * @return string Sanitized error message, or an empty string when the requirement is met
     */
    private function checkRequirement($composerRequirement, $composerRequiredVersion, array $installedPackages): string
    {
        if (!is_string($composerRequirement) || !is_string($composerRequiredVersion)) {
            return 'Invalid requirement "' . OutputSanitizer::sanitize($composerRequirement) . '"';
        }

        if (preg_match('/^ext-/', $composerRequirement) || $composerRequirement === 'php') {
            return '';
        }

        $matchedInProject = false;
        foreach ($installedPackages as $installedPackage) {
            if ($installedPackage['name'] === $composerRequirement) {
                $matchedInProject = $installedPackage;
            }
        }

        if ($matchedInProject === false) {
            return '"' . OutputSanitizer::sanitize($composerRequirement) . '" is not found in project';
        }

        $installedVersion = (string)$matchedInProject['version'];
        if ($installedVersion === $composerRequiredVersion) {
            return '';
        }

        try {
            $composerConstraint = (new VersionParser)->parseConstraints($composerRequiredVersion);
            $projectConstraint = (new VersionParser)->parseConstraints($installedVersion);
        } catch (UnexpectedValueException $unexpectedValueException) {
            return 'Invalid version constraint for "' . OutputSanitizer::sanitize($composerRequirement) . '": '
                . OutputSanitizer::sanitize($unexpectedValueException->getMessage());
        }

        if ($composerConstraint->matches($projectConstraint)) {
            return '';
        }

        return sprintf(
            '"%s:%s" does not match required version "%s"',
            OutputSanitizer::sanitize($composerRequirement),
            OutputSanitizer::sanitize($installedVersion),
            OutputSanitizer::sanitize($composerRequiredVersion)
        );
    }
}
