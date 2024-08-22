<?php

namespace Rikudou\SourceGenerators\Dto;

use JetBrains\PhpStorm\ExpectedValues;
use PhpParser\Modifiers;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;

final readonly class MethodImplementation
{
    /**
     * @param class-string $class
     * @param string|array<Stmt> $body
     * @param array<string|Param>|null $parameters
     */
    public function __construct(
        public string       $class,
        public string       $name,
        public string|array $body,
        #[ExpectedValues(flagsFromClass: Modifiers::class)]
        public ?int $flags = null,
        public ?array $parameters = null,
        public string|Name|Identifier|null $returnType = null,
    ) {
    }
}
