<?php

namespace App\Filament\Resources\Posts\Concerns;

use App\Filament\Support\TenantSite;
use App\Support\ContentImages;
use Illuminate\Validation\ValidationException;

/**
 * Bài chỉ được chứa ảnh từ thư viện của site (có alt, biết kích thước). Ảnh dán từ
 * nơi khác bị từ chối khi lưu, kèm hướng dẫn chèn lại bằng nút thư viện.
 */
trait GuardsContentImages
{
    /** @param  array<string, mixed>  $data */
    protected function ensureImagesComeFromLibrary(array $data): void
    {
        $content = $data['content'] ?? null;

        if (is_string($content) && ContentImages::foreignSources($content, (int) TenantSite::id()) !== []) {
            throw ValidationException::withMessages([
                'data.content' => 'Bài có ảnh không thuộc thư viện của site (ảnh dán từ nơi khác). Hãy xoá ảnh đó và chèn bằng nút "Chèn ảnh từ thư viện".',
            ]);
        }
    }
}
