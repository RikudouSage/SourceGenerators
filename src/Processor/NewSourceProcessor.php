<?php

namespace Rikudou\SourceGenerators\Processor;

use PhpParser\PrettyPrinter;
use PhpParser\PrettyPrinter\Standard;
use Rikudou\SourceGenerators\Autoloader\TargetClassMapManager;
use Rikudou\SourceGenerators\Dto\ClassSource;
use Rikudou\SourceGenerators\Exception\IOException;

final readonly class NewSourceProcessor
{
    private PrettyPrinter $dumper;

    public function __construct(
        private string                $targetDirectory,
        private TargetClassMapManager $classMapManager,
    ) {
        $this->dumper = new Standard();
    }

    /**
     * @param array<ClassSource> $sources
     */
    public function process(array $sources): void
    {
        foreach ($sources as $source) {
            $fileName = $source->class . '.php';
            $filePath = "{$this->targetDirectory}/{$fileName}";
            $content = <<<EOF
            <?php

            declare(strict_types=1);
            
            EOF;

            if ($namespace = $source->namespace) {
                $content .= <<<EOF
                
                namespace {$namespace};
                EOF;
            }

            if (is_string($source->content)) {
                $content .= "\n\n" . $source->content;
            } else {
                $content .= "\n\n" . $this->dumper->prettyPrint($source->content);
            }

            $content = str_replace('%className%', $source->class, $content);

            file_put_contents($filePath, $content) ?: throw new IOException("Could not write to file: '{$filePath}'");
            $fqn = $source->class;
            if ($namespace) {
                $fqn = "{$namespace}\\{$fqn}";
            }
            $this->classMapManager->add($fqn, $filePath);
        }
    }
}
