<?php

namespace Rikudou\Tests\SourceGenerators\Trait;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

trait TemporaryDirectoryTrait
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->createTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    private function createTemporaryDirectory(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid();
        if (!is_dir($this->temporaryDirectory)) {
            mkdir($this->temporaryDirectory, 0777, true);
        }
    }

    private function removeTemporaryDirectory(): void
    {
        if (is_dir($this->temporaryDirectory)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->temporaryDirectory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    unlink($file->getRealPath());
                } else {
                    rmdir($file->getRealPath());
                }
            }

            rmdir($this->temporaryDirectory);
        }
    }
}
