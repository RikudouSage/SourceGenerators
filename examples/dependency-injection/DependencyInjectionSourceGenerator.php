<?php

namespace App;

use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Method;
use PhpParser\Builder\Param;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\Cast\Bool_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Rikudou\SourceGenerators\Context\Context;
use Rikudou\SourceGenerators\Contract\SourceGenerator;
use Rikudou\SourceGenerators\Dto\ClassSource;

final readonly class DependencyInjectionSourceGenerator implements SourceGenerator
{
    public function execute(Context $context): void
    {
        $services = $context->findClassesByAttribute(Service::class);
        $container = $this->getContainer([...$services]);


        $getServiceMethod = (new Method('get'))
            ->makePublic()
            ->setReturnType(new Identifier('object'))
            ->addParam(
                (new Param('service'))
                    ->setType(new Identifier('string'))
            );

        $switch = new Match_(new Variable('service'), [
            new MatchArm(
                [new Name('default')],
                new Throw_(new New_(new Name\FullyQualified(LogicException::class), [
                    new Concat(
                        new String_('Could not find a service with the name '),
                        new Variable('service'),
                    )
                ])),
            ),
        ]);

        foreach ($container as $class => $parameters) {
            $args = [];
            foreach ($parameters as $parameter) {
                if ($parameter instanceof Reference) {
                    $args[] = new MethodCall(new Variable('this'), 'get', [new String_($parameter->serviceName)]);
                } else {
                    $args[] = $this->valueToExpression($parameter);
                }
            }
            $switch->arms[] = new MatchArm(
                [new String_($class)],
                new New_(new Name\FullyQualified($class), $args),
            );
        }

        $getServiceMethod->addStmt(new Return_($switch));

        $containerClass = (new Class_('%className%'))
            ->makeFinal()
            ->addStmt((new Method('__construct'))->makePrivate())
            ->addStmt(
                (new Method('create'))
                    ->makePublic()
                    ->makeStatic()
                    ->setReturnType(new Name('self'))
                    ->addStmt(new Return_(new New_(new Name('self'))))
            )
            ->addStmt($getServiceMethod)
        ;

        $context->addClassSource(new ClassSource(
            class: 'DependencyContainer',
            namespace: 'App\DependencyInjection',
            content: [$containerClass->getNode()],
        ));
    }

    /**
     * @param array<ReflectionClass> $services
     * @return array<class-string<object>, array<mixed>>
     */
    private function getContainer(array $services): array
    {
        $lastRemainingCount = count($services);

        /** @var array<class-string<object>, array<mixed>> $container */
        $container = [];

        while (true) {
            foreach ($services as $key => $service) {
                if (!$this->canBeConstructed($service, array_keys($container))) {
                    continue;
                }

                $container[$service->getName()] = $this->constructDependencies($service, array_keys($container));
                unset($services[$key]);
            }

            $currentRemainingCount = count($services);
            if ($currentRemainingCount === 0) {
                break;
            }
            if ($currentRemainingCount === $lastRemainingCount) {
                throw new LogicException('The classes cannot be constructed into a proper dependency graph. Perhaps a missing scalar argument or a circular dependency?');
            }
            $lastRemainingCount = $currentRemainingCount;
        }

        return $container;
    }

    /**
     * @param array<string> $alreadyConstructedNames
     */
    private function canBeConstructed(ReflectionClass $class, array $alreadyConstructedNames): bool
    {
        $constructor = $class->getConstructor();
        if ($constructor === null || count($constructor->getParameters()) === 0) {
            return true;
        }

        // since we're here, we can safely assume there's a service attribute
        $serviceAttribute = $class->getAttributes(Service::class)[0]->newInstance();
        assert($serviceAttribute instanceof Service);

        $constructorParameterNames = array_map(fn (ReflectionParameter $parameter) => $parameter->getName(), $constructor->getParameters());
        $attributeProvidedNames = array_keys($serviceAttribute->parameters);

        $missingInjected = array_diff($constructorParameterNames, $attributeProvidedNames);

        if (!count($missingInjected)) {
            return true;
        }

        foreach ($constructor->getParameters() as $parameter) {
            if (isset($serviceAttribute->parameters[$parameter->getName()])) {
                continue;
            }
            $type = $parameter->getType();
            if ($type->allowsNull() || $parameter->isDefaultValueAvailable()) {
                continue;
            }
            if (!$type instanceof ReflectionNamedType) {
                throw new LogicException("Parameter \${$parameter->getName()} of type {$type->getName()} cannot be automatically injected");
            }

            if (!class_exists($type->getName())) {
                throw new LogicException("Parameter \${$parameter->getName()} of type {$type->getName()} cannot be automatically injected");
            }

            if (!in_array($type->getName(), $alreadyConstructedNames)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param ReflectionClass $service
     * @param array<string> $containerClassNames
     * @return array<mixed>
     */
    private function constructDependencies(ReflectionClass $service, array $containerClassNames): array
    {
        $constructor = $service->getConstructor();
        if ($constructor === null || count($constructor->getParameters()) === 0) {
            return [];
        }

        // since we're here, we can safely assume there's a service attribute
        $serviceAttribute = $service->getAttributes(Service::class)[0]->newInstance();
        assert($serviceAttribute instanceof Service);

        $returnDefault = function (ReflectionParameter $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            if ($parameter->allowsNull()) {
                return null;
            }

            throw new LogicException("Parameter \${$parameter->getName()} cannot be automatically injected");
        };

        $result = [];
        foreach ($constructor->getParameters() as $parameter) {
            if (array_key_exists($parameter->getName(), $serviceAttribute->parameters)) {
                $result[] = $serviceAttribute->parameters[$parameter->getName()];
                continue;
            }

            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType) {
                $result[] = $returnDefault($parameter);
                continue;
            }

            $targetClass = $type->getName();
            if (!class_exists($targetClass)) {
                $result[] = $returnDefault($parameter);
                continue;
            }

            if (in_array($targetClass, $containerClassNames, true)) {
                $result[] = new Reference($targetClass);
                continue;
            }

            if ($type->allowsNull()) {
                $result[] = null;
                continue;
            }

            throw new LogicException("Parameter \${$parameter->getName()} of type {$type->getName()} cannot be automatically injected");
        }

        return $result;
    }

    private function valueToExpression(mixed $value): Expr
    {
        if (is_string($value)) {
            return new String_($value);
        }

        if (is_int($value)) {
            return new Int_($value);
        }

        if (is_float($value)) {
            return new Float_($value);
        }

        if (is_bool($value)) {
            return new Bool_($value);
        }

        if ($value === null) {
            return new ConstFetch(new Name('null'));
        }

        if (is_array($value)) {
            $result = new Expr\Array_();
            foreach ($value as $key => $item) {
                $result->items[] = new ArrayItem($this->valueToExpression($item), $this->valueToExpression($key));
            }

            return $result;
        }

        throw new LogicException('Unsupported value type: ' . gettype($value));
    }
}
