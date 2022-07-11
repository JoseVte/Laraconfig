<?php

namespace DarkGhostHunter\Laraconfig\Migrator\Pipes;

use Closure;
use DarkGhostHunter\Laraconfig\Migrator\Data;
use DarkGhostHunter\Laraconfig\Eloquent\Metadata;

/**
 * @internal
 */
class LoadMetadata
{
    /**
     * Handles the Settings migration.
     */
    public function handle(Data $data, Closure $next): mixed
    {
        $data->metadata = Metadata::all()->keyBy(static fn (Metadata $metadata): string => $metadata->name);

        return $next($data);
    }
}
