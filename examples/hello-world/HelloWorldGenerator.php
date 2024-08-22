<?php

namespace App;

use PhpParser\Builder\Class_;
use PhpParser\Builder\Method;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Echo_;
use Rikudou\SourceGenerators\Context\Context;
use Rikudou\SourceGenerators\Contract\SourceGenerator;
use Rikudou\SourceGenerators\Dto\ClassSource;

final readonly class HelloWorldGenerator implements SourceGenerator
{
    public function execute(Context $context): void
    {
        $context->addClassSource(new ClassSource(
            class: 'HelloWorld1',
            namespace: 'App',
            content: <<<EOF
                final class %className%
                {
                    public function sayHello(): void
                    {
                        echo 'Hello world!';
                    }
                }
                EOF
        ));

        $context->addClassSource(new ClassSource(
            class: 'HelloWorld2',
            namespace: 'App',
            content: [
                (new Class_('%className%'))
                    ->makeFinal()
                    ->addStmt(
                        (new Method('sayHello'))
                            ->makePublic()
                            ->setReturnType(new Identifier('void'))
                            ->addStmt(new Echo_([new String_('Hello world!')]))
                    )
                    ->getNode()
            ],
        ));
    }
}
