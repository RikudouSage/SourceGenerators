<?php

namespace App;

#[JsonSerializable]
final readonly class AnotherObjectToSerialize
{
    public function __construct(
        public string $value1,
        public int $value2,
    ) {
    }
}
