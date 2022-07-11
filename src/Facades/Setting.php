<?php

namespace DarkGhostHunter\Laraconfig\Facades;

use Illuminate\Support\Facades\Facade;
use DarkGhostHunter\Laraconfig\Registrar\SettingRegistrar;

/**
 * @method static \Illuminate\Support\Collection|\DarkGhostHunter\Laraconfig\Eloquent\Setting[] getSettings()
 * @method static \DarkGhostHunter\Laraconfig\Registrar\Declaration name(string $name)
 */
class Setting extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return SettingRegistrar::class;
    }
}
