A simplified statically compiled dependency injection container. Note that it's not production-ready - it does not
support many nice to have features of common dependency containers and does not really work even as a simplified DI
due to same intentionally not implemented features (default values that are constants, default object values).

Anyway, if you include the files from this example, you will get a nice statically compiled dependency injection
container like this:

```php
<?php

declare (strict_types=1);
namespace App\DependencyInjection;

final class DependencyContainer
{
    private function __construct()
    {
    }
    public static function create(): self
    {
        return new self();
    }
    public function get(string $service): object
    {
        return match ($service) {
            default => throw new \LogicException('Could not find a service with the name ' . $service),
            'App\SomeDependency' => new \App\SomeDependency(null, '1.0.0', 'someAppName'),
            'App\SomeService' => new \App\SomeService($this->get('App\SomeDependency')),
        };
    }
}
```

This parses all classes that are tagged with [`#[Service]`](Service.php) attribute and constructs a dependency graph
and then dumps it down to a php class. The algorithm used is not the fastest, but that's one of the biggest advantages
of source generation - it doesn't have to be because it does not have any effect on runtime.
