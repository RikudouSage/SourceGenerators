<?php


use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Script\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Rikudou\SourceGenerators\ComposerPlugin;

#[CoversClass(ComposerPlugin::class)]
final class ComposerPluginTest extends TestCase
{
    protected function tearDown(): void
    {
        if (file_exists(__DIR__ . '/Data/vendor/autoload.php')) {
            unlink(__DIR__ . '/Data/vendor/autoload.php');
        }
        if (file_exists(__DIR__ . '/Data/vendor/source-generators/__source_generators_autoloader.php')) {
            unlink(__DIR__ . '/Data/vendor/source-generators/__source_generators_autoloader.php');
            rmdir(__DIR__ . '/Data/vendor/source-generators');
        }
    }

    public function testRun()
    {
        putenv('COMPOSER=' . __DIR__ . '/Data/composer.json');
        copy(__DIR__ . '/Data/vendor/autoload_orig.php', __DIR__ . '/Data/vendor/autoload.php');

        $composer = new Composer();
        $composer->setPackage(new RootPackage('test', '1', '1'));
        $io = new NullIO();

        $instance = new ComposerPlugin();
        $instance->activate($composer, $io);

        $event = new Event(
            name: 'post-autoload',
            composer: $composer,
            io: $io
        );

        $instance->postInstall($event);
        self::assertFileExists(__DIR__ . '/Data/vendor/autoload.php');
        self::assertFileExists(__DIR__ . '/Data/vendor/source-generators/__source_generators_autoloader.php');

        self::assertStringContainsString(
            '__source_generators_autoloader.php',
            file_get_contents(__DIR__ . '/Data/vendor/autoload.php'),
        );
    }

    public function testDisabled()
    {
        $composer = new Composer();
        $composer->setPackage(new RootPackage('test', '1', '1'));
        $composer->getPackage()->setExtra([
            'source-generators' => [
                'enabled' => false,
            ]
        ]);
        $io = new NullIO();
        $event = new Event(
            name: 'post-autoload',
            composer: $composer,
            io: $io
        );

        $instance = new ComposerPlugin();
        $instance->activate($composer, $io);

        $instance->postInstall($event);
        self::assertFileDoesNotExist(__DIR__ . '/Data/vendor/autoload.php');
        self::assertFileDoesNotExist(__DIR__ . '/Data/vendor/source-generators/__source_generators_autoloader.php');
    }
}
