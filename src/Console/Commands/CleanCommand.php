<?php

namespace DarkGhostHunter\Laraconfig\Console\Commands;

use Illuminate\Console\Command;
use DarkGhostHunter\Laraconfig\Eloquent\Setting;

/**
 * @internal
 */
class CleanCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'settings:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes orphaned settings without users or metadata.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("Deleted {$this->deleteOrphanedSettings()} orphaned settings.");

        return 0;
    }

    /**
     * Deletes orphaned settings.
     */
    protected function deleteOrphanedSettings(): int
    {
        return Setting::query()
            ->withoutGlobalScopes()
            ->doesntHave('user')
            ->orDoesntHave('metadata')
            ->delete();
    }
}
