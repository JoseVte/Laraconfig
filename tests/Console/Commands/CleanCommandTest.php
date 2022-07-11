<?php

namespace Tests\Console\Commands;

use Tests\BaseTestCase;
use Tests\Dummies\DummyModel;
use Illuminate\Support\Facades\DB;
use DarkGhostHunter\Laraconfig\Eloquent\Setting;
use DarkGhostHunter\Laraconfig\Eloquent\Metadata;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CleanCommandTest extends BaseTestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        DB::table('users')->insert([
            [
                'name' => 'charlie',
                'email' => 'charlie@email.com',
                'password' => '123456',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
    }

    public function testCleansOrphanedSettings(): void
    {
        Metadata::forceCreate([
            'name' => 'foo',
            'type' => 'string',
            'default' => 'foo-value',
            'bag' => 'users',
            'group' => 'default',
        ]);

        // Not orphaned.
        Setting::forceCreate([
            'metadata_id' => 1,
            'settable_type' => DummyModel::class,
            'settable_id' => 1,
        ]);

        // Orphaned metadata, on user.
        Setting::forceCreate([
            'metadata_id' => 99,
            'settable_type' => DummyModel::class,
            'settable_id' => 1,
        ]);

        // Orphaned user, on metadata
        Setting::forceCreate([
            'metadata_id' => 1,
            'settable_type' => DummyModel::class,
            'settable_id' => 99,
        ]);

        // Totally orphaned
        Setting::forceCreate([
            'metadata_id' => 99,
            'settable_type' => DummyModel::class,
            'settable_id' => 99,
        ]);

        $this->artisan('settings:clean')
            ->expectsOutput('Deleted 3 orphaned settings.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('user_settings', ['metadata_id' => 1, 'settable_id' => 1]);
        $this->assertDatabaseMissing('user_settings', ['metadata_id' => 99, 'settable_id' => 1]);
        $this->assertDatabaseMissing('user_settings', ['metadata_id' => 1, 'settable_id' => 99]);
        $this->assertDatabaseMissing('user_settings', ['metadata_id' => 99, 'settable_id' => 99]);
    }
}
