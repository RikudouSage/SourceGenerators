<?php

namespace Rikudou\SourceGenerators\Autoloader;

/**
 * @internal
 */
final class TargetClassMapManager
{
    /**
     * @var array<class-string, string>
     */
    private array $map = [];

    /**
     * @param class-string $className
     * @param string $path
     */
    public function add(string $className, string $path): void
    {
        $this->map[$className] = $path;
    }

    /**
     * @return array<class-string, string>
     */
    public function getMap(): array
    {
        return $this->map;
    }
}
