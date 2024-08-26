<?php

namespace App;

#[Service]
final readonly class SomeService
{
    public function __construct(
        public SomeDependency $dependency,
    ) {
    }
}
