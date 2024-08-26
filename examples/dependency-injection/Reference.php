<?php

namespace App;

/**
 * A DTO object that's used as a placeholder when dumping the container
 */
final readonly class Reference
{
    public function __construct(
        public string $serviceName,
    ) {
    }
}
