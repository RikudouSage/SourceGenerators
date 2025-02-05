# Advanced usage

## Manually triggering source generators

Usually the package hooks into Composer and runs your source generator automatically. If you want to run them manually
you have two options:

- trigger composer install by running `composer install`
- manually call the source generator handler

### Calling the source generators manually

The class that takes care of running the source processors is `Rikudou\SourceGenerators\Processor\SourceGeneratorProcessor`.
The easiest way to create an instance of that class is by using the `\Rikudou\SourceGenerators\Processor\SourceGeneratorProcessorFactory`
which has a static method called `fromComposerJson()` which, as you probably guessed, needs path to a composer.json file.

So, for example:

```php
<?php

use Rikudou\SourceGenerators\Processor\SourceGeneratorProcessorFactory;

$processor = SourceGeneratorProcessorFactory::fromComposerJson(__DIR__ . '/composer.json');
$processor->execute();

// if you also want to register the autoloader into composer
$processor->autoloadManager->registerAutoloader();
```

Note that fully manually constructing the processor is a little more tedious process:

```php
<?php

use Rikudou\SourceGenerators\Processor\SourceGeneratorProcessor;
use Rikudou\SourceGenerators\Extractor\Psr4Rule;

$processor = new SourceGeneratorProcessor(
    psr4Rules: [
        new Psr4Rule(namespace: 'App\\', directory: __DIR__ . '/src'),
        new Psr4Rule(namespace: 'Some\\Vendor\\', directory: __DIR__ . '/vendor/some/vendor'),
    ],
    targetDirectory: __DIR__ . '/path/to/generated/classes/directory',
    autoloadRegistrar: function (string $customAutoloaderPath) {
        assert(file_exists($customAutoloaderPath));
        // implement some custom logic to register the autoloader, like requiring it in your index, or some other
        // autoloaded file
        // note that it should be the first autoloader because it's the nature of this package to overwrite existing
        // classes
        // also note that it's an absolute path and if you'd like this function to create a portable autoloader, regardless
        // of the file location on disk, you should manually determine how to replace parts of the file path with __DIR__ or similar
    }, 
)
```

Note that you need to include all psr-4 rules, including from your dependencies, if you want the processor to be able
to traverse them.
