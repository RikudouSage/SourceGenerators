<?php

namespace Rikudou\SourceGenerators\Processor;

use ReflectionClass;
use Rikudou\SourceGenerators\Autoloader\TargetClassMapManager;
use Rikudou\SourceGenerators\Exception\IOException;

final readonly class EmptyClassProcessor
{
    public function __construct(
        private string $targetDirectory,
        private TargetClassMapManager $classMapManager,
    ) {
    }

    /**
     * @param array<class-string> $classes
     */
    public function process(array $classes): void
    {
        foreach ($classes as $class) {
            $classReflection = new ReflectionClass($class);
            $fileName = $classReflection->getShortName() . '.php';
            $filePath = "{$this->targetDirectory}/{$fileName}";

            if (!is_file($filePath)) {
                copy($classReflection->getFileName(), $filePath) ?: throw new IOException("Failed copying from '{$classReflection->getFileName()}' to '{$filePath}'");
            }

            $this->classMapManager->add($class, $filePath);
        }
    }
}
