<?php

namespace DarkGhostHunter\Laraconfig\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;

class FilterBags implements Scope
{
    /**
     * FilterBags constructor.
     *
     * @param array $bags
     */
    public function __construct(protected array $bags)
    {
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas('metadata', function (Builder $query): void {
            $query->whereIn('bag', $this->bags);
        });
    }
}
