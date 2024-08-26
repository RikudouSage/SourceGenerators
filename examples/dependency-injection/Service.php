<?php

namespace App;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Service
{
    public function __construct(
        public array $parameters = [],
    ) {
    }
}
