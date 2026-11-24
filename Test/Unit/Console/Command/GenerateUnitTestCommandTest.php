<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Test\Unit\Console\Command;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Filesystem\File\WriteFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\ExtensionValidationTools\Console\Command\GenerateUnitTestCommand;
use Yireo\ExtensionValidationTools\Console\ModuleResolver;

class GenerateUnitTestCommandTest extends TestCase
{
    private const FIXTURE_CLASS = 'Yireo\\EvtFixture\\Model\\Foo';

    private static string $modulePath = '';

    public static function setUpBeforeClass(): void
    {
        self::$modulePath = (string)realpath(sys_get_temp_dir()) . '/yireo-evt-fixture-' . uniqid();
        mkdir(self::$modulePath . '/Model', 0777, true);
        file_put_contents(
            self::$modulePath . '/Model/Foo.php',
            "<?php\nnamespace Yireo\\EvtFixture\\Model;\nclass Foo {\n"
            . "public function __construct() {}\npublic function bar() {}\nprivate function secret() {}\n}\n"
        );
        require_once self::$modulePath . '/Model/Foo.php';
    }

    public static function tearDownAfterClass(): void
    {
        (new File())->deleteDirectory(self::$modulePath);
    }

    public function testGeneratesUnitTestOnlyOnce(): void
    {
        $testFile = self::$modulePath . '/Test/Unit/Model/FooTest.php';

        $commandTester = $this->getCommandTester(self::$modulePath);
        $this->assertSame(Command::SUCCESS, $commandTester->execute(['--module' => 'Yireo_EvtFixture', '--class' => self::FIXTURE_CLASS]));
        $this->assertStringContainsString('Unit test generated', $commandTester->getDisplay());

        $code = (string)file_get_contents($testFile);
        $this->assertStringContainsString('namespace Yireo\\EvtFixture\\Test\\Unit\\Model;', $code);
        $this->assertStringContainsString('function testBar()', $code);
        $this->assertStringNotContainsString('testSecret', $code);
        $this->assertStringNotContainsString('test__construct', $code);

        $this->assertSame(Command::SUCCESS, $commandTester->execute(['--module' => 'Yireo_EvtFixture', '--class' => self::FIXTURE_CLASS]));
        $this->assertStringContainsString('Unit test already exists', $commandTester->getDisplay());
        $this->assertSame($code, file_get_contents($testFile));
    }

    #[DataProvider('getInvalidInput')]
    public function testRejectsInvalidInput(string $module, string $class, string $message): void
    {
        $commandTester = $this->getCommandTester(self::$modulePath);
        $this->assertSame(Command::FAILURE, $commandTester->execute(['--module' => $module, '--class' => $class]));
        $this->assertStringContainsString($message, $commandTester->getDisplay());
    }

    public static function getInvalidInput(): array
    {
        return [
            'unknown module' => ['Yireo_Unknown', self::FIXTURE_CLASS, 'is not found'],
            'invalid module' => ['../Yireo', self::FIXTURE_CLASS, 'Invalid module name'],
            'class of other module' => ['Yireo_EvtFixture', 'Magento\\Framework\\Shell', 'does not belong'],
            'unknown class' => ['Yireo_EvtFixture', 'Yireo\\EvtFixture\\Model\\Nope', 'does not exist'],
            'invalid class' => ['Yireo_EvtFixture', 'Yireo\\EvtFixture\\..\\Foo', 'Invalid class name'],
        ];
    }

    public function testRejectsClassOutsideModuleFolder(): void
    {
        $otherPath = self::$modulePath . '-other';
        mkdir($otherPath);

        $commandTester = $this->getCommandTester($otherPath);
        $this->assertSame(Command::FAILURE, $commandTester->execute(['--module' => 'Yireo_EvtFixture', '--class' => self::FIXTURE_CLASS]));
        $this->assertStringContainsString('is not defined in the module folder', $commandTester->getDisplay());
        rmdir($otherPath);
    }

    private function getCommandTester(string $modulePath): CommandTester
    {
        $componentRegistrar = $this->createStub(ComponentRegistrar::class);
        $componentRegistrar->method('getPath')->willReturnCallback(
            fn ($type, $name) => $name === 'Yireo_EvtFixture' ? $modulePath : null
        );

        $command = new GenerateUnitTestCommand(
            new ModuleResolver($componentRegistrar),
            new WriteFactory(new DriverPool()),
            new File()
        );

        return new CommandTester($command);
    }
}
