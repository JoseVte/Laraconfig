<?php

namespace DarkGhostHunter\Laraconfig\Eloquent\Casts;

use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use DarkGhostHunter\Laraconfig\Eloquent\Setting;
use DarkGhostHunter\Laraconfig\Eloquent\Metadata;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * @internal
 */
class DynamicCasting implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param \DarkGhostHunter\Laraconfig\Eloquent\Setting|\DarkGhostHunter\Laraconfig\Eloquent\Metadata $model
     * @param mixed                                                                                      $value
     *
     * @throws \JsonException
     */
    public function get(
        $model,
        string $key,
        $value,
        array $attributes
    ): array|bool|float|int|string|Collection|DateTimeInterface|null
    {
        if (null === $value) {
            return null;
        }

        if ($model instanceof Setting && !isset($attributes['type'], $attributes['metadata_id'])) {
            return $value;
        }

        return match ($attributes['type'] ??= Metadata::whereKey($attributes['metadata_id'])->value('type')) {
            Metadata::TYPE_ARRAY => Arr::wrap(json_decode($value, true, 512, JSON_THROW_ON_ERROR)),
            Metadata::TYPE_BOOLEAN => (bool) $value,
            Metadata::TYPE_DATETIME => Carbon::parse($value),
            Metadata::TYPE_COLLECTION => new Collection(Arr::wrap(json_decode($value, true, 512, JSON_THROW_ON_ERROR))),
            Metadata::TYPE_FLOAT => (float) $value,
            Metadata::TYPE_INTEGER => (int) $value,
            default => $value,
        };
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param \DarkGhostHunter\Laraconfig\Eloquent\Setting|\DarkGhostHunter\Laraconfig\Eloquent\Metadata $model
     * @param mixed                                                                                      $value
     *
     * @throws \JsonException
     */
    public function set(
        $model,
        string $key,
        $value,
        array $attributes
    ): null|array|int|bool|float|string|Collection|DateTimeInterface
    {
        if (null === $value) {
            return null;
        }

        if ($model instanceof Setting && !isset($attributes['type'], $attributes['metadata_id'])) {
            return $value;
        }

        return match ($attributes['type'] ??= Metadata::whereKey($attributes['metadata_id'])->value('type')) {
            Metadata::TYPE_COLLECTION,
            Metadata::TYPE_ARRAY => json_encode(is_array($value) ? Arr::wrap($value) : $value, JSON_THROW_ON_ERROR),
            Metadata::TYPE_BOOLEAN => (bool) $value,
            Metadata::TYPE_DATETIME => Carbon::parse($value),
            Metadata::TYPE_STRING => (string) $value,
            Metadata::TYPE_INTEGER => (int) $value,
            Metadata::TYPE_FLOAT => (float) $value,
            default => $value,
        };
    }
}
