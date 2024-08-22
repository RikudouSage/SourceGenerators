<?php

namespace Rikudou\SourceGenerators\Processor;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;
use Rikudou\SourceGenerators\Attribute\PartialClass;
use Rikudou\SourceGenerators\Attribute\PartialMethod;
use Rikudou\SourceGenerators\Attribute\PartialProperty;
use Rikudou\SourceGenerators\Autoloader\TargetClassMapManager;
use Rikudou\SourceGenerators\Exception\IOException;
use Rikudou\SourceGenerators\Exception\UnimplementedPartialClassException;
use Rikudou\SourceGenerators\Exception\UnimplementedPropertyException;
use Rikudou\SourceGenerators\Parser\ParserHelper;

/**
 * @internal
 */
final readonly class PartialAttributeCleanerProcessor
{
    private PrettyPrinter $dumper;
    private Parser $parser;
    private ParserHelper $helper;

    public function __construct(
        private TargetClassMapManager $classMapManager,
    ) {
        $this->dumper = new Standard();
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->helper = new ParserHelper();
    }

    /**
     * @param array<ReflectionClass> $partialClasses
     */
    public function process(array $partialClasses): void
    {
        $this->throwOnUnimplementedClasses($partialClasses);

        foreach ($this->classMapManager->getMap() as $filePath) {
            $ast = $this->parser->parse(file_get_contents($filePath));
            $ast = $this->cleanClassAttribute($ast);

            $this->throwOnUnimplementedProperties($ast);
            $this->throwOnUnimplementedMethods($ast);

            file_put_contents($filePath, $this->dumper->prettyPrintFile($ast)) ?: throw new IOException("Could not write to file: '{$filePath}'");
        }
    }

    /**
     * @param array<Node\Stmt> $ast
     * @return array<Node\Stmt>
     */
    private function cleanClassAttribute(array $ast): array
    {
        $traverser = new NodeTraverser(
            new NameResolver(),
            new class($this->helper) extends NodeVisitorAbstract
            {
                public function __construct(
                    private readonly ParserHelper $helper,
                ) {
                }

                public function enterNode(Node $node): null
                {
                    if ($node instanceof Node\Stmt\Class_ && $this->helper->hasAttribute($node, PartialClass::class)) {
                        $this->helper->removeAttribute($node, PartialClass::class);
                    }
                    return null;
                }
            }
        );

        return $traverser->traverse($ast);
    }

    /**
     * @param array<Node\Stmt> $ast
     */
    private function throwOnUnimplementedProperties(array $ast): void
    {
        $traverser = new NodeTraverser(
            new NameResolver(),
            new class($this->helper) extends NodeVisitorAbstract
            {
                private string $className;

                public function __construct(
                    private readonly ParserHelper $helper,
                ) {
                }

                public function enterNode(Node $node): null
                {
                    if ($node instanceof Node\Stmt\Class_ && $node->name !== null) {
                        $this->className = $node->namespacedName->name;
                        return null;
                    }
                    if ($node instanceof Node\Stmt\Property && $this->helper->hasAttribute($node, PartialProperty::class)) {
                        $prop = $node->props[0];
                        throw new UnimplementedPropertyException("The property {$this->className}::\${$prop->name->name} is partial and must be implemented in a source generator.");
                    }

                    return null;
                }
            }
        );
        $traverser->traverse($ast);
    }

    /**
     * @param array<Node\Stmt> $ast
     */
    private function throwOnUnimplementedMethods(array $ast): void
    {
        $traverser = new NodeTraverser(
            new NameResolver(),
            new class($this->helper) extends NodeVisitorAbstract
            {
                private string $className;

                public function __construct(
                    private readonly ParserHelper $helper,
                ) {
                }

                public function enterNode(Node $node): null
                {
                    if ($node instanceof Node\Stmt\Class_ && $node->namespacedName !== null) {
                        $this->className = $node->namespacedName->name;
                        return null;
                    }
                    if ($node instanceof Node\Stmt\ClassMethod && $this->helper->hasAttribute($node, PartialMethod::class)) {
                        throw new UnimplementedPropertyException("The method {$this->className}::{$node->name->name}() is partial and must be implemented in a source generator.");
                    }

                    return null;
                }
            }
        );
        $traverser->traverse($ast);
    }

    /**
     * @param array<ReflectionClass> $partialClasses
     */
    private function throwOnUnimplementedClasses(array $partialClasses): void
    {
        foreach ($partialClasses as $partialClass) {
            if (!isset($this->classMapManager->getMap()[$partialClass->getName()])) {
                throw new UnimplementedPartialClassException("The class '{$partialClass->getName()}' is partial and must be implemented by a source generator.");
            }
        }
    }
}
