<?php

namespace Autoloader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Rikudou\SourceGenerators\Autoloader\AutoloadManager;
use Rikudou\SourceGenerators\Autoloader\TargetClassMapManager;
use Rikudou\Tests\SourceGenerators\Trait\TemporaryDirectoryTrait;

#[CoversClass(AutoloadManager::class)]
final class AutoloadManagerTest extends TestCase
{
    use TemporaryDirectoryTrait;

    public function testRegisterAutoloader()
    {
        $called = false;
        $instance = new AutoloadManager(
            $this->temporaryDirectory,
            function (string $path) use (&$called) {
                self::assertStringStartsWith($this->temporaryDirectory, $path);
                $called = true;
            },
            new TargetClassMapManager(),
        );

        $instance->registerAutoloader();
        $this->assertTrue($called);
    }

    public function testDumpCustomAutoloader()
    {
        $classMapManager = new TargetClassMapManager();
        $filePath = '';

        $instance = new AutoloadManager(
            $this->temporaryDirectory,
            function (string $file) use (&$filePath) {
                $filePath = $file;
            },
            $classMapManager,
        );

        $instance->registerAutoloader();
        self::assertNotEmpty($filePath);

        $instance->dumpCustomAutoloader();
        self::assertFileExists($filePath);

        require $filePath;
        $fns = spl_autoload_functions();
        spl_autoload_unregister($fns[0]);

        assert(isset($rikudouSourceGeneratorsClassMap));
        self::assertIsArray($rikudouSourceGeneratorsClassMap);
        self::assertCount(0, $rikudouSourceGeneratorsClassMap);

        $classMapManager->add('App\\SomeTestClass', __DIR__ . '/App/SomeTestClass.php');
        $instance->dumpCustomAutoloader();

        require $filePath;
        $fns = spl_autoload_functions();
        spl_autoload_unregister($fns[0]);

        self::assertCount(1, $rikudouSourceGeneratorsClassMap);
        self::assertArrayHasKey('App\\SomeTestClass', $rikudouSourceGeneratorsClassMap);
        self::assertSame(__DIR__ . '/App/SomeTestClass.php' ,$rikudouSourceGeneratorsClassMap['App\\SomeTestClass']);
    }
}
