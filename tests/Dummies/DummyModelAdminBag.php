<?php

namespace Tests\Dummies;

use Illuminate\Database\Eloquent\Model;
use DarkGhostHunter\Laraconfig\HasConfig;

class DummyModelAdminBag extends Model
{
    use HasConfig;

    protected $table = 'users';

    protected function getSettingBags(): string|array
    {
        return ['admins'];
    }
}
