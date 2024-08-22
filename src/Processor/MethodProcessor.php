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
use Rikudou\SourceGenerators\Attribute\PartialMethod;
use Rikudou\SourceGenerators\Autoloader\TargetClassMapManager;
use Rikudou\SourceGenerators\Dto\MethodImplementation;
use Rikudou\SourceGenerators\Exception\IOException;
use Rikudou\SourceGenerators\Exception\NonPartialMethodException;
use Rikudou\SourceGenerators\Parser\ParserHelper;

final readonly class MethodProcessor
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
     * @param array<MethodImplementation> $methods
     */
    public function process(array $methods): void
    {
        foreach ($methods as $method) {
            $classReflection = new ReflectionClass($method->class);
            $fileName = $classReflection->getShortName() . '.php';
            $filePath = "{$this->targetDirectory}/{$fileName}";

            if (!is_file($filePath)) {
                copy($classReflection->getFileName(), $filePath) ?: throw new IOException("Failed copying from '{$classReflection->getFileName()}' to '{$filePath}'");
            }

            $ast = $this->parser->parse(file_get_contents($filePath));

            if ($classReflection->hasMethod($method->name)) {
                $ast = $this->modifyMethod($ast, $method);
            } else {
                $ast = $this->addMethod($ast, $method);
            }

            file_put_contents($filePath, $this->dumper->prettyPrintFile($ast)) ?: throw new IOException("Could not write to file: '{$filePath}'");
            $this->classMapManager->add($method->class, $filePath);
        }
    }

    /**
     * @param array<Stmt> $ast
     * @return array<Stmt>
     */
    private function addMethod(array $ast, MethodImplementation $method): array
    {
        $traverser = new NodeTraverser(
            new NameResolver(),
            new class ($method, $this->parser, $this->normalizeParams(...)) extends NodeVisitorAbstract
            {
                public function __construct(
                    private readonly MethodImplementation $method,
                    private readonly Parser $parser,
                    private readonly Closure $normalizeParams,
                ) {
                }

                public function enterNode(Node $node): null
                {
                    if (!$node instanceof Stmt\Class_) {
                        return null;
                    }

                    $body = $this->method->body;
                    if (is_string($this->method->body)) {
                        if (!str_starts_with($body, '<?php')) {
                            $body = '<?php ' . $body;
                        }
                        $body = $this->parser->parse($body);
                    }

                    $returnType = $this->method->returnType;
                    if (is_string($returnType)) {
                        $returnType = new Identifier($returnType);
                    }

                    $node->stmts[] = new Stmt\ClassMethod($this->method->name, [
                        'flags' => $this->method->flags ?? Modifiers::PUBLIC,
                        'stmts' => $body,
                        'params' => ($this->normalizeParams)($this->method->parameters ?? []),
                        'returnType' => $returnType,
                    ]);

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
    private function modifyMethod(array $ast, MethodImplementation $method): array
    {
        $traverser = new NodeTraverser(
            new NameResolver(),
            new class ($method, $this->parser, $this->normalizeParams(...), $this->helper) extends NodeVisitorAbstract
            {

                public function __construct(
                    private readonly MethodImplementation $method,
                    private readonly Parser $parser,
                    private readonly Closure $normalizeParams,
                    private readonly ParserHelper $helper,
                ) {
                }

                public function enterNode(Node $node): null
                {
                    if (!$node instanceof Stmt\ClassMethod) {
                        return null;
                    }

                    if ($node->name->name !== $this->method->name) {
                        return null;
                    }

                    if (!$this->helper->hasAttribute($node, PartialMethod::class)) {
                        throw new NonPartialMethodException("You're trying to overwrite method {$this->method->class}::{$node->name->name}(), but it's not marked as partial.");
                    }

                    $body = $this->method->body;
                    if (is_string($this->method->body)) {
                        if (!str_starts_with($body, '<?php')) {
                            $body = '<?php ' . $body;
                        }
                        $body = $this->parser->parse($body);
                    }

                    $returnType = $this->method->returnType;
                    if (is_string($returnType)) {
                        $returnType = new Identifier($returnType);
                    }

                    $node->stmts = $body;
                    if ($this->method->flags !== null) {
                        $node->flags = $this->method->flags;
                    }
                    if ($returnType !== null) {
                        $node->returnType = $returnType;
                    }
                    if ($this->method->parameters !== null) {
                        $node->params = ($this->normalizeParams)($this->method->parameters);
                    }

                    $this->helper->removeAttribute($node, PartialMethod::class);

                    return null;
                }
            }
        );

        return $traverser->traverse($ast);
    }

    /**
     * @param array<Node\Param|string> $params
     * @return array<Node\Param>
     */
    private function normalizeParams(array $params): array
    {
        return array_map(function (Node\Param|string $param) {
            if ($param instanceof Node\Param) {
                return $param;
            }

            $parts = explode(' ', $param);
            if (count($parts) === 1) {
                return new Node\Param(new Node\Expr\Variable($parts[0]));
            } else if (count($parts) === 2) {
                return new Node\Param(
                    new Node\Expr\Variable(substr($parts[1], 1)),
                    type: new Identifier($parts[0]),
                );
            }

            throw new LogicException("Unsupported parameter string: '{$param}'. If you need more complex parameter, use the " . Node\Param::class . ' class');
        }, $params);
    }

}
