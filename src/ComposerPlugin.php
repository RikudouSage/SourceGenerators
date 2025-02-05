<?php

namespace Rikudou\SourceGenerators;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Override;
use Rikudou\SourceGenerators\Processor\SourceGeneratorProcessor;
use Rikudou\SourceGenerators\Processor\SourceGeneratorProcessorFactory;

/**
 * @internal
 */
final class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    private ?SourceGeneratorProcessor $processor = null;

    private Composer $composer;

    /**
     * @codeCoverageIgnore
     */
    #[Override]
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
    }

    /**
     * @codeCoverageIgnore
     */
    #[Override]
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
    }

    /**
     * @codeCoverageIgnore
     */
    #[Override]
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
    }

    /**
     * @codeCoverageIgnore
     */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'postAutoloadDump',
        ];
    }

    public function postInstall(Event $event): void
    {
        $enabled = $this->composer->getPackage()->getExtra()['source-generators']['enabled'] ?? true;
        if (!$enabled) {
            return;
        }
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
