<?php

namespace Tests\Console\Commands;

use Tests\BaseTestCase;
use Illuminate\Filesystem\Filesystem;

class PublishCommandTest extends BaseTestCase
{
    protected Filesystem $filesystem;

    public function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
    }

    public function testAddsSampleFileIntoSettings(): void
    {
        $this->artisan('settings:publish')
            ->expectsOutput("Manifest published. Check it at: {$this->app->basePath('settings/users.php')}")
            ->assertExitCode(0);

        static::assertFileExists($this->app->basePath('settings/users.php'));
    }

    public function testConfirmsManifestReplace(): void
    {
        $this->filesystem->ensureDirectoryExists($this->app->basePath('settings'));
        $this->filesystem->put($this->app->basePath('settings/users.php'), '');

        $this->artisan('settings:publish')
            ->expectsConfirmation('A manifest file already exists. Overwrite?')
            ->assertExitCode(0);

        static::assertFileExists($this->app->basePath('settings/users.php'));
        static::assertStringEqualsFile(
            $this->app->basePath('settings/users.php'),
            ''
        );
    }

    public function testReplacesManifestOnceConfirmed(): void
    {
        $this->filesystem->ensureDirectoryExists($this->app->basePath('settings'));
        $this->filesystem->put($this->app->basePath('settings/users.php'), '');

        $this->artisan('settings:publish')
            ->expectsConfirmation('A manifest file already exists. Overwrite?', 'yes')
            ->expectsOutput("Manifest published. Check it at: {$this->app->basePath('settings/users.php')}")
            ->assertExitCode(0);

        static::assertFileExists($this->app->basePath('settings/users.php'));

        sleep(10);

        static::assertFileEquals(
            __DIR__.'/../../../stubs/users.php',
            $this->app->basePath('settings/users.php')
        );
    }

    public function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->app->basePath('settings'));

        parent::tearDown();
    }
}
