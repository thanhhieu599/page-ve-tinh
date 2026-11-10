<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Model;

/** Tổng Search Console của cả property theo ngày (khớp màn hình Search Console). Ghi bằng upsert khi kéo dữ liệu (PullSearchConsoleData). */
class GscSiteDaily extends Model
{
    use BelongsToSite;

    protected $table = 'gsc_site_daily';

    protected $guarded = ['id'];
}
