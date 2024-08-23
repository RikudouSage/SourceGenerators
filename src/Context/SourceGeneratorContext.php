<?php

namespace Rikudou\SourceGenerators\Context;

use PhpParser\Modifiers;
use PhpParser\Node\Expr;
use ReflectionClass;
use ReflectionException;
use Rikudou\Iterables\Iterables;
use Rikudou\SourceGenerators\Attribute\PartialClass;
use Rikudou\SourceGenerators\Attribute\PartialMethod;
use Rikudou\SourceGenerators\Attribute\PartialProperty;
use Rikudou\SourceGenerators\Contract\SourceGenerator;
use Rikudou\SourceGenerators\Dto\MethodImplementation;
use Rikudou\SourceGenerators\Dto\ClassSource;
use Rikudou\SourceGenerators\Dto\PropertyImplementation;
use Rikudou\SourceGenerators\Exception\ClassExistsException;
use Rikudou\SourceGenerators\Exception\InvalidFlagsException;
use Rikudou\SourceGenerators\Exception\MethodAlreadyImplementedException;
use Rikudou\SourceGenerators\Exception\NonPartialClassException;
use Rikudou\SourceGenerators\Exception\NonPartialMethodException;
use Rikudou\SourceGenerators\Exception\PropertyExistsException;

/**
 * @internal
 */
final class SourceGeneratorContext implements Context
{
    /**
     * @var array<ClassSource>
     */
    private array $newSources = [];

    /**
     * @var array<string, class-string>
     */
    private array $newlyImplemented = [];

    /**
     * @var array<MethodImplementation>
     */
    private array $implementedMethods = [];

    /** @var array<PropertyImplementation> */
    private array $implementedProperties = [];

    /** @var array<class-string> */
    private array $implementedClasses = [];

    /**
     * @param iterable<ReflectionClass> $partialClasses
     * @param array<string, class-string> $alreadyImplemented
     * @param class-string<SourceGenerator> $sourceGenerator
     * @param array<ReflectionClass> $allClasses
     */
    public function __construct(
        private readonly iterable $partialClasses,
        private readonly array $alreadyImplemented,
        private readonly string $sourceGenerator,
        private readonly array $allClasses,
    ) {
    }

    public function getPartialClasses(): iterable
    {
        return $this->partialClasses;
    }

    public function findClassesByAttribute(string $attribute): iterable
    {
        return Iterables::filter($this->allClasses, fn (ReflectionClass $class) => count($class->getAttributes($attribute)) > 0);
    }

    public function findClassesByParent(string $parent): iterable
    {
        return Iterables::filter($this->allClasses, fn (ReflectionClass $class) => is_a($class->getName(), $parent, true));
    }

    public function getAllClasses(): iterable
    {
        return $this->allClasses;
    }

    public function findClass(string $class): ?ReflectionClass
    {
        if (class_exists($class)) {
            return new ReflectionClass($class);
        }

        return null;
    }

    public function addClassSource(ClassSource $source): void
    {
        if (class_exists("{$source->namespace}\\{$source->class}")) {
            throw new ClassExistsException("The class {$source->namespace}\\{$source->class} already exists. If you're trying to implement a partial class, don't use addClassSource(), that's for adding entirely new sources.");
        }

        $this->newSources[] = $source;
    }

    public function implementPartialClassMethod(MethodImplementation $implementation): void
    {
        $fqn = "{$implementation->class}::{$implementation->name}";

        if (isset($this->alreadyImplemented[$fqn])) {
            throw new MethodAlreadyImplementedException("The partial method '{$fqn}()' is already implemented by source generator '{$this->alreadyImplemented[$fqn]}'");
        }

        $reflection = new ReflectionClass($implementation->class);
        if (!count($reflection->getAttributes(PartialClass::class))) {
            throw new NonPartialClassException("The class '{$reflection->getName()}' is not partial");
        }
        if ($reflection->hasMethod($implementation->name) && !$reflection->getMethod($implementation->name)->getAttributes(PartialMethod::class)) {
            throw new NonPartialMethodException("The method '{$fqn}()' exists but is not partial");
        }

        $this->newlyImplemented[$fqn] = $this->sourceGenerator;
        $this->implementedMethods[] = $implementation;
    }

    public function createPartialClassProperty(PropertyImplementation $implementation): void
    {
        $fqn = "{$implementation->class}::\${$implementation->name}";
        if ($implementation->flags & Modifiers::READONLY && $implementation->defaultValue) {
            throw new InvalidFlagsException("'{$fqn}' is a readonly property and thus cannot have a default value");
        }

        $reflection = new ReflectionClass($implementation->class);
        try {
            $property = $reflection->getProperty($implementation->name);
            if (!count($property->getAttributes(PartialProperty::class))) {
                throw new PropertyExistsException("The property '{$fqn}' exists and is not partial, cannot override");
            }
        } catch (ReflectionException) {
            // ignore, property does not exist, we can continue
        }

        if (isset($this->alreadyImplemented[$fqn])) {
            throw new PropertyExistsException("The property '{$fqn}' has already been implemented by source generator '{$this->alreadyImplemented[$fqn]}'");
        }

        $this->newlyImplemented[$fqn] = $this->sourceGenerator;
        $this->implementedProperties[] = $implementation;
    }

    public function markClassAsImplemented(string $className): void
    {
        $this->newlyImplemented[$className] = $this->sourceGenerator;
        $this->implementedClasses[] = $className;
    }

    /**
     * @return array<ClassSource>
     */
    public function getNewSources(): array
    {
        return $this->newSources;
    }

    /**
     * @return array<string, class-string>
     */
    public function getNewlyImplemented(): array
    {
        return $this->newlyImplemented;
    }

    /**
     * @return array<MethodImplementation>
     */
    public function getImplementedMethods(): array
    {
        return $this->implementedMethods;
    }

    /**
     * @return array<PropertyImplementation>
     */
    public function getImplementedProperties(): array
    {
        return $this->implementedProperties;
    }

    /**
     * @return array<class-string>
     */
    public function getImplementedClasses(): array
    {
        return $this->implementedClasses;
    }
}
