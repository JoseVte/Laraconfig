<?php

namespace DarkGhostHunter\Laraconfig\Migrator\Pipes;

use Closure;
use RuntimeException;
use Illuminate\Console\OutputStyle;
use DarkGhostHunter\Laraconfig\Migrator\Data;

class EnsureSomethingToMigrate
{
    public function __construct(protected OutputStyle $output)
    {
    }

    /**
     * Handles the Settings migration.
     */
    public function handle(Data $data, Closure $next): mixed
    {
        if ($data->metadata->isEmpty() && $data->declarations->isEmpty()) {
            throw new RuntimeException('No metadata exists in the database, and no declaration exists.');
        }

        return $next($data);
    }
}
