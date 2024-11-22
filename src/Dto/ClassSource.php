<?php

namespace Rikudou\SourceGenerators\Dto;

use PhpParser\Node\Stmt;

final readonly class ClassSource
{
    /**
     * @param string|array<Stmt> $content
     * @codeCoverageIgnore
     */
    public function __construct(
        public string       $class,
        public ?string      $namespace,
        public string|array $content,
    ) {
    }
}
