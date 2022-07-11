<?php

namespace DarkGhostHunter\Laraconfig\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;

class WhereConfig implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
    }

    /**
     * Extend the query builder with the needed functions.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('whereConfig', [static::class, 'whereConfig']);
        $builder->macro('orWhereConfig', [static::class, 'orWhereConfig']);
    }

    /**
     * Filters the user by the config value.
     *
     * @param null $value
     */
    public static function whereConfig(
        Builder $builder,
        string|array $name,
        string $operator = null,
        $value = null,
        string $boolean = 'and'
    ): Builder {
        if (is_array($name)) {
            foreach ($name as $key => $item) {
                if (is_array($item)) {
                    static::whereConfig($builder, ...$item);
                } else {
                    static::whereConfig($builder, $key, $item);
                }
            }

            return $builder;
        }

        return $builder->has(
            relation: 'settings',
            boolean: $boolean,
            callback: static function (Builder $builder) use ($name, $operator, $value): void {
                $builder
                    ->withoutGlobalScope(AddMetadata::class)
                    ->where(
                        static function (Builder $builder) use ($name, $operator, $value): void {
                            $builder
                                ->where('value', $operator, $value)
                                ->whereHas('metadata', static function (Builder $builder) use ($name): void {
                                    $builder->where('name', $name);
                                });
                        }
                    );
            }
        );
    }

    /**
     * Filters the user by the config value.
     *
     * @param null $value
     */
    public static function orWhereConfig(
        Builder $builder,
        string|array $name,
        string $operator = null,
        $value = null,
    ): Builder {
        return static::whereConfig($builder, $name, $operator, $value, 'or');
    }
}
