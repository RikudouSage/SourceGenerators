<?php

namespace App;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class SerializedName
{
    public function __construct(
        public string $name,
    ) {
    }
}
