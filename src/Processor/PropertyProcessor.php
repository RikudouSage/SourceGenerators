<?php

namespace Rikudou\SourceGenerators\Processor;

use Closure;
use LogicException;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;
use Rikudou\SourceGenerators\Attribute\PartialProperty;
use Rikudou\SourceGenerators\Autoloader\TargetClassMapManager;
use Rikudou\SourceGenerators\Dto\PropertyImplementation;
use Rikudou\SourceGenerators\Exception\IOException;
use Rikudou\SourceGenerators\Parser\ParserHelper;

/**
 * @internal
 */
final readonly class PropertyProcessor
{
    private PrettyPrinter $dumper;
    private Parser $parser;
    private ParserHelper $helper;

    public function __construct(
        private string                $targetDirectory,
        private TargetClassMapManager $classMapManager,
    ) {
        $this->dumper = new Standard();
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->helper = new ParserHelper();
    }

    /**
     * @param array<PropertyImplementation> $properties
     */
    public function process(array $properties): void
    {
        foreach ($properties as $property) {
            $classReflection = new ReflectionClass($property->class);
            $fileName = $classReflection->getShortName() . '.php';
            $filePath = "{$this->targetDirectory}/{$fileName}";

            if (!is_file($filePath)) {
                copy($classReflection->getFileName(), $filePath) ?: throw new IOException("Failed copying from '{$classReflection->getFileName()}' to '{$filePath}'");
            }

            $ast = $this->parser->parse(file_get_contents($filePath));

            if ($classReflection->hasProperty($property->name)) {
                $ast = $this->modifyProperty($ast, $property);
            } else {
                $ast = $this->addProperty($ast, $property);
            }

            file_put_contents($filePath, $this->dumper->prettyPrintFile($ast)) ?: throw new IOException("Could not write to file: '{$filePath}'");
            $this->classMapManager->add($property->class, $filePath);
        }
    }

    /**
     * @param array<Stmt> $ast
     * @return array<Stmt>
     */
    private function addProperty(array $ast, PropertyImplementation $property): array
    {
        $traverser = new NodeTraverser(
            new NameResolver(),
            new class ($property, $this->helper) extends NodeVisitorAbstract
            {
                public function __construct(
                    private readonly PropertyImplementation $property,
                    private readonly ParserHelper $helper,
                ) {
                }

                public function enterNode(Node $node): null
                {
                    if (!$node instanceof Stmt\Class_) {
                        return null;
                    }

                    $node->stmts[] = new Stmt\Property(
                        $this->property->flags ?? Modifiers::PRIVATE,
                        [
                            new Node\PropertyItem(
                                new Node\VarLikeIdentifier($this->property->name),
                                $this->property->defaultValue !== null ? $this->helper->toExpression($this->property->defaultValue) : null,
                            ),
                        ],
                        type: new Identifier($this->property->type),
                    );

                    return null;
                }
            }
        );

        return $traverser->traverse($ast);
    }

    /**
     * @param array<Stmt> $ast
     * @return array<Stmt>
     */
    private function modifyProperty(array $ast, PropertyImplementation $property): array
    {
        $traverser = new NodeTraverser(
            new NameResolver(),
            new class ($property, $this->helper) extends NodeVisitorAbstract
            {
                public function __construct(
                    private readonly PropertyImplementation $property,
                    private readonly ParserHelper $helper,
                ) {
                }

                public function enterNode(Node $node): null
                {
                    if (!$node instanceof Stmt\Property) {
                        return null;
                    }

                    foreach ($node->props as $prop) {
                        if ($prop->name->name !== $this->property->name) {
                            continue;
                        }

                        if ($this->property->flags !== null) {
                            $node->flags = $this->property->flags;
                        }

                        $prop->default = $this->property->defaultValue ? $this->helper->toExpression($this->property->defaultValue) : null;
                        $node->type = new Identifier($this->property->type);

                        if ($this->helper->hasAttribute($node, PartialProperty::class)) {
                            $this->helper->removeAttribute($node, PartialProperty::class);
                        }

                        return null;
                    }

                    return null;
                }
            }
        );

        return $traverser->traverse($ast);
    }
}
