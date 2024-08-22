<?php

namespace Rikudou\SourceGenerators\Autoloader;

use Closure;
use Rikudou\SourceGenerators\Exception\IOException;

final readonly class AutoloadManager
{
    public function __construct(
        private string                $targetDirectory,
        private Closure               $autoloadRegistrar,
        private TargetClassMapManager $classMapManager,
    ) {
    }

    public function registerAutoloader(): void
    {
        ($this->autoloadRegistrar)($this->getAutoloaderFilename());
    }

    public function dumpCustomAutoloader(): void
    {
        file_put_contents($this->getAutoloaderFilename(), $this->getAutoloaderString()) ?: throw new IOException("Could not write to file: '{$this->getAutoloaderFilename()}'");
    }

    private function getAutoloaderFilename(): string
    {
        return "{$this->targetDirectory}/__source_generators_autoloader.php";
    }

    private function getAutoloaderString(): string
    {
        $exportedClassMap = var_export($this->classMapManager->getMap(), true);
        $exportedClassMap = str_replace("'{$this->targetDirectory}", "__DIR__ . '", $exportedClassMap);

        return <<<EOF
        <?php
        
        \$classMap = {$exportedClassMap};
        
        spl_autoload_register(function (string \$className) use (&\$classMap) {
            if (!isset(\$classMap[\$className])) {
                return;
            }
            
            require_once(\$classMap[\$className]);
        }, prepend: true);
        EOF;
    }
}
