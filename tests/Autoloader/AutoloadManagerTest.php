<?php

namespace Autoloader;

use PHPUnit\Framework\Attributes\CoversClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Rikudou\SourceGenerators\Autoloader\AutoloadManager;
use PHPUnit\Framework\TestCase;
use Rikudou\SourceGenerators\Autoloader\TargetClassMapManager;

#[CoversClass(AutoloadManager::class)]
final class AutoloadManagerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid();
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    unlink($file->getRealPath());
                } else {
                    rmdir($file->getRealPath());
                }
            }

            rmdir($this->directory);
        }
    }

    public function testRegisterAutoloader()
    {
        $called = false;
        $instance = new AutoloadManager(
            $this->directory,
            function (string $path) use (&$called) {
                self::assertStringStartsWith($this->directory, $path);
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
            $this->directory,
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
