<?php

namespace App;

#[Service(parameters: ['appVersion' => '1.0.0'])]
final readonly class SomeDependency
{
    public function __construct(
        public ?string $nullableDependency,
        public string $appVersion,
        public string $appName = 'someAppName',
    ) {
    }
}
