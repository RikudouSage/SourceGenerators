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

/**
 * @internal
 */
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
    }
}
