<?php

namespace DarkGhostHunter\Laraconfig\Migrator;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * @internal
 */
class Data
{
    /**
     * Database Metadata.
     *
     * @var \Illuminate\Database\Eloquent\Collection|\DarkGhostHunter\Laraconfig\Eloquent\Metadata[]
     */
    public EloquentCollection $metadata;

    /**
     * Declarations.
     *
     * @var \Illuminate\Support\Collection|\DarkGhostHunter\Laraconfig\Registrar\Declaration[]
     */
    public Collection $declarations;

    /**
     * Models to check for bags.
     *
     * @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model[]
     */
    public Collection $models;

    /**
     * If the cache should be invalidated on settings changes.
     */
    public bool $invalidateCache = false;

    /**
     * Invalidate the cache through the models instead of the settings.
     */
    public bool $useModels = false;

    /**
     * Data constructor.
     */
    public function __construct()
    {
        $this->models = new Collection();
    }
}
