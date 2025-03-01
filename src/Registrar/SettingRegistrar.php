<?php

namespace DarkGhostHunter\Laraconfig\Registrar;

use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

/**
 * @internal
 */
class SettingRegistrar
{
    /**
     * Manifest directory.
     *
     * @var string
     */
    protected const MANIFEST_DIR = 'settings';

    /**
     * If the manifests has loaded.
     */
    protected bool $manifestsLoaded = false;

    /**
     * Manifest path.
     */
    protected string $manifestsPath;

    /**
     * SettingCollection constructor.
     *
     * @param \Illuminate\Contracts\Config\Repository      $config
     * @param \Illuminate\Support\Collection               $declarations
     * @param \Illuminate\Support\Collection               $migrations
     * @param \Illuminate\Filesystem\Filesystem            $filesystem
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct(
        protected Repository $config,
        protected Collection $declarations,
        protected Collection $migrations,
        protected Filesystem $filesystem,
        protected Application $app
    ) {
        $this->manifestsPath = $this->app->basePath(static::MANIFEST_DIR);
    }

    /**
     * Load the declarations from the manifests.
     */
    public function loadDeclarations(): void
    {
        // IF the directory doesn't exist, we won't bulge with reading files.
        if ($this->filesystem->exists($this->app->basePath('settings'))) {
            $files = $this->filesystem->allFiles($this->manifestsPath);

            $this->manifestsLoaded = !empty($files);

            foreach ($files as $file) {
                require $file->getPathname();
            }
        }
    }

    /**
     * Returns the settings collection.
     */
    public function getDeclarations(): Collection
    {
        return $this->declarations;
    }

    /**
     * Returns a collection of declaration that migrates to another.
     */
    public function getMigrable(): Collection
    {
        return $this->getDeclarations()
            ->filter(static fn (Declaration $declaration): bool => null !== $declaration->from);
    }

    /**
     * Creates a new declaration.
     */
    public function name(string $name): Declaration
    {
        $this->declarations->put($name, $declaration = new Declaration($name, $this->config->get('laraconfig.default')));

        return $declaration;
    }
}
