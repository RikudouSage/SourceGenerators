<?php

namespace Rikudou\SourceGenerators\Service;

use DirectoryIterator;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Rikudou\SourceGenerators\Extractor\Psr4Rule;
use SplFileInfo;
use Throwable;

/**
 * @internal
 */
final class SourceClassMapManager
{
    private ?array $classMap;
    private readonly Parser $parser;

    /**
     * @param array<Psr4Rule> $psr4Rules
     */
    public function __construct(
        private readonly array $psr4Rules,
    ) {
        $this->parser = (new ParserFactory())->createForHostVersion();
    }

    public function getClassMap(): array
    {
        if ($this->classMap === null) {
            $classMap = [];
            foreach ($this->psr4Rules as $psr4Rule) {
                if (!is_dir($psr4Rule->directory)) {
                    continue;
                }
                $this->parseDirectory($classMap, $psr4Rule->directory, trim($psr4Rule->namespace, '\\'));
            }
            $this->classMap = $classMap;
        }

        return $this->classMap;
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

        $ast = $this->parser->parse(file_get_contents($file->getRealPath()));

        $traverser = new NodeTraverser(
            new NameResolver(),
            new class ($className, $classMap, $file) extends NodeVisitorAbstract
            {
                public function __construct(
                    private readonly string $expectedClassName,
                    private array &$classMap,
                    private readonly SplFileInfo $file,
                ) {
                }

                public function enterNode(Node $node): void
                {
                    if (!$node instanceof Node\Stmt\Class_) {
                        return;
                    }

                    try {
                        if (
                            (string) $node->namespacedName === $this->expectedClassName
                            && class_exists($this->expectedClassName)
                        ) {
                            $this->classMap[$this->file->getRealPath()] = $this->expectedClassName;
                        }
                    } catch (Throwable) {
                        // ignore
                    }
                }
            }
        );
        $traverser->traverse($ast);
    }
}
