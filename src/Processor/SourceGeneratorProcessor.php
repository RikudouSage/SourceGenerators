<?php

namespace Rikudou\SourceGenerators\Processor;

use ReflectionClass;
use ReflectionException;
use Rikudou\SourceGenerators\Attribute\PartialClass;
use Rikudou\SourceGenerators\Autoloader\AutoloadManager;
use Rikudou\SourceGenerators\Autoloader\TargetClassMapManager;
use Rikudou\SourceGenerators\Context\SourceGeneratorContext;
use Rikudou\SourceGenerators\Contract\SourceGenerator;
use Rikudou\SourceGenerators\Extractor\Psr4Rule;
use Rikudou\SourceGenerators\Service\DirectoryManager;
use Rikudou\SourceGenerators\Service\SourceClassMapManager;

final readonly class SourceGeneratorProcessor
{
    public AutoloadManager $autoloadManager;
    private DirectoryManager $directoryManager;
    private NewSourceProcessor $newSourceProcessor;
    private PropertyProcessor $propertyProcessor;
    private MethodProcessor $methodProcessor;
    private TargetClassMapManager $targetClassMapManager;
    private SourceClassMapManager $sourceClassMapManager;
    private PartialAttributeCleanerProcessor $attributeCleaner;
    private EmptyClassProcessor $emptyClassProcessor;

    /**
     * @param array<Psr4Rule> $psr4Rules
     */
    public function __construct(
        array          $psr4Rules,
        private string $targetDirectory,
        callable       $autoloadRegistrar,
    ) {
        $this->sourceClassMapManager = new SourceClassMapManager($psr4Rules);
        $this->directoryManager = new DirectoryManager($this->targetDirectory);
        $this->targetClassMapManager = new TargetClassMapManager();
        $this->autoloadManager = new AutoloadManager($this->targetDirectory, $autoloadRegistrar(...), $this->targetClassMapManager);
        $this->newSourceProcessor = new NewSourceProcessor($this->targetDirectory, $this->targetClassMapManager);
        $this->propertyProcessor = new PropertyProcessor($this->targetDirectory, $this->targetClassMapManager);
        $this->methodProcessor = new MethodProcessor($this->targetDirectory, $this->targetClassMapManager);
        $this->attributeCleaner = new PartialAttributeCleanerProcessor($this->targetClassMapManager);
        $this->emptyClassProcessor = new EmptyClassProcessor($this->targetDirectory, $this->targetClassMapManager);
    }

    public function execute(): void
    {
        $this->directoryManager->prepare();

        /** @var array<class-string<SourceGenerator>> $sourceGenerators */
        $sourceGenerators = array_filter(
            $this->sourceClassMapManager->classMap,
            fn (string $class) => is_a($class, SourceGenerator::class, true),
        );
        if (!count($sourceGenerators)) {
            return;
        }

        /** @var ReflectionClass[] $reflections */
        $reflections = array_filter(array_map(
            function (string $class) {
                try {
                    return new ReflectionClass($class);
                } catch (ReflectionException) {
                    return null;
                }
            },
            $this->sourceClassMapManager->classMap,
        ));

        $partialClasses = array_filter(
            $reflections,
            fn (ReflectionClass $reflection) => count($reflection->getAttributes(PartialClass::class)) > 0,
        );


        /** @var array<string, class-string> $implemented */
        $implemented = [];
        $newSources = [];
        $propertyImplementations = [];
        $methodImplementations = [];
        $classImplementations = [];

        foreach ($sourceGenerators as $sourceGenerator) {
            $context = new SourceGeneratorContext($partialClasses, $implemented, $sourceGenerator, $reflections);
            $instance = new $sourceGenerator;
            $instance->execute($context);
            $implemented = array_merge($implemented, $context->getNewlyImplemented());

            $newSources = [...$newSources, ...$context->getNewSources()];
            $propertyImplementations = [...$propertyImplementations, ...$context->getImplementedProperties()];
            $methodImplementations = [...$methodImplementations, ...$context->getImplementedMethods()];
            $classImplementations = [...$classImplementations, ...$context->getImplementedClasses()];
        }

        $this->newSourceProcessor->process($newSources);
        $this->propertyProcessor->process($propertyImplementations);
        $this->methodProcessor->process($methodImplementations);
        $this->emptyClassProcessor->process($classImplementations);
        $this->attributeCleaner->process($partialClasses);

        $this->autoloadManager->dumpCustomAutoloader();

//        $parser = (new ParserFactory())->createForNewestSupportedVersion();
//        foreach ($partialClasses as $partialClass) {
//            $methods = array_filter($implementedMethods, fn (array $method) => $method['class'] === $partialClass->getName());
//            $properties = array_filter($implementedProperties, fn (array $property) => $property['class'] === $partialClass->getName());
//
//            $fileName = $partialClass->getShortName() . '_' . hash('sha512', serialize($methods) . serialize($properties)) . '.php';
//            $filePath = "{$this->targetDirectory}/{$fileName}";
//            copy($partialClass->getFileName(), $filePath);
//
//            $ast = $parser->parse(file_get_contents($filePath));
//            $traverser = new NodeTraverser(
//                new NameResolver(),
//                new class(
//                    $properties,
//                    $this->hasAttribute(...),
//                    $this->getPropertyName(...),
//                ) extends NodeVisitorAbstract {
//                    public function __construct(
//                        private array            &$properties,
//                        private readonly Closure $hasAttribute,
//                        private readonly Closure $getPropertyName,
//                    ) {
//                    }
//
//                    public function enterNode(Node $node): null
//                    {
//                        if (count($this->properties) && $node instanceof Node\Stmt\Property && ($this->hasAttribute)($node, PartialProperty::class)) {
//                            /** @var array<array{class: class-string, name: string, type: string, defaultValue: string|null}> $implementedProperties */
//                            $name = ($this->getPropertyName)($node);
//                            foreach ($this->properties as $key => $property) {
//                                if ($property['name'] !== $name) {
//                                    continue;
//                                }
//
//                                $node->type = new Node\Identifier($property['type']);
//                                if ($property['defaultValue'] !== null) {
//                                    $node->props[0]->default = $this->toExpression($property['defaultValue']);
//                                }
//
//                                unset($this->properties[$key]);
//                                break;
//                            }
//                        }
//
//                        return null;
//                    }
//
//                    private function toExpression(Expr|string|bool|int|float|array $value): Expr
//                    {
//                        if ($value instanceof Expr) {
//                            return $value;
//                        }
//
//                        if (is_string($value)) {
//                            return new Node\Scalar\String_($value);
//                        }
//                        if (is_bool($value)) {
//                            return new Expr\ConstFetch(new Node\Name($value ? 'true' : 'false'));
//                        }
//                        if (is_int($value)) {
//                            return new Node\Scalar\Int_($value);
//                        }
//                        if (is_float($value)) {
//                            return new Node\Scalar\Float_($value);
//                        }
//                        if (is_array($value)) {
//                            $items = [];
//                            foreach ($value as $key => $item) {
//                                $items[] = new Node\ArrayItem($this->toExpression($item), $this->toExpression($key));
//                            }
//                            return new Expr\Array_($items);
//                        }
//
//                        throw new LogicException('Could not conver type to expression: ' . gettype($value));
//                    }
//                }
//            );
//            $ast = $traverser->traverse($ast);
//
//            $prettyPrinter = new Standard();
//            file_put_contents($filePath, $prettyPrinter->prettyPrintFile($ast));
//
//            $classMap[$partialClass->getName()] = $filePath;
//        }
    }
}
