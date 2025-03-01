<?php

namespace Tests;

use Mockery;
use Exception;
use RuntimeException;
use Tests\Dummies\DummyModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Database\Eloquent\Model;
use DarkGhostHunter\Laraconfig\HasConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use DarkGhostHunter\Laraconfig\Eloquent\Setting;
use DarkGhostHunter\Laraconfig\Eloquent\Metadata;
use DarkGhostHunter\Laraconfig\SettingsCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\HigherOrderCollectionProxy;
use DarkGhostHunter\Laraconfig\Eloquent\Scopes\FilterBags;

class HasConfigTest extends BaseTestCase
{
    use RefreshDatabase;

    protected Metadata $metadata;
    protected Setting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metadata = Metadata::make()->forceFill([
            'name' => 'foo',
            'type' => 'string',
            'default' => 'bar',
            'bag' => 'users',
            'group' => 'default',
        ]);

        $this->metadata->save();

        $this->setting = Setting::make()->forceFill([
            'settable_id' => 1,
            'settable_type' => 'bar',
            'metadata_id' => 1,
        ]);

        DummyModel::forceCreate([
            'name' => 'john',
            'email' => 'john@email.com',
            'password' => '123456',
        ]);
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function testCreatesSettingsOnCreation(): void
    {
        $this->assertDatabaseCount('user_settings', 1);

        DummyModel::forceCreate([
            'name' => 'maria',
            'email' => 'maria@email.com',
            'password' => '123456',
        ]);

        $this->assertDatabaseCount('user_settings', 2);
    }

    public function testInitializesManually(): void
    {
        $model = new class() extends Model {
            use HasConfig;

            protected $table = 'users';

            public function filterBags(): array
            {
                return ['test-bag', 'users'];
            }

            public function shouldInitializeConfig(): bool
            {
                return false;
            }
        };

        $instance = $model->forceCreate([
            'name' => 'dummy',
            'email' => 'dummy@email.com',
            'password' => '123456',
        ]);

        $this->assertDatabaseMissing('user_settings', ['settable_id' => $instance->id]);

        $instance->settings()->initialize();

        $this->assertDatabaseHas('user_settings', ['settable_id' => $instance->id]);
    }

    public function testDoesntInitializesAgain(): void
    {
        $user = DummyModel::find(1);

        $user->settings->set('foo', 'quz');

        $user->settings()->initialize();

        $this->assertDatabaseCount('user_settings', 1);
        $this->assertDatabaseHas('user_settings', ['value' => 'quz']);
    }

    public function testInitializesForcefully(): void
    {
        $user = DummyModel::find(1);

        $user->settings->set('foo', 'quz');

        $this->assertDatabaseHas('user_settings', ['value' => 'quz']);

        $user->settings()->initialize(true);

        $this->assertDatabaseCount('user_settings', 1);
        $this->assertDatabaseHas('user_settings', ['value' => 'bar']);
    }

