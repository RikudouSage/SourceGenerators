If you include the files from this example in your project together with the source generators package, the following
class should be generated:

```php
<?php

declare (strict_types=1);
namespace App;

final readonly class SomeInterfaceCollection
{
    /**
     * @return array<\App\SomeInterface>
     */
    public function get(): array
    {
        return [new \App\SomeClass1(), new \App\SomeClass2()];
    }
}
```

This example could easily be made even more generic by introducing an attribute `#[Autocollection]` and processing
all interfaces marked with this attribute. That way you could even make a separate package just with the source generator
and the attribute.
