<?php

namespace Autoloader;

use PHPUnit\Framework\Attributes\CoversClass;
use Rikudou\SourceGenerators\Autoloader\TargetClassMapManager;
use PHPUnit\Framework\TestCase;

#[CoversClass(TargetClassMapManager::class)]
final class TargetClassMapManagerTest extends TestCase
{
    public function testAdd()
    {
        $className = 'Test';
        $path = 'Test.php';

        $instance = new TargetClassMapManager();
        self::assertEmpty($instance->getMap());

        $instance->add($className, $path);
        self::assertNotEmpty($instance->getMap());

        self::assertArrayHasKey($className, $instance->getMap());
        self::assertSame($path, $instance->getMap()[$className]);
    }
}
