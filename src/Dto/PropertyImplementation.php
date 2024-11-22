<?php

namespace Rikudou\SourceGenerators\Dto;

use JetBrains\PhpStorm\ExpectedValues;
use PhpParser\Modifiers;
use PhpParser\Node\Expr;

final readonly class PropertyImplementation
{
    /**
     * @param class-string $class
     * @param Expr|null|string|bool|int|float|array<mixed> $defaultValue
     * @codeCoverageIgnore
     */
    public function __construct(
        public string                                $class,
        public string                                $name,
        public ?string                               $type = null,
        public Expr|null|string|bool|int|float|array $defaultValue = null,
        #[ExpectedValues(flagsFromClass: Modifiers::class)]
        public ?int $flags = null,
    ) {
    }
}
