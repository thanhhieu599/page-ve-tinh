<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Model;

/** Số liệu Search Console theo trang, theo ngày. Ghi bằng upsert khi kéo dữ liệu (PullSearchConsoleData). */
class GscPageDaily extends Model
{
    use BelongsToSite;

    protected $table = 'gsc_page_daily';

    protected $guarded = ['id'];
}
