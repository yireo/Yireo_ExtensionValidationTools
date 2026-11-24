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

class GenerateUnitTestCommand extends Command
{
    private const TEST_CASE_CLASS = 'PHPUnit\\Framework\\TestCase';

    private ComponentRegistrar $componentRegistrar;
    private WriteFactory $writeFactory;

    public function __construct(
        ComponentRegistrar $componentRegistrar,
        WriteFactory $writeFactory,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->componentRegistrar = $componentRegistrar;
        $this->writeFactory = $writeFactory;
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
        $modulePath = (string)$this->componentRegistrar->getPath('module', $moduleName);

        $className = ltrim((string)$input->getOption('class'), '\\');
        if (!class_exists($className)) {
            $output->writeln('<error>Class "' . $className . '" does not exist</error>');
            return Command::FAILURE;
        }

        $reflectionClass = new ReflectionClass($className);
        $phpUnitTestFile = $this->getPhpUnitTestFile($modulePath, $reflectionClass);
        if (file_exists($phpUnitTestFile)) {
            $output->writeln('Unit test already exists');
            return 0;
        }

        $phpUnitTestCode = $this->getPhpUnitTestCode($reflectionClass);
        $fileWrite = $this->writeFactory->create($phpUnitTestFile, DriverPool::FILE, 'w');
        $fileWrite->write($phpUnitTestCode);

        $output->writeln('Unit test generated: ' . $phpUnitTestFile);
        return 0;
    }

    private function getPhpUnitTestFile(string $modulePath, ReflectionClass $reflectionClass): string
    {
        $parts = explode('\\', $reflectionClass->getNamespaceName());
        array_shift($parts);
        array_shift($parts);

        return $modulePath . '/Test/Unit/' . implode('//', $parts)
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
        $namespaceName = implode('\\', array_merge([$vendor, $module, 'Test'], $parts));
        return new PhpNamespace($namespaceName);
    }
}
