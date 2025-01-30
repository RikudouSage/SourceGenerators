<?php

namespace App;

#[JsonSerializable]
final readonly class ObjectToSerialize
{
    public function __construct(
        public string $test1,
        public string $test2 = 'world',
        #[SerializedName('renamedProperty')]
        public int $test3 = 3,
        public string $version = PHP_VERSION,
    ) {
    }
}
