An example implementation of automatically generated repository for any entity. Allows for strong typing and is made to
be used with named arguments.

For the example entity [Page](Page.php), this repository gets generated when the source generator runs:

```php
<?php

declare (strict_types=1);
namespace App\Repository;

final readonly class PageRepository
{
    /**
     * @return array<\App\Page>
     */
    public function findBy(?string $title = null, ?string $content = null, ?string $slug = null): array
    {
        // TODO: actually implement this method
    }
    /**
     * @return array<\App\Page>
     */
    public function findAll(): array
    {
        return $this->findBy();
    }
    public function findOneBy(?string $title = null, ?string $content = null, ?string $slug = null): ?\App\Page
    {
        return $this->findBy($title, $content, $slug)[0] ?? null;
    }
}
```

Note that the source generator would need to be more complex to actually provide a good implementation of a repository,
but my goal was to show off the parts where source generators shine; not to implement a fully working repository.
