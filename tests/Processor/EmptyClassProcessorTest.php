<?php

namespace Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use Rikudou\SourceGenerators\Autoloader\TargetClassMapManager;
use Rikudou\SourceGenerators\Processor\EmptyClassProcessor;
use PHPUnit\Framework\TestCase;
use Rikudou\Tests\SourceGenerators\Data\Classes\TestEmptyPartialClass;
use Rikudou\Tests\SourceGenerators\Trait\TemporaryDirectoryTrait;

#[CoversClass(EmptyClassProcessor::class)]
final class EmptyClassProcessorTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private TargetClassMapManager $classMapManager;
    private EmptyClassProcessor $instance;

    protected function setUp(): void
    {
        $this->createTemporaryDirectory();

        $this->classMapManager = new TargetClassMapManager();
        $this->instance = new EmptyClassProcessor(
            targetDirectory: $this->temporaryDirectory,
            classMapManager: $this->classMapManager,
        );
    }

    public function testProcess()
    {
        $this->instance->process([
            TestEmptyPartialClass::class,
        ]);
        self::assertFileExists("{$this->temporaryDirectory}/TestEmptyPartialClass.php");
        self::assertSame(
            file_get_contents(__DIR__ . '/../Data/Classes/TestEmptyPartialClass.php'),
            file_get_contents("{$this->temporaryDirectory}/TestEmptyPartialClass.php"),
        );

        $map = $this->classMapManager->getMap();
        self::assertArrayHasKey(TestEmptyPartialClass::class, $map);
        self::assertSame("{$this->temporaryDirectory}/TestEmptyPartialClass.php", $map[TestEmptyPartialClass::class]);
    }
}
