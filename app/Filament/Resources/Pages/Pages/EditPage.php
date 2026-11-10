<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Support\TenantSite;
use App\Support\ContentLinkRules;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    /**
     * Trang bắt buộc theo cùng chính sách liên kết với bài viết. Trang giới thiệu/liên hệ
     * được nhắc tới trangthietbi.vn (minh bạch chủ sở hữu), nhưng không link site vệ tinh khác.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $site = TenantSite::site();
        $violations = $site !== null ? ContentLinkRules::violations((string) ($data['content'] ?? ''), $site, hasPurchaseContext: true) : [];

        if ($violations !== []) {
            throw ValidationException::withMessages(['data.content' => $violations]);
        }

        return $data;
    }
}
