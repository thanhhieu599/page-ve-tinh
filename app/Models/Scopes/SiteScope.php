<?php

namespace App\Models\Scopes;

use App\Support\CurrentSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Lọc theo site của request hiện tại. Ngoài request site (console, test dữ liệu)
 * thì không lọc.
 */
class SiteScope implements Scope
{
    /** @param  Builder<Model>  $builder */
    public function apply(Builder $builder, Model $model): void
    {
        $site = app(CurrentSite::class)->get();

        if ($site !== null) {
            $builder->where($model->qualifyColumn('site_id'), $site->id);
        }
    }
}
