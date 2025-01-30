<?php

namespace App;

use InvalidArgumentException;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Method;
use PhpParser\Builder\Param;
use PhpParser\BuilderFactory;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use Rikudou\SourceGenerators\Context\Context;
use Rikudou\SourceGenerators\Contract\SourceGenerator;
use Rikudou\SourceGenerators\Dto\ClassSource;
use Rikudou\SourceGenerators\Dto\MethodImplementation;

final readonly class JsonSerializerSourceGenerator implements SourceGenerator
{
    public function execute(Context $context): void
    {
        $body = [];

        foreach ($context->findClassesByAttribute(JsonSerializable::class) as $item) {
            $provider = $this->createProvider($context, $item);
            $body[] = new Expression(new Yield_(
                new New_(new FullyQualified($provider)),
                new String_($item->getName()),
            ));
        }

        $context->implementPartialClassMethod(new MethodImplementation(
            class: JsonSerializer::class,
            name: 'getProviders',
            body: $body,
        ));
    }

    private function createProvider(Context $context, ReflectionClass $item): string
    {
        $builderFactory = new BuilderFactory();
        $className = $item->getShortName() . 'SerializationProvider';

        $class = (new Class_($className))
            ->makeFinal()
            ->makeReadonly()
            ->implement(new FullyQualified(SerializationProvider::class))
        ;

        $serialize = (new Method('serialize'))
            ->makePublic()
            ->addParam((new Param('object'))->setType('object'))
            ->setReturnType('array')
            ->addStmt(
                new If_(
                    new BooleanNot(new Instanceof_(new Variable('object'), new FullyQualified($item->getName()))),
                    [
                        'stmts' => [
                            new Expression(new Throw_(new New_(
                                new FullyQualified(LogicException::class),
                                [
                                    new Concat(
                                        new Concat(
                                            new String_("This provider only supports instances of '{$item->getName()}', "),
                                            new FuncCall(new Name('get_debug_type'), [new Variable('object')])
                                        ),
                                        new String_(' given'),
                                    ),
                                ],
                            ))),
                        ],
                    ],
                ),
            )
        ;
        $deserialize = (new Method('deserialize'))
            ->makePublic()
            ->addParam((new Param('data'))->setType('array'))
            ->setReturnType(new FullyQualified($item->getName()))
        ;

        $serializeReturn = new Array_();
        foreach ($item->getProperties() as $property) {
            $name = $this->getAttribute($property, SerializedName::class)?->name ?? $property->getName();
            $serializeReturn->items[] = new ArrayItem(
                new PropertyFetch(new Variable('object'), $property->getName()),
                new String_($name),
            );
        }

        $serialize->addStmt(new Return_($serializeReturn));

        $deserializeArgs = [];
        foreach ($item->getProperties() as $property) {
            $name = $this->getAttribute($property, SerializedName::class)?->name ?? $property->getName();
            /** @var ReflectionParameter|null $constructorPromoted */
            $constructorPromoted = array_find(
                $item->getConstructor()?->getParameters() ?? [],
                fn (ReflectionParameter $param) => $param->getName() === $property->getName() && $param->isPromoted(),
            );

            if ($property->hasDefaultValue() || $constructorPromoted?->isDefaultValueAvailable()) {
                if ($property->hasDefaultValue()) {
                    $default = $builderFactory->val($property->getDefaultValue());
                } else if ($constructorPromoted->isDefaultValueConstant()) {
                    $constantName = $constructorPromoted->getDefaultValueConstantName();
                    if (!defined($constantName)) {
                        $constantName = substr($constantName, strlen($item->getNamespaceName()) + 1);
                    }
                    $default = new ConstFetch(new FullyQualified($constantName));
                } else {
                    $default = $builderFactory->val($constructorPromoted->getDefaultValue());
                }
            } else {
                $default = new Throw_(new New_(
                    new FullyQualified(InvalidArgumentException::class),
                    [new Arg(new String_("The '{$name}' key is required when deserializing objects of type '{$item->getName()}'"))]
                ));
            }

            $deserializeArgs[] = new Arg(
                value: new Coalesce(
                    new ArrayDimFetch(new Variable('data'), new String_($name)),
                    $default,
                ),
                name: new Identifier($property->getName()),
            );
        }

        $deserialize->addStmt(new Return_(new New_(
            new FullyQualified($item->getName()),
            $deserializeArgs,
        )));

        $class
            ->addStmt($serialize)
            ->addStmt($deserialize)
        ;

        $context->addClassSource(new ClassSource(
            class: $className,
            namespace: $item->getNamespaceName(),
            content: [$class->getNode()],
        ));

        return $item->getNamespaceName() . '\\' . $className;
    }


    /**
     * @template T of object
     * @param class-string<T> $attributeClass
     * @return T|null
     */
    private function getAttribute(ReflectionProperty $reflection, string $attributeClass): ?object
    {
        $attributes = $reflection->getAttributes($attributeClass);
        if (!count($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
