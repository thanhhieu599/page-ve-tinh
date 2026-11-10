<?php

namespace App\Filament\Resources\Posts\Concerns;

use App\Models\Site;
use App\Support\LinkPolicy;
use Filament\Facades\Filament;
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
        $site = Filament::getTenant();

        if (! is_string($content) || ! $site instanceof Site) {
            return;
        }

        $hasPurchaseContext = (bool) ($data['has_purchase_context'] ?? $savedPurchaseContext);
        $violations = LinkPolicy::violations($content, $site, $hasPurchaseContext);

        if ($violations !== []) {
            throw ValidationException::withMessages(['data.content' => $violations]);
        }
    }
}
