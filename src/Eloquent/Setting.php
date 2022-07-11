<?php

namespace DarkGhostHunter\Laraconfig\Eloquent;

use Illuminate\Database\Eloquent\Model;
use DarkGhostHunter\Laraconfig\SettingsCache;
use DarkGhostHunter\Laraconfig\MorphManySettings;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Database\Eloquent\Model                                                        $user
 * @property int                                                                                        $id
 * @property array|bool|string|int|float|\Illuminate\Support\Collection|\Illuminate\Support\Carbon|null $value
 * @property bool                                                                                       $is_enabled
 * @property string                                                                                     $name       // Added by the "add-metadata" global scope.
 * @property string                                                                                     $type       // Added by the "add-metadata" global scope.
 * @property \Illuminate\Support\Carbon|\Illuminate\Support\Collection|array|string|int|float|bool|null $default    // Added by the "add-metadata" global scope.
 * @property string                                                                                     $group      // Added by the "add-metadata" global scope.
 * @property string                                                                                     $bag        // Added by the "add-metadata" global scope.
 * @property \DarkGhostHunter\Laraconfig\Eloquent\Metadata                                              $metadata
 */
class Setting extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_settings';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'value' => Casts\DynamicCasting::class,
        'default' => Casts\DynamicCasting::class,
        'is_enabled' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ['value', 'is_enabled'];

    /**
     * The attributes that should be visible in serialization.
     *
     * @var array
     */
    protected $visible = ['value', 'name', 'group', 'is_disabled'];

    /**
     * Parent bags used for scoping.
     */
    public ?array $parentBags = null;

    /**
     * Settings cache repository.
     */
    public ?SettingsCache $cache = null;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::updated(static function (Setting $setting): void {
            // Immediately after saving we will invalidate the cache of the
            // settings, and mark the cache ready to regenerate once there
            // is no more work to be done with the settings themselves.
            $setting->invalidateCache();
        });
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new Scopes\AddMetadata());
    }

    /**
     * The parent metadata.
     */
    public function metadata(): BelongsTo
    {
        return $this->belongsTo(Metadata::class, 'metadata_id');
    }

    /**
     * The user this settings belongs to.
     */
    public function user(): MorphTo
    {
        return $this->morphTo('settable');
    }

    /**
     * Fills the settings data from a Metadata model instance.
     *
     * @param \DarkGhostHunter\Laraconfig\Eloquent\Metadata $metadata
     *
     * @return $this
     */
    public function fillFromMetadata(Metadata $metadata): static
    {
        return $this->forceFill(
            $metadata->only('name', 'type', 'default', 'group', 'bag')
        )->syncOriginal();
    }

    /**
     * Sets a value into the setting and saves it immediately.
     *
     * @param bool $force when "false", it will be only set if its enabled
     *
     * @return bool "true" on success, or "false" if it's disabled
     */
    public function set(mixed $value, bool $force = true): bool
    {
        if ($force || $this->isEnabled()) {
            $this->setAttribute('value', $value)->save();
        }

        return $this->isEnabled();
    }

    /**
     * Sets a value into the setting if it's enabled.
     *
     * @return bool "true" on success, or "false" if it's disabled
     */
    public function setIfEnabled(mixed $value): bool
    {
        return $this->set($value, false);
    }

    /**
     * Reverts the setting to its default value.
     */
    public function setDefault(): void
    {
        // We will retrieve the default value if it was not retrieved.
        if (!isset($this->attributes['default'])) {
            // By setting the same attribute as original we can skip saving it.
            // We will also use the Query Builder directly to avoid the value
            // being casted, as we need it raw, and let model be set as is.
            $this->attributes['default'] =
            $this->original['default'] = $this->metadata()->getQuery()->value('default');
        }

        $this->set($this->default);
    }

    /**
     * Enables the setting.
     */
    public function enable(bool $enable = true): void
    {
        $this->update(['is_enabled' => $enable]);
    }

    /**
     * Disables the setting.
     */
    public function disable(): void
    {
        $this->enable(false);
    }

    /**
     * Check if the current setting is enabled.
     */
    public function isEnabled(): bool
    {
        return true === $this->is_enabled;
    }

    /**
     * Check if the current settings is disabled.
     */
    public function isDisabled(): bool
    {
        return !$this->isEnabled();
    }

    /**
     * Forcefully invalidates the cache from this setting.
     */
    public function invalidateCache(): void
    {
        // If an instance of the Settings Cache helper exists, we will use that.
        if ($this->cache) {
            // Invalidate the cache immediately, as is no longer representative.
            $this->cache->invalidateIfNotInvalidated();
            // Mark the cache to be regenerated once is destructed.
            $this->cache->regenerateOnExit();
        } elseif (config('laraconfig.cache.enable', false)) {
            [$morph, $id] = $this->getMorphs('settable', null, null);

            cache()
                ->store(config('laraconfig.cache.store'))
                ->forget(
                    MorphManySettings::generateKeyForModel(
                        config('laraconfig.cache.prefix'),
                        $this->getAttribute($morph),
                        $this->getAttribute($id)
                    )
                );
        }
    }
}