    public function testModelSetsConfig(): void
    {
        $user = DummyModel::find(1);

        $user->settings->set('foo', 'quz');

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'value' => 'quz']);
    }

    public function testModelGetConfigValue(): void
    {
        $user = DummyModel::find(1);

        static::assertEquals('bar', $user->settings->value('foo'));
    }

    public function testEnablesAndDisablesSetting(): void
    {
        $user = DummyModel::find(1);

        $user->settings->disable('foo');
        $this->assertDatabaseHas('user_settings', ['id' => 1, 'is_enabled' => false]);
        static::assertFalse($user->settings->isEnabled('foo'));
        static::assertTrue($user->settings->isDisabled('foo'));

        $user->settings->enable('foo');
        $this->assertDatabaseHas('user_settings', ['id' => 1, 'is_enabled' => true]);
        static::assertTrue($user->settings->isEnabled('foo'));
        static::assertFalse($user->settings->isDisabled('foo'));
    }

    public function testModelSetsConfigForcefully(): void
    {
        $user = DummyModel::find(1);

        $user->settings->disable('foo');
        $user->settings->set('foo', 'quz', true);

        static::assertEquals('quz', $user->settings->value('foo'));
    }

    public function testModelSetsConfigNotForcefully(): void
    {
        /** @var \DarkGhostHunter\Laraconfig\HasConfig $user */
        $user = DummyModel::find(1);

        $user->settings->disable('foo');

        $user->settings->set('foo', 'quz', false);
        static::assertEquals('bar', $user->settings->value('foo'));

        $user->settings->setIfEnabled('foo', 'quz');
        static::assertEquals('bar', $user->settings->value('foo'));

        $user->settings->setIfEnabled(['foo' => 'quz']);
        static::assertEquals('bar', $user->settings->value('foo'));

        $user->settings->enable('foo');

        $user->settings->set('foo', 'quz', false);
        static::assertEquals('quz', $user->settings->value('foo'));

        $user->settings->setIfEnabled('foo', 'bar');
        static::assertEquals('bar', $user->settings->value('foo'));

        $user->settings->setIfEnabled(['foo' => 'quz']);
        static::assertEquals('quz', $user->settings->value('foo'));
    }

    public function testModelGetsConfig(): void
    {
        $user = DummyModel::find(1);

        static::assertEquals('bar', $user->settings->value('foo'));
    }

    public function testReturnsAllSettings(): void
    {
        Metadata::forceCreate([
            'name' => 'bar',
            'type' => 'string',
            'bag' => 'users',
            'group' => 'default',
        ]);

        Setting::forceCreate([
            'settable_id' => 1,
            'settable_type' => DummyModel::class,
            'metadata_id' => 2,
        ]);

        Metadata::forceCreate([
            'name' => 'baz',
            'type' => 'string',
            'bag' => 'users',
            'group' => 'default',
        ]);

        Setting::forceCreate([
            'settable_id' => 1,
            'settable_type' => DummyModel::class,
            'metadata_id' => 3,
        ]);

        $user = DummyModel::find(1);

        static::assertCount(3, $user->settings);
        static::assertTrue($user->settings->contains('name', 'foo'));
        static::assertTrue($user->settings->contains('name', 'bar'));
        static::assertTrue($user->settings->contains('name', 'baz'));
    }

    public function testReturnsOnlySomeSettings(): void
    {
        Metadata::forceCreate([
            'name' => 'bar',
            'type' => 'string',
            'bag' => 'users',
            'group' => 'default',
        ]);

        Setting::forceCreate([
            'settable_id' => 1,
            'settable_type' => DummyModel::class,
            'metadata_id' => 2,
        ]);

        Metadata::forceCreate([
            'name' => 'baz',
            'type' => 'string',
            'bag' => 'users',
            'group' => 'default',
        ]);

        Setting::forceCreate([
            'settable_id' => 1,
            'settable_type' => DummyModel::class,
            'metadata_id' => 3,
        ]);

        $user = DummyModel::find(1);

        $settings = $user->settings->only('bar', 'baz');

        static::assertCount(2, $settings);
        static::assertTrue($settings->contains('name', 'bar'));
        static::assertTrue($settings->contains('name', 'baz'));
    }

    public function testReturnsExceptSomeSettings(): void
    {
        Metadata::forceCreate([
            'name' => 'bar',
            'type' => 'string',
            'bag' => 'users',
            'group' => 'default',
        ]);

        Setting::forceCreate([
            'settable_id' => 1,
            'settable_type' => DummyModel::class,
            'metadata_id' => 2,
        ]);

        Metadata::forceCreate([
            'name' => 'baz',
            'type' => 'string',
            'bag' => 'users',
            'group' => 'default',
        ]);

        Setting::forceCreate([
            'settable_id' => 1,
            'settable_type' => DummyModel::class,
            'metadata_id' => 3,
        ]);

        $user = DummyModel::find(1);

        $settings = $user->settings->except('bar', 'baz');

        static::assertCount(1, $settings);
        static::assertTrue($settings->contains('name', 'foo'));
    }

    public function testCheckHasSettingName(): void
    {
        $user = DummyModel::find(1);

        static::assertTrue($user->settings->has('foo'));
        static::assertFalse($user->settings->has('bar'));
    }

    public function testChecksValueSameAsSetting(): void
    {
        $user = DummyModel::find(1);

        static::assertTrue($user->settings->is('foo', 'bar'));
        static::assertFalse($user->settings->is('foo', 'not bar'));
    }

    public function testModelSetsBag(): void
    {
        $user = DummyModel::find(1);

        static::assertEquals(['users'], $user->settings()->bags());

        $model = new class() extends Model {
            use HasConfig;

            protected $table = 'users';

            public function filterBags(): array
            {
                return ['test-bag', 'users'];
            }
        };

        static::assertEquals(['test-bag', 'users'], $model->settings()->bags());
    }

    public function testFiltersSettingsByModelBags(): void
    {
        Metadata::forceCreate([
            'name' => 'bar',
            'type' => 'string',
            'group' => 'default',
            'bag' => 'test-bag',
        ]);

        Metadata::forceCreate([
            'name' => 'baz',
            'type' => 'string',
            'group' => 'default',
            'bag' => 'test-bag',
        ]);

        $model = new class() extends Model {
            use HasConfig;

            protected $table = 'users';

            public function filterBags(): string
            {
                return 'test-bag';
            }
        };

        /** @var \DarkGhostHunter\Laraconfig\HasConfig $instance */
        $instance = $model->forceCreate([
            'name' => 'dummy',
            'email' => 'dummy@email.com',
            'password' => '123456',
        ]);

        static::assertCount(2, $instance->settings);
    }

    public function testSetsDefault(): void
    {
        $user = DummyModel::find(1);

        $user->settings->set('foo', 'quz');

        $user->settings->setDefault('foo');

        static::assertEquals('bar', $user->settings->value('foo'));
    }

    public function testSetsDefaultFromDatabase(): void
    {
        /** @var \DarkGhostHunter\Laraconfig\Eloquent\Setting $setting */
        $setting = Setting::find(1);

        $setting->setRawAttributes(['default' => null])->syncOriginal();

        $setting->setDefault();

        static::assertEquals('bar', Setting::find(1)->value);
    }

    public function testCheckIfNull(): void
    {
        $user = DummyModel::find(1);

        $user->settings->set('foo', 'quz');

        static::assertFalse($user->settings->isNull('foo'));

        $user->settings->set('foo', null);

        static::assertTrue($user->settings->isNull('foo'));
    }

    public function testDoesntInvalidatesCacheIfCacheDisabled(): void
    {
        $this->mock(Factory::class)->shouldNotReceive('forget');

        DummyModel::find(1)->settings->cache?->invalidateIfNotInvalidated();
    }

    public function testCanInvalidateCacheIfEnabled(): void
    {
        config()->set('laraconfig.cache.enable', true);

        $cache = $this->mock(Repository::class);

        $this->mock(Factory::class)
            ->shouldReceive('store')
            ->with(null)
            ->andReturn($cache);

        $cache->shouldReceive('get')
            ->with('laraconfig|'.DummyModel::class.'|1')
            ->andReturnNull();

        $cache->shouldReceive('forget')
            ->with('laraconfig|'.DummyModel::class.'|1');

        $cache->shouldReceive('forget')
            ->with('laraconfig|'.DummyModel::class.'|1:time');

        $cache->shouldNotReceive('setMultiple');

        $user = DummyModel::find(1);

        $user->settings->invalidateIfNotInvalidated();

        static::assertFalse($user->settings->regeneratesOnExit);
    }

    public function testDoesntRegeneratesCacheIfCacheDisabled(): void
    {
        $this->mock(Factory::class)->shouldNotReceive('store');

        DummyModel::find(1)->settings->regenerate();
    }

    public function testShouldRegenerateCacheIfCacheEnabled(): void
    {
        config()->set('laraconfig.cache.enable', true);

        $cache = $this->mock(Repository::class);

        $this->mock(Factory::class)
            ->shouldReceive('store')
            ->with(null)
            ->andReturn($cache);

        $cache->shouldReceive('forget')
            ->with('laraconfig|'.DummyModel::class.'|1');

        $cache->shouldReceive('get')
            ->with('laraconfig|'.DummyModel::class.'|1')
            ->andReturn(new SettingsCollection([(new Setting())->forceFill([
                'name' => 'foo',
            ])]));

        $cache->shouldReceive('get')
            ->with('laraconfig|'.DummyModel::class.'|1:time')
            ->andReturn(now()->subMinute());

        $cache->shouldReceive('set')
            ->with('laraconfig|'.DummyModel::class.'|1', Mockery::type(Collection::class), 60 * 60 * 3)
            ->andReturns();

        $cache->shouldReceive('setMultiple')
            ->withArgs(function ($array, $ttl) {
                static::assertArrayHasKey('laraconfig|'.DummyModel::class.'|1', $array);
                static::assertArrayHasKey('laraconfig|'.DummyModel::class.'|1:time', $array);
                static::assertEquals(60 * 60 * 3, $ttl);

                return true;
            })
            ->andReturns();

        $user = DummyModel::find(1);

        $user->settings->regenerate();

        static::assertFalse($user->settings->regeneratesOnExit);
    }

    public function testShouldNotRegenerateCacheIfIsNotFresher(): void
    {
        config()->set('laraconfig.cache.enable', true);

        $cache = $this->mock(Repository::class);

        $this->mock(Factory::class)
            ->shouldReceive('store')
            ->with(null)
            ->andReturn($cache);

        $cache->shouldReceive('forget')
            ->with('laraconfig|'.DummyModel::class.'|1');

        $cache->shouldReceive('get')
            ->with('laraconfig|'.DummyModel::class.'|1')
            ->andReturn(new SettingsCollection([(new Setting())->forceFill([
                'name' => 'foo',
            ])]));

        $cache->shouldReceive('get')
            ->with('laraconfig|'.DummyModel::class.'|1:time')
            ->andReturn(now()->addMinute());

        $cache->shouldNotReceive('set');
        $cache->shouldNotReceive('setMultiple');

        $user = DummyModel::find(1);

        $user->settings->regenerate();

        static::assertFalse($user->settings->regeneratesOnExit);
    }

    public function testDoesntInvalidatesCacheOnSaveIfCacheDisabled(): void
    {
        $this->mock(Factory::class)->shouldNotReceive('store');

        $user = DummyModel::find(1);

        $user->settings->set('foo', 'quz');
        $user->settings->disable('foo');
    }

    public function testInvalidatesCacheOnlyOnceOnSetIfCacheEnabled(): void
    {
        config()->set('laraconfig.cache.enable', true);

        $cache = $this->mock(Repository::class);

        $this->mock(Factory::class)
            ->shouldReceive('store')
            ->with(null)
            ->once()
            ->andReturn($cache);

        $cache->shouldReceive('get')
            ->with('laraconfig|'.DummyModel::class.'|1')
            ->once()
            ->andReturnNull();

        $cache->shouldReceive('forget')
            ->with('laraconfig|'.DummyModel::class.'|1')
            ->once();

        $cache->shouldReceive('forget')
            ->with('laraconfig|'.DummyModel::class.'|1:time')
            ->once();

        $cache->shouldReceive('get')
            ->with('laraconfig|'.DummyModel::class.'|1:time')
            ->once()
            ->andReturnNull();

        $cache->shouldReceive('setMultiple')
            ->withArgs(function ($array, $ttl) {
                static::assertArrayHasKey('laraconfig|'.DummyModel::class.'|1', $array);
                static::assertArrayHasKey('laraconfig|'.DummyModel::class.'|1:time', $array);
                static::assertEquals(60 * 60 * 3, $ttl);

                return true;
            })
            ->once();

        $user = DummyModel::find(1);

        static::assertFalse($user->settings->regeneratesOnExit);

        $user->settings->set('foo', 'quz');
        $user->settings->set('foo', 'bar');

        static::assertTrue($user->settings->regeneratesOnExit);

        $user->settings->regenerate();

        $user->settings->regeneratesOnExit = false;
    }

    public function testInvalidatesCacheOnlyOnceOnDisableIfCacheEnabled(): void
    {
        config()->set('laraconfig.cache.enable', true);

        $cache = $this->mock(Repository::class);

        $this->mock(Factory::class)
            ->shouldReceive('store')
            ->with(null)
            ->andReturn($cache);

        $cache->shouldReceive('get')
            ->with('laraconfig|'.DummyModel::class.'|1')
            ->once();

        $cache->shouldReceive('forget')
            ->with('laraconfig|'.DummyModel::class.'|1')
            ->once();

        $cache->shouldReceive('forget')
            ->with('laraconfig|'.DummyModel::class.'|1:time')
            ->once();

        $cache->shouldReceive('get')
            ->with('laraconfig|'.DummyModel::class.'|1:time')
            ->once();

        $cache->shouldReceive('setMultiple')
            ->withArgs(function ($array, $ttl) {
                static::assertArrayHasKey('laraconfig|'.DummyModel::class.'|1', $array);
                static::assertArrayHasKey('laraconfig|'.DummyModel::class.'|1:time', $array);
                static::assertEquals(60 * 60 * 3, $ttl);

                return true;
            })
            ->once();

        $user = DummyModel::find(1);

        static::assertFalse($user->settings->regeneratesOnExit);

        $user->settings->disable('foo');
        $user->settings->enable('foo');

        static::assertTrue($user->settings->regeneratesOnExit);

        $user->settings->regenerate();

        $user->settings->regeneratesOnExit = false;
    }

    public function testSavesAndRetrievesSettingsFromCache(): void
    {
        Cache::store('file')->forget('laraconfig|'.DummyModel::class.'|1');

        config()->set('laraconfig.cache.enable', true);
        config()->set('laraconfig.cache.store', 'file');

        $user = DummyModel::find(1);

        $user->settings->set('foo', 'quz');

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'value' => 'quz']);

        $user->settings->regenerate(true);

        $settings = Cache::store('file')->get('laraconfig|'.DummyModel::class.'|1');

        $setting = $settings->firstWhere('name', 'foo');

        static::assertNull($setting->laraconfig);
        static::assertSame('quz', $setting->value);
    }

    public function testGroupsSettings(): void
    {
        Setting::forceCreate([
            'settable_type' => DummyModel::class,
            'settable_id' => 1,
            'metadata_id' => Metadata::forceCreate([
                'name' => 'baz',
                'type' => 'string',
                'bag' => 'users',
                'group' => 'default',
            ])->id,
        ]);

        Setting::forceCreate([
            'settable_type' => DummyModel::class,
            'settable_id' => 1,
            'metadata_id' => Metadata::forceCreate([
                'name' => 'quz',
                'type' => 'string',
                'bag' => 'users',
                'group' => 'test-default',
            ])->id,
        ]);

        Setting::forceCreate([
            'settable_type' => DummyModel::class,
            'settable_id' => 1,
            'metadata_id' => Metadata::forceCreate([
                'name' => 'qux',
                'type' => 'string',
                'bag' => 'users',
                'group' => 'test-default',
            ])->id,
        ]);

        $user = DummyModel::find(1);

        $groups = $user->settings->groups();

        static::assertArrayHasKey('default', $groups);
        static::assertCount(2, $groups['default']);
        static::assertArrayHasKey('test-default', $groups);
        static::assertCount(2, $groups['test-default']);
    }

    public function testFiltersBags(): void
    {
        Metadata::forceCreate([
            'name' => 'baz',
            'type' => 'string',
            'bag' => 'users',
            'group' => 'default',
        ]);

        Metadata::forceCreate([
            'name' => 'quz',
            'type' => 'string',
            'bag' => 'test-bag',
            'group' => 'test-default',
        ]);

        Metadata::forceCreate([
            'name' => 'qux',
            'type' => 'string',
            'bag' => 'test-bag',
            'group' => 'test-default',
        ]);

        $user = new class() extends Model {
            use HasConfig;

            protected $table = 'users';

            public function filterBags(): array
            {
                return ['test-bag'];
            }
        };

        $user->forceFill([
            'name' => 'maria',
            'email' => 'maria@mail.com',
            'password' => '123456',
        ])->save();

        $settings = $user->settings->all();

        static::assertCount(2, $settings);

        foreach ($settings as $setting) {
            static::assertEquals('test-bag', $setting->bag);
        }

        $this->assertDatabaseHas('user_settings', ['id' => 4, 'metadata_id' => 3]);
        $this->assertDatabaseHas('user_settings', ['id' => 5, 'metadata_id' => 4]);
    }

    public function testCacheAvoidsDataRaces(): void
    {
        config()->set('laraconfig.cache.enable', true);
        config()->set('laraconfig.cache.automatic', false);

        $user_alpha = DummyModel::find(1);

        $user_alpha->settings->set('foo', 'baz');

        $user_beta = DummyModel::find(1);

        $user_beta->settings->set('foo', 'qux');

        $user_beta->settings->regenerate();

        $user_alpha->settings->regenerate();

        static::assertEquals('qux', cache()->get('laraconfig|'.DummyModel::class.'|1')->get('foo')->value);
    }

    public function testChecksSettingsHasKey(): void
    {
        $user = DummyModel::find(1);

        static::assertTrue(isset($user->settings->foo));
        static::assertFalse(isset($user->settings->bar));
    }

    public function testSetsValueDynamically(): void
    {
        $user = DummyModel::find(1);

        $user->settings->foo = 'quz';

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'value' => 'quz']);
    }

    public function testSetsDoesntSetsPropertyDynamicallyIntoCollection(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The setting [invalid] doesn't exist.");

        $user = DummyModel::find(1);

        $user->settings->invalid = 'quz';

        $user->settings->invalid;
    }

    public function testGetsValueDynamically(): void
    {
        $user = DummyModel::find(1);

        static::assertEquals('bar', $user->settings->foo);
    }

    public function testExceptionWhenDynamicGetDoesntExists(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Property [invalid] does not exist on this collection instance');

        $user = DummyModel::find(1);

        $user->settings->invalid;
    }

    public function testGetAllowsPassToHigherOrderProxy(): void
    {
        $user = DummyModel::find(1);

        static::assertInstanceOf(HigherOrderCollectionProxy::class, $user->settings->map);
    }

    public function testDeletesSettingsWhenModelDeletesItself(): void
    {
        DummyModel::find(1)->delete();

        $this->assertDatabaseMissing('user_settings', ['settable_id' => 1]);
    }

    public function testDeletesSettingsWhenModelForceDeletesItself(): void
    {
        Metadata::forceCreate([
            'name' => 'bar',
            'type' => 'string',
            'default' => 'quz',
            'bag' => 'test-users',
            'group' => 'default',
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        $user = new class() extends Model {
            use SoftDeletes;
            use HasConfig;
            protected $table = 'users';
            protected $attributes = [
                'name' => 'john',
                'email' => 'email@email.com',
                'password' => '123456',
            ];
        };

        $user->save();

        $this->assertDatabaseHas('user_settings', ['settable_id' => 2]);

        $user->delete();

        $this->assertDatabaseHas('user_settings', ['settable_id' => 2]);

        $user->restore();

        $this->assertDatabaseHas('user_settings', ['settable_id' => 2]);

        $user->forceDelete();

        $this->assertDatabaseMissing('user_settings', ['settable_id' => 2]);
    }

    public function testAllowsForRemovingBagsFilterOnQuery(): void
    {
        Setting::forceCreate([
            'value' => 'quz',
            'settable_id' => 1,
            'settable_type' => (new DummyModel())->getMorphClass(),
            'metadata_id' => Metadata::forceCreate([
                'name' => 'bar',
                'type' => 'string',
                'default' => 'quz',
                'bag' => 'test-users',
                'group' => 'default',
            ])->getKey(),
        ]);

        $user = DummyModel::find(1);

        $settings = $user->settings()->withoutGlobalScope(FilterBags::class)->get();

        static::assertCount(2, $settings);
    }

    public function testAllowsToDisableBagFilter(): void
    {
        Metadata::forceCreate([
            'name' => 'bar',
            'type' => 'string',
            'default' => 'quz',
            'bag' => 'test-users',
            'group' => 'default',
        ]);

        $user = new class() extends Model {
            use SoftDeletes;
            use HasConfig;
            protected $table = 'users';
            protected $attributes = [
                'name' => 'john',
                'email' => 'email@email.com',
                'password' => '123456',
            ];

            public function filterBags()
            {
                return [];
            }
        };

        $user->save();

        $settings = $user->settings()->get();

        static::assertCount(2, $settings);
    }
}
