<?php

namespace Tests\Eloquent\Scopes;

use Tests\BaseTestCase;
use Tests\Dummies\DummyModel;
use Illuminate\Database\Eloquent\Model;
use DarkGhostHunter\Laraconfig\HasConfig;
use DarkGhostHunter\Laraconfig\Eloquent\Setting;
use DarkGhostHunter\Laraconfig\Eloquent\Metadata;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FilterBySettingTest extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
    }

    protected function createSettingsForUser(Model $model): void
    {
        Setting::forceCreate([
            'value' => 'foo-value',
            'settable_type' => $model->getMorphClass(),
            'settable_id' => $model->getKey(),
            'metadata_id' => Metadata::forceCreate([
                'name' => 'foo',
                'type' => 'string',
                'group' => 'default',
                'bag' => 'users',
            ])->id,
        ]);

        Setting::forceCreate([
            'value' => 'baz-value',
            'settable_type' => $model->getMorphClass(),
            'settable_id' => $model->getKey(),
            'metadata_id' => Metadata::forceCreate([
                'name' => 'baz',
                'type' => 'string',
                'group' => 'default',
                'bag' => 'users',
            ])->id,
        ]);

        Setting::forceCreate([
            'value' => 'quz-value',
            'settable_type' => $model->getMorphClass(),
            'settable_id' => $model->getKey(),
            'metadata_id' => Metadata::forceCreate([
                'name' => 'quz',
                'type' => 'string',
                'group' => 'default',
                'bag' => 'test-users',
            ])->id,
        ]);

        Setting::forceCreate([
            'value' => 'qux-value',
            'settable_type' => $model->getMorphClass(),
            'settable_id' => $model->getKey(),
            'metadata_id' => Metadata::forceCreate([
                'name' => 'qux',
                'type' => 'string',
                'group' => 'default',
                'bag' => 'test-users',
            ])->id,
        ]);
    }

    public function testFiltersUserByConfig(): void
    {
        $user = DummyModel::make()->forceFill([
            'name' => 'john',
            'email' => 'john@email.com',
            'password' => '123456',
        ]);

        $user->saveQuietly();

        $this->createSettingsForUser($user);

        $users = DummyModel::whereConfig('foo', 'foo-value')->first();

        static::assertEquals(1, $users->getKey());

        $users = DummyModel::whereConfig([
            'foo' => 'foo-value',
            'baz' => 'baz-value',
        ])->get();

        static::assertCount(1, $users);

        $users = DummyModel::whereConfig('quz', 'quz-value')->first();

        static::assertNull($users);

        $users = DummyModel::whereConfig([
            'quz' => 'quz-value',
            'qux' => 'qux-value',
        ])->get();

        static::assertCount(0, $users);
    }

    public function testFiltersBagsOfUserOnQuery(): void
    {
        $user = new class() extends Model {
            use HasConfig;
            protected $attributes = [
                'name' => 'john',
                'email' => 'john@email.com',
                'password' => '123456',
            ];
            protected $table = 'users';

            public function getMorphClass()
            {
                return 'morph-test';
            }

            public function filterBags()
            {
                return ['test-users'];
            }
        };

        $user->saveQuietly();

        $this->createSettingsForUser($user);

        $users = $user->newQuery()->whereConfig('foo', 'foo-value')->first();

        static::assertNull($users);

        $users = $user->newQuery()->whereConfig([
            'foo' => 'foo-value',
            'baz' => 'baz-value',
        ])->get();

        static::assertCount(0, $users);

        $users = $user->newQuery()->whereConfig('quz', 'quz-value')->first();

        static::assertNotNull($users);

        $users = $user->newQuery()->whereConfig([
            'quz' => 'quz-value',
            'qux' => 'qux-value',
        ])->get();

        static::assertCount(1, $users);
    }

    public function testAcceptsWhereWithOperatorAndOr(): void
    {
        $user = DummyModel::make()->forceFill([
            'name' => 'john',
            'email' => 'john@email.com',
            'password' => '123456',
        ]);

        $user->saveQuietly();

        $this->createSettingsForUser($user);

        Metadata::query()->whereKey(1)->update([
            'type' => Metadata::TYPE_INTEGER,
        ]);

        Setting::query()->whereKey(1)->update([
            'value' => 2,
        ]);

        static::assertCount(1, DummyModel::whereConfig('foo', '>', 1)->get());
        static::assertCount(0, DummyModel::whereConfig('foo', '<', 1)->get());

        static::assertCount(1, DummyModel::whereConfig('foo', '>', 1)->orWhereConfig('foo', '<', 0)->get());
        static::assertCount(0, DummyModel::whereConfig('foo', '>', 2)->orWhereConfig('foo', '<', 0)->get());
    }
}
