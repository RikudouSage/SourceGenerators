<?php

namespace Rikudou\SourceGenerators\Extractor;

/**
 * @internal
 */
final readonly class Psr4Rule
{
    public function __construct(
        public string $namespace,
        public string $directory,
    ) {
    }
}
