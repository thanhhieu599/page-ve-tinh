<?php

namespace App\Support\Reports;

use App\Models\Site;

/** Một dòng báo cáo: số liệu tìm kiếm và bán hàng của một site trong kỳ. */
final readonly class SiteReportRow
{
    public function __construct(
        public Site $site,
        public int $impressions,
        public int $clicks,
        public float $positionTimesImpressions,
        public int $leads,
        public int $wonLeads,
        public int $revenue,
    ) {}

    /** Tỉ lệ nhấp (%), tính từ tổng lượt nhấp / tổng lượt hiển thị. */
    public function ctr(): ?float
    {
        return $this->impressions > 0 ? $this->clicks * 100 / $this->impressions : null;
    }

    /** Vị trí trung bình, có trọng số theo lượt hiển thị (cách Search Console tính). */
    public function averagePosition(): ?float
    {
        return $this->impressions > 0 ? $this->positionTimesImpressions / $this->impressions : null;
    }
}
