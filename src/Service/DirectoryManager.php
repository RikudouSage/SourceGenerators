<?php

namespace Rikudou\SourceGenerators\Service;

use Rikudou\SourceGenerators\Exception\IOException;

final readonly class DirectoryManager
{
    public function __construct(
        private string $targetDirectory,
    ) {
    }

    public function prepare(): void
    {
        $this->create();
        $this->cleanup();
    }

    private function create(): void
    {
        if (!is_dir($this->targetDirectory) && !mkdir($this->targetDirectory, 0755, true)) {
            throw new IOException("Could not create target directory: '{$this->targetDirectory}'");
        }
    }

    private function cleanup(): void
    {
        foreach (glob("{$this->targetDirectory}/*.*") as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }
    }
}
