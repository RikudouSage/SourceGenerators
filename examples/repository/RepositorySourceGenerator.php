<?php

namespace App;

use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Method;
use PhpParser\Builder\Param;
use PhpParser\Comment;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\UnionType;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Rikudou\SourceGenerators\Context\Context;
use Rikudou\SourceGenerators\Contract\SourceGenerator;
use Rikudou\SourceGenerators\Dto\ClassSource;

final readonly class RepositorySourceGenerator implements SourceGenerator
{
    public function execute(Context $context): void
    {
        $entities = $context->findClassesByAttribute(Entity::class);
        foreach ($entities as $entity) {
            $class = (new Class_('%className%'))
                ->makeFinal()
                ->makeReadonly()
            ;

            $entityFqn = new FullyQualified($entity->getName());

            $columns = array_filter(
                $entity->getProperties(),
                fn (ReflectionProperty $property) => count($property->getAttributes(Column::class)),
            );
            $params = array_map(
                fn (ReflectionProperty $property) =>
                    (new Param($property->getName()))
                        ->setType($this->makeNullable($this->toType($property->getType())))
                        ->setDefault(null),
                $columns,
            );

            $findBy = (new Method('findBy'))
                ->makePublic()
                ->setReturnType(new Identifier('array'))
                ->setDocComment(new Comment\Doc(<<<DOC
                    /**
                     * @return array<\\{$entityFqn}>
                     */
                    DOC
                ))
                ->addStmt($this->comment('TODO: actually implement this method'))
                ->addParams($params)
            ;

            $findAll = (new Method('findAll'))
                ->makePublic()
                ->setReturnType(new Identifier('array'))
                ->setDocComment(new Comment\Doc(<<<DOC
                    /**
                     * @return array<\\{$entityFqn}>
                     */
                    DOC
                ))
                ->addStmt(new Return_(
                    new MethodCall(
                        new Variable('this'),
                        $findBy->getNode()->name,
                    )
                ))
            ;

            $findOneBy = (new Method('findOneBy'))
                ->makePublic()
                ->setReturnType(new NullableType($entityFqn))
                ->addParams($params)
                ->addStmt(new Return_(
                    new Coalesce(
                        new ArrayDimFetch(
                            new MethodCall(new Variable('this'), 'findBy', array_map(
                                fn (ReflectionProperty $property) => new Variable($property->getName()),
                                $columns,
                            )),
                            new Int_(0),
                        ),
                        new ConstFetch(new Name('null')),
                    )
                ))
            ;

            $class
                ->addStmt($findBy)
                ->addStmt($findAll)
                ->addStmt($findOneBy)
            ;

            $context->addClassSource(new ClassSource(
                class: $entity->getShortName() . 'Repository',
                namespace: $entity->getNamespaceName() . '\\Repository',
                content: [$class->getNode()],
            ));
        }
    }

    private function comment(string $comment): Nop
    {
        return new Nop([
            'comments' => [new Comment('// ' . $comment)],
        ]);
    }

    private function toType(?ReflectionType $type): Identifier|Name|ComplexType
    {
        if ($type === null) {
            return new Identifier('mixed');
        }

        $basicTypes = [
            'int',
            'float',
            'string',
            'bool',
            'array',
            'object',
            'mixed',
            'void',
            'iterable',
            'callable',
            'self',
            'static',
            'parent',
            'null',
        ];

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            if (in_array($typeName, $basicTypes, true)) {
                return new Identifier($type->getName());
            }

            if (class_exists($typeName) || interface_exists($typeName) || enum_exists($typeName)) {
                return new FullyQualified($typeName);
            }

            throw new LogicException('Unsupported type: ' . $typeName);
        }

        $childTypes = array_map(
            fn (ReflectionType $type) => $this->toType($type),
            $type->getTypes(),
        );

        if ($type instanceof ReflectionUnionType) {
            return new UnionType($childTypes);
        }

        if ($type instanceof ReflectionIntersectionType) {
            return new IntersectionType($childTypes);
        }

        throw new LogicException('Unsupported type: ' . get_class($type));
    }

    private function makeNullable(Name|Identifier|ComplexType $type): Name|Identifier|ComplexType
    {
        if ($type instanceof NullableType) {
            return $type;
        }

        if ($type instanceof Name || $type instanceof Identifier) {
            return new NullableType($type);
        }

        if ($type instanceof UnionType) {
            foreach ($type->types as $subType) {
                if (($subType instanceof Name || $subType instanceof Identifier) && (string) $subType === 'null') {
                    return $type;
                }
            }

            $newType = clone $type;
            $newType->types[] = new Identifier('null');

            return $newType;
        }

        return new UnionType([$type, new Identifier('null')]);
    }
}
