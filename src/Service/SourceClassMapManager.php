<?php

namespace Rikudou\SourceGenerators\Service;

use DirectoryIterator;
use Rikudou\SourceGenerators\Extractor\Psr4Rule;
use SplFileInfo;

/**
 * @internal
 */
final readonly class SourceClassMapManager
{
    public array $classMap;

    /**
     * @param array<Psr4Rule> $psr4Rules
     */
    public function __construct(
        array $psr4Rules,
    ) {
        $classMap = [];
        foreach ($psr4Rules as $psr4Rule) {
            if (!is_dir($psr4Rule->directory)) {
                continue;
            }
            $this->parseDirectory($classMap, $psr4Rule->directory, trim($psr4Rule->namespace, '\\'));
        }
        $this->classMap = $classMap;
    }

    private function parseDirectory(array &$classMap, string $directory, string $namespace): void
    {
        $iterator = new DirectoryIterator($directory);
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $this->parseFile($classMap, $item, $namespace);
            } else {
                if ($item->getFilename() === "." || $item->getFilename() === "..") {
                    continue;
                }
                $this->parseDirectory($classMap, $item->getRealPath(), $namespace . "\\" . $item->getBasename());
            }
        }
    }

    private function parseFile(array &$classMap, SplFileInfo $file, string $namespace): void
    {
        if ($file->getExtension() !== 'php') {
            return;
        }

        $className = $namespace . '\\' . $file->getBasename('.php');
        if (!class_exists($className)) {
            return;
        }

        $classMap[$file->getRealPath()] = $namespace . '\\' . $file->getBasename('.php');
    }
}
