<?php

namespace App;

use PhpParser\Builder\Class_;
use PhpParser\Builder\Method;
use PhpParser\Comment\Doc;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Return_;
use Rikudou\SourceGenerators\Context\Context;
use Rikudou\SourceGenerators\Contract\SourceGenerator;
use Rikudou\SourceGenerators\Dto\ClassSource;

final class SomeInterfaceCollectionSourceGenerator implements SourceGenerator
{
    public function execute(Context $context): void
    {
        $interfaceToFind = SomeInterface::class;
        $interfaceShortName = (function() use ($interfaceToFind) {
            $parts = explode('\\', $interfaceToFind);
            return $parts[array_key_last($parts)];
        })();
        $namespace = (function() use ($interfaceToFind) {
            $parts = explode('\\', $interfaceToFind);
            unset($parts[array_key_last($parts)]);

            return implode('\\', $parts);
        })();

        $class = (new Class_('%className%'))
            ->makeFinal()
            ->makeReadonly()
        ;

        $method = (new Method('get'))
            ->setReturnType(new Identifier('array'))
            ->setDocComment(new Doc(<<<DOC
                /**
                 * @return array<\\{$interfaceToFind}>
                 */
                DOC
            ))
            ->makePublic();

        $resultArray = new Array_();
        $method->addStmt(new Return_($resultArray));
        foreach ($context->findClassesByParent($interfaceToFind) as $reflection) {
            $resultArray->items[] = new ArrayItem(new New_(new FullyQualified($reflection->getName())));
        }
        $class->addStmt($method);

        $context->addClassSource(new ClassSource(
            class: $interfaceShortName . 'Collection',
            namespace: $namespace,
            content: [$class->getNode()],
        ));
    }
}
