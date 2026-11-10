<?php

namespace App\Filament\Support;

use App\Models\Site;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * Site đang chọn trong trang quản trị. Dùng cho truy vấn và quy tắc kiểm tra
 * không đi qua global scope (danh sách lựa chọn, unique), để luôn giới hạn trong site.
 */
final class TenantSite
{
    public static function id(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Site ? $tenant->id : null;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scope(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('site_id'), self::id());
    }

    public static function unique(Unique $rule): Unique
    {
        return $rule->where('site_id', self::id());
    }
}
