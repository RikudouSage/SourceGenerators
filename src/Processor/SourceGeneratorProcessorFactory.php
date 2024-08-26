<?php

namespace Rikudou\SourceGenerators\Processor;

use JsonException;
use Rikudou\SourceGenerators\Exception\ExtractorException;
use Rikudou\SourceGenerators\Exception\IOException;
use Rikudou\SourceGenerators\Extractor\Psr4Rule;
use SplFileInfo;

final readonly class SourceGeneratorProcessorFactory
{
    public static function fromComposerJson(string $pathToComposer): SourceGeneratorProcessor
    {
        $file = new SplFileInfo($pathToComposer);
        if (!$file->isFile()) {
            throw new ExtractorException("The composer file could not be found: '{$pathToComposer}'");
        }

        try {
            $json = json_decode(
                file_get_contents($file->getRealPath()) ?: throw new ExtractorException("Could not read the composer.json file: '{$file->getRealPath()}'"),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $directory = dirname($file->getRealPath());

            $psr4Rules = array_map(
                fn (string $dir, string $namespace) => new Psr4Rule(namespace: $namespace, directory: "{$directory}/$dir"),
                $json['autoload']['psr-4'] ?? [],
                array_keys($json['autoload']['psr-4'] ?? [])
            );

            $vendorDirectory = $json['config']['vendor-dir'] ?? 'vendor';
            if (!str_starts_with((string) $vendorDirectory, '/')) {
                $vendorDirectory = "{$directory}/{$vendorDirectory}";
            }

            if (is_dir($vendorDirectory)) {
                foreach (glob("{$vendorDirectory}/*/*/composer.json") as $composerJson) {
                    $directory = dirname($composerJson);
                    $composerJson = json_decode(
                        file_get_contents($composerJson) ?: throw new ExtractorException("Could not read the composer.json file: '{$composerJson}'"),
                        true,
                        flags: JSON_THROW_ON_ERROR,
                    );
                    $psr4Rules = [
                        ...$psr4Rules,
                        ...array_map(
                            fn (string $dir, string $namespace) => new Psr4Rule(namespace: $namespace, directory: "{$directory}/$dir"),
                            $composerJson['autoload']['psr-4'] ?? [],
                            array_keys($composerJson['autoload']['psr-4'] ?? [])
                        ),
                    ];
                }
            }

            $autoloaderRegistrar = function (string $file) use ($vendorDirectory): void {
                $originalAutoloadFile = "{$vendorDirectory}/autoload.php";
                if (!file_exists($originalAutoloadFile)) {
                    throw new IOException('Could not find autoload.php file');
                }

                $relativeFileName = str_replace($vendorDirectory, "__DIR__ . '", $file);

                $content = "require_once {$relativeFileName}';" . PHP_EOL;
                $originalAutoloadFileContent = array_filter(file($originalAutoloadFile), fn (string $line) => (bool) trim($line));

                $lastLine = &$originalAutoloadFileContent[array_key_last($originalAutoloadFileContent)];
                $lastLine = str_replace('return ', '$result = ', $lastLine);

                assert(is_array($originalAutoloadFileContent));

                $originalAutoloadFileContent[] = $content;
                $originalAutoloadFileContent[] = 'return $result;' . PHP_EOL;

                file_put_contents($originalAutoloadFile, implode('', $originalAutoloadFileContent)) ?: throw new IOException("Could not write to file: '{$originalAutoloadFile}'");
            };

            return new SourceGeneratorProcessor($psr4Rules, $vendorDirectory . '/source-generators', $autoloaderRegistrar);
        } catch (JsonException $e) {
            throw new ExtractorException("Failed parsing the JSON file", previous: $e);
        }
    }
}
