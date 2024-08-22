If you run this example, two class with the same content should be generated. This demonstrates using both strings
and AST for content.

```php
<?php

declare (strict_types=1);
namespace App;

final class HelloWorld1
{
    public function sayHello(): void
    {
        echo 'Hello world!';
    }
}
```

and 

```php
<?php

declare (strict_types=1);
namespace App;

final class HelloWorld2
{
    public function sayHello(): void
    {
        echo 'Hello world!';
    }
}
```
