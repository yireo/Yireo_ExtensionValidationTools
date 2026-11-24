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
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Filesystem\File\WriteFactory;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Input\InputOption;
use Yireo\ExtensionValidationTools\Console\ModuleResolver;
use Yireo\ExtensionValidationTools\Console\OutputSanitizer;

class GenerateUnitTestCommand extends Command
{
    private const TEST_CASE_CLASS = 'PHPUnit\\Framework\\TestCase';

    private ModuleResolver $moduleResolver;
    private WriteFactory $writeFactory;
    private FileDriver $fileDriver;

    public function __construct(
        ModuleResolver $moduleResolver,
        WriteFactory $writeFactory,
        FileDriver $fileDriver,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->moduleResolver = $moduleResolver;
        $this->writeFactory = $writeFactory;
        $this->fileDriver = $fileDriver;
    }

    protected function configure()
    {
        $this->setName('yireo_extension_validate:generate-unit-test');
        $this->setDescription('Generate a PHPUnit unit test');

        $this->addOption(
            'module',
            null,
            InputOption::VALUE_REQUIRED,
            'Module name'
        );

        $this->addOption(
            'class',
            null,
            InputOption::VALUE_REQUIRED,
            'Full qualified class name'
        );
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output): int
    {
        $moduleName = (string)$input->getOption('module');
        $className = ltrim((string)$input->getOption('class'), '\\');

        try {
            $modulePath = $this->moduleResolver->getPath($moduleName);
            $reflectionClass = $this->getReflectionClass(
                $className,
                $this->moduleResolver->getNamespacePrefix($moduleName),
                $modulePath
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            $output->writeln('<error>' . OutputSanitizer::sanitize($invalidArgumentException->getMessage()) . '</error>');
            return Command::FAILURE;
        }

        $phpUnitTestFile = $this->getPhpUnitTestFile($modulePath, $reflectionClass);
        if (file_exists($phpUnitTestFile)) {
            $output->writeln('Unit test already exists: ' . OutputSanitizer::sanitize($phpUnitTestFile));
            return Command::SUCCESS;
        }

        try {
            $this->fileDriver->createDirectory(dirname($phpUnitTestFile), 0775);
            $fileWrite = $this->writeFactory->create($phpUnitTestFile, DriverPool::FILE, 'x');
            $fileWrite->write($this->getPhpUnitTestCode($reflectionClass));
            $fileWrite->close();
        } catch (FileSystemException $fileSystemException) {
            $output->writeln('<error>' . OutputSanitizer::sanitize($fileSystemException->getMessage()) . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('Unit test generated: ' . OutputSanitizer::sanitize($phpUnitTestFile));
        return Command::SUCCESS;
    }

    private function getReflectionClass(string $className, string $namespacePrefix, string $modulePath): ReflectionClass
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $className)) {
            throw new InvalidArgumentException('Invalid class name "' . $className . '"');
        }

        if (stripos($className, $namespacePrefix) !== 0) {
            throw new InvalidArgumentException(
                'Class "' . $className . '" does not belong to namespace "' . $namespacePrefix . '"'
            );
        }

        if (!class_exists($className)) {
            throw new InvalidArgumentException('Class "' . $className . '" does not exist');
        }

        $reflectionClass = new ReflectionClass($className);
        $fileName = (string)$reflectionClass->getFileName();
        $realFileName = $fileName !== '' ? realpath($fileName) : false;
        if (strpos($reflectionClass->getName(), $namespacePrefix) !== 0
            || $realFileName === false
            || strpos($realFileName, $modulePath . '/') !== 0) {
            throw new InvalidArgumentException(
                'Class "' . $className . '" is not defined in the module folder "' . $modulePath . '"'
            );
        }

        return $reflectionClass;
    }

    private function getPhpUnitTestFile(string $modulePath, ReflectionClass $reflectionClass): string
    {
        $parts = explode('\\', $reflectionClass->getNamespaceName());
        array_shift($parts);
        array_shift($parts);

        return $modulePath . '/Test/Unit/' . implode('/', $parts)
            . '/' . $reflectionClass->getShortName() . 'Test.php';
    }

    private function getPhpUnitTestCode(ReflectionClass $reflectionClass): string
    {
        $targetClass = $reflectionClass->getName();
        $targetObjectName = $reflectionClass->getShortName();
        $targetObjectVar = '$' . lcfirst($targetObjectName);

        $class = new ClassType($targetObjectName . 'Test');
        $class->setExtends(self::TEST_CASE_CLASS);

        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $targetMethod) {
            if ($targetMethod->isConstructor() || $targetMethod->getDeclaringClass()->getName() !== $targetClass) {
                continue;
            }

            $bodyLines = [];
            $bodyLines[] = $targetObjectVar . ' = new ' . $targetObjectName . '();';
            $bodyLines[] = $targetObjectVar . '->' . $targetMethod->getName() . '();';

            $class->addMethod('test' . ucfirst($targetMethod->getName()))
                ->addComment('Test for ' . $targetClass . '::' . $targetMethod->getName())
                ->setBody(implode("\n", $bodyLines));
        }

        $namespace = $this->getTestNameSpace($reflectionClass);
        $namespace->addUse(self::TEST_CASE_CLASS);
        $namespace->addUse($targetClass);
        $namespace->add($class);

        return "<?php declare(strict_types=1);\n\n" . $namespace;
    }

    private function getTestNameSpace(ReflectionClass $reflectionClass): PhpNamespace
    {
        $parts = explode('\\', $reflectionClass->getNamespaceName());
        $vendor = array_shift($parts);
        $module = array_shift($parts);
        $namespaceName = implode('\\', array_merge([$vendor, $module, 'Test', 'Unit'], $parts));
        return new PhpNamespace($namespaceName);
    }
}
