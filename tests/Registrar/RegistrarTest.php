<?php

namespace Tests\Registrar;

use Tests\BaseTestCase;
use Illuminate\Support\Collection;
use DarkGhostHunter\Laraconfig\Facades\Setting;
use DarkGhostHunter\Laraconfig\Registrar\SettingRegistrar;

class RegistrarTest extends BaseTestCase
{
    protected SettingRegistrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrar = $this->app->make(SettingRegistrar::class);
    }

    public function testRegistersDefaultDeclaration(): void
    {
        Setting::name('foo');

        static::assertNotEmpty($this->registrar->getDeclarations());

        $declaration = $this->registrar->getDeclarations()->first();

        static::assertEquals('string', $declaration->type);
        static::assertNull($declaration->default);
        static::assertTrue($declaration->enabled);
        static::assertEquals('default', $declaration->group);
        static::assertNull($declaration->from);
        static::assertNull($declaration->using);
        static::assertEquals('foo', $declaration->name);
        static::assertEquals('users', $declaration->bag);
    }

    public function testRegistersDeclarationsTypes(): void
    {
        $collection = new Collection([
            'array' => Setting::name('array')->array(),
            'boolean' => Setting::name('boolean')->boolean(),
            'collection' => Setting::name('collection')->collection(),
            'datetime' => Setting::name('datetime')->datetime(),
            'float' => Setting::name('float')->float(),
            'integer' => Setting::name('integer')->integer(),
            'string' => Setting::name('string')->string(),
        ]);

        static::assertEquals($collection, $this->registrar->getDeclarations());
    }

    public function testRegistersDeclarationDefault(): void
    {
        Setting::name('foo')->datetime()->default(today());

        static::assertEquals(today(), $this->registrar->getDeclarations()->first()->default);
    }

    public function testRegistersDeclarationDisabled(): void
    {
        Setting::name('foo')->disabled();
        Setting::name('bar')->disabled(false);

        static::assertFalse($this->registrar->getDeclarations()->get('foo')->enabled);
        static::assertFalse($this->registrar->getDeclarations()->get('bar')->enabled);
    }

    public function testRegistersDeclarationGroup(): void
    {
        Setting::name('foo')->group('baz');
        Setting::name('bar')->group('qux');

        static::assertEquals('baz', $this->registrar->getDeclarations()->get('foo')->group);
        static::assertEquals('qux', $this->registrar->getDeclarations()->get('bar')->group);
    }

    public function testRegistersDeclarationBag(): void
    {
        Setting::name('foo')->bag('baz');
        Setting::name('bar')->bag('qux');

        static::assertEquals('baz', $this->registrar->getDeclarations()->get('foo')->bag);
        static::assertEquals('qux', $this->registrar->getDeclarations()->get('bar')->bag);
    }

    public function testRegistersDeclarationProcedure(): void
    {
        Setting::name('foo')->using(fn () => true);

        static::assertEquals(fn () => true, $this->registrar->getDeclarations()->get('foo')->using);
    }

    public function testRegistersMigrable(): void
    {
        Setting::name('foo')->from('baz');

        static::assertEquals('baz', $this->registrar->getMigrable()->get('foo')->from);
    }

    public function testRegistersMigrableWithProcedure(): void
    {
        Setting::name('foo')->from('baz')->using(fn () => true);

        static::assertEquals('baz', $this->registrar->getMigrable()->get('foo')->from);
        static::assertEquals(fn () => true, $this->registrar->getMigrable()->get('foo')->using);
    }
}
