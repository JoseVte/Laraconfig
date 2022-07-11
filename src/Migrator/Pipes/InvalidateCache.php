<?php

namespace DarkGhostHunter\Laraconfig\Migrator\Pipes;

use Closure;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\LazyCollection;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Config\Repository;
use DarkGhostHunter\Laraconfig\Migrator\Data;
use DarkGhostHunter\Laraconfig\MorphManySettings;
use Symfony\Component\Console\Input\InputInterface;

class InvalidateCache
{
    /**
     * RemoveOldMetadata constructor.
     *
     * @param \Illuminate\Contracts\Config\Repository         $config
     * @param \Illuminate\Console\OutputStyle                 $output
     * @param \Illuminate\Contracts\Cache\Factory             $cache
     * @param \Symfony\Component\Console\Input\InputInterface $input
     */
    public function __construct(
        protected Repository $config,
        protected OutputStyle $output,
        protected Factory $cache,
        protected InputInterface $input
    ) {
    }

    /**
     * Handles the Settings migration.
     */
    public function handle(Data $data, Closure $next): mixed
    {
        // If the cache was flushed, there are no cache keys to forget.
        if ($this->shouldInvalidateCacheKeys($data)) {
            $store = $this->config->get('cache.default');

            $this->output->info(
                "Forgot {$this->forgetModelCacheKeys($data)} config cache keys/users from the cache store $store."
            );
        }

        return $next($data);
    }

    /**
     * Check if we should cycle through models to invalidate their keys.
     */
    protected function shouldInvalidateCacheKeys(Data $data): bool
    {
        return $data->invalidateCache
            && !$this->input->getOption('flush-cache')
            && $this->config->get('laraconfig.cache.enable');
    }

    /**
     * Forget model cache keys.
     */
    protected function forgetModelCacheKeys(Data $data): int
    {
        $store = $this->cache->store($this->config->get('laraconfig.cache.store'));

        $prefix = $this->config->get('laraconfig.cache.prefix');

        $count = 0;

        foreach ($data->models as $settable) {
            $morph = $settable->getMorphClass();
            $keyName = $settable->getKeyName();

            foreach ($this->querySettable($settable) as $model) {
                $key = MorphManySettings::generateKeyForModel($prefix, $morph, $model->{$keyName});

                $store->forget($key);
                $store->forget("$key:time");

                ++$count;
            }
        }

        return $count;
    }

    /**
     * Returns a query to retrieve all settings distinct by type and id.
     */
    protected function querySettable(Model $settable): LazyCollection
    {
        // We can simply query the settings table through its index of morphs.
        return $settable->newQuery()
            ->getQuery()
            ->select($name = $settable->getKeyName())
            ->lazyById(column: $name);
    }
}
