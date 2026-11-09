<?php

namespace App\Support;

use App\Models\Site;

/**
 * Giữ site của request hiện tại. Được đăng ký dạng scoped nên mỗi request
 * (và mỗi job trong worker) bắt đầu với giá trị rỗng.
 */
class CurrentSite
{
    private ?Site $site = null;

    public function set(Site $site): void
    {
        $this->site = $site;
    }

    public function get(): ?Site
    {
        return $this->site;
    }
}
