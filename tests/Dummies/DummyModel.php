<?php

namespace Tests\Dummies;

use Illuminate\Database\Eloquent\Model;
use DarkGhostHunter\Laraconfig\HasConfig;

/**
 * @method static static find($int)
 */
class DummyModel extends Model
{
    use HasConfig;

    protected $table = 'users';
}
