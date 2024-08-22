<?php

namespace Rikudou\SourceGenerators;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Rikudou\SourceGenerators\Processor\SourceGeneratorProcessor;
use Rikudou\SourceGenerators\Processor\SourceGeneratorProcessorFactory;

final class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    private ?SourceGeneratorProcessor $processor = null;

    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'postAutoloadDump',
        ];
    }

    public function postAutoloadDump(Event $event): void
    {
        $processor = $this->getProcessor();
        $processor->execute();
        $processor->autoloadManager->registerAutoloader();
    }

    private function getProcessor(): SourceGeneratorProcessor
    {
        $this->processor ??= SourceGeneratorProcessorFactory::fromComposerJson(Factory::getComposerFile());
        return $this->processor;
    }
}