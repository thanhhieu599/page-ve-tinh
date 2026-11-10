<?php

namespace App\Filament\Resources\Posts\Concerns;

use App\Filament\Support\TenantSite;
use App\Support\ContentLinkRules;
use Illuminate\Validation\ValidationException;

/**
 * Chặn lưu bài có link vi phạm chính sách liên kết, báo rõ link nào vi phạm.
 * Người viết không phải nhớ quy tắc.
 */
trait GuardsContentLinks
{
    /**
     * @param  array<string, mixed>  $data
     * @param  bool  $savedPurchaseContext  giá trị đang lưu, dùng khi người sửa không thấy công tắc
     */
    protected function ensureLinksFollowPolicy(array $data, bool $savedPurchaseContext = false): void
    {
        $content = $data['content'] ?? null;
        $site = TenantSite::site();

        if (! is_string($content) || $site === null) {
            return;
        }

        $hasPurchaseContext = (bool) ($data['has_purchase_context'] ?? $savedPurchaseContext);
        $violations = ContentLinkRules::violations($content, $site, $hasPurchaseContext);

        if ($violations !== []) {
            throw ValidationException::withMessages(['data.content' => $violations]);
        }
    }
}
