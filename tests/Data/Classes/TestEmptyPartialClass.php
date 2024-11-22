<?php

namespace Rikudou\Tests\SourceGenerators\Data\Classes;


use Rikudou\SourceGenerators\Attribute\PartialClass;

#[PartialClass]
final class TestEmptyPartialClass
{
    public function someMethod(): void
    {
        echo "Test";
    }
}
