<?php

namespace Rikudou\SourceGenerators\Context;

use PhpParser\Node\Expr;
use ReflectionClass;
use Rikudou\SourceGenerators\Dto\MethodImplementation;
use Rikudou\SourceGenerators\Dto\ClassSource;
use Rikudou\SourceGenerators\Dto\PropertyImplementation;
use Rikudou\SourceGenerators\Exception\ClassExistsException;
use Rikudou\SourceGenerators\Exception\InvalidFlagsException;
use Rikudou\SourceGenerators\Exception\MethodAlreadyImplementedException;
use Rikudou\SourceGenerators\Exception\NonPartialClassException;
use Rikudou\SourceGenerators\Exception\NonPartialMethodException;
use Rikudou\SourceGenerators\Exception\PropertyExistsException;

interface Context
{
    /**
     * @return iterable<ReflectionClass<object>>
     */
    public function getPartialClasses(): iterable;

    /**
     * @param class-string $attribute
     * @return iterable<ReflectionClass<object>>
     */
    public function findClassesByAttribute(string $attribute): iterable;

    /**
     * @template T of object
     *
     * @param class-string<T> $parent
     * @return iterable<ReflectionClass<T>>
     */
    public function findClassesByParent(string $parent): iterable;

    /**
     * @return iterable<ReflectionClass<object>>
     */
    public function getAllClasses(): iterable;

    /**
     * You can use %className% in the $content, and it will be replaced with the class name you provide in $name.
     *
     * @throws ClassExistsException
     */
    public function addClassSource(ClassSource $source): void;

    /**
     * @throws NonPartialMethodException
     * @throws MethodAlreadyImplementedException
     */
    public function implementPartialClassMethod(MethodImplementation $implementation): void;

    /**
     * @throws PropertyExistsException
     * @throws NonPartialClassException
     * @throws InvalidFlagsException
     */
    public function createPartialClassProperty(PropertyImplementation $implementation): void;

    /**
     * @throws NonPartialClassException
     */
    public function markClassAsImplemented(string $className): void;
}
