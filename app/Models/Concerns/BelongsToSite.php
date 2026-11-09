<?php

namespace App\Models\Concerns;

use App\Models\Scopes\SiteScope;
use App\Models\Site;
use App\Support\CurrentSite;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dành cho mọi model nội dung thuộc về một site.
 *
 * Khi đang xử lý request của một site, truy vấn tự lọc theo site đó và bản ghi
 * mới tự gán site_id. Dev không phải tự thêm điều kiện site_id.
 */
trait BelongsToSite
{
    public static function bootBelongsToSite(): void
    {
        static::addGlobalScope(new SiteScope);

        static::creating(function ($model): void {
            $site = app(CurrentSite::class)->get();

            if ($site !== null && $model->site_id === null) {
                $model->site_id = $site->id;
            }
        });
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
