<?php

namespace DarkGhostHunter\Laraconfig;

use Serializable;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Config\Repository as Config;

class SettingsCache implements Serializable
{
    /**
     * The collection of settings to persist.
     *
     * @var \DarkGhostHunter\Laraconfig\SettingsCollection|null
     */
    protected ?SettingsCollection $settings = null;

    /**
     * If the cache was already invalidated (to not do it again).
     */
    protected ?Carbon $invalidatedAt = null;

    /**
     * SettingsCache constructor.
     *
     * @param \Illuminate\Contracts\Cache\Repository $cache
     * @param string                                 $key
     * @param int                                    $ttl
     * @param bool                                   $automaticRegeneration
     */
    public function __construct(
        protected Repository $cache,
        protected string $key,
        protected int $ttl,
        protected bool $automaticRegeneration = false
    ) {
    }

    /**
     * Set the settings collection to persist.
     *
     * @param \DarkGhostHunter\Laraconfig\SettingsCollection $settings
     *
     * @return \DarkGhostHunter\Laraconfig\SettingsCache
     */
    public function setSettings(SettingsCollection $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    /**
     * Returns the collection in the cache, if it exists.
     */
    public function retrieve(): ?Collection
    {
        return $this->cache->get($this->key);
    }

    /**
     * Check if the cache of the settings not is older these settings.
     */
    public function shouldRegenerate(): bool
    {
        // If the time doesn't exist in the cache then we can safely store.
        if (!isset($this->cache) || !$time = $this->cache?->get("$this->key:time")) {
            return true;
        }

        // Return if the is an invalidation (data changed) and is fresher.
        return (bool) $this->invalidatedAt?->isAfter($time);
    }

    /**
     * Saves the collection of settings in the cache.
     */
    public function regenerate(bool $force = false): void
    {
        if (isset($this->cache) && ($force || $this->shouldRegenerate())) {
            $this->cache?->setMultiple([
                $this->key => $this->settings,
                "$this->key:time" => now(),
            ], $this->ttl);
        }
    }

    /**
     * Invalidates the cache of the setting's user.
     */
    public function invalidate(): void
    {
        $this->cache->forget($this->key);
        $this->cache->forget("$this->key:time");

        // Update the time of the last invalidation.
        $this->invalidatedAt = now();
    }

    /**
     * Invalidate the settings cache if it has not been done before.
     */
    public function invalidateIfNotInvalidated(): void
    {
        if (!$this->invalidatedAt) {
            $this->invalidate();
        }
    }

    /**
     * Marks the settings cache to regenerate on exit.
     */
    public function regenerateOnExit(): void
    {
        // Just a simple trick to regenerate only if it's enabled.
        $this->settings->regeneratesOnExit = $this->automaticRegeneration;
    }

    /**
     * representation of object.
     */
    public function __serialize(): array
    {
        return []; // Do not serialize this.
    }

    /**
     * Constructs the object.
     *
     * @param string $data
     */
    public function __unserialize($data): void
    {
        // Don't unserialize from anything.
    }

    /**
     * String representation of object.
     */
    public function serialize(): ?string
    {
        return null; // Do not serialize this.
    }

    /**
     * Constructs the object.
     *
     * @param string $data
     */
    public function unserialize($data): void
    {
        // Don't unserialize from anything.
    }

    /**
     * Creates a new instance.
     */
    public static function make(Config $config, Factory $factory, Model $model): static
    {
        return new static(
            $factory->store($config->get('laraconfig.cache.store')),
            MorphManySettings::generateKeyForModel(
                $config->get('laraconfig.cache.prefix', 'laraconfig'),
                $model->getMorphClass(),
                $model->getKey()
            ),
            $config->get('laraconfig.cache.ttl', 60 * 60 * 3),
            $config->get('laraconfig.cache.automatic', true),
        );
    }
}
