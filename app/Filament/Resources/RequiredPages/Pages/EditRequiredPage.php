<?php

namespace App\Filament\Resources\RequiredPages\Pages;

use App\Enums\PageKind;
use App\Filament\Resources\RequiredPages\RequiredPageResource;
use App\Filament\Support\TenantSite;
use App\Models\RequiredPage;
use App\Support\ContentLinkRules;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditRequiredPage extends EditRecord
{
    protected static string $resource = RequiredPageResource::class;

    /**
     * Trang bắt buộc theo cùng chính sách liên kết với bài viết. Trang giới thiệu/liên hệ
     * được nhắc tới trangthietbi.vn (minh bạch chủ sở hữu), nhưng không link site vệ tinh khác.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $page = $this->getRecord();
        assert($page instanceof RequiredPage);

        // Trang giới thiệu: chỉ được đăng khi đủ câu định vị, đoạn "Về chúng tôi", số cam kết.
        if ($page->kind === PageKind::About) {
            if ($data['is_published'] ?? false) {
                $draft = (new RequiredPage)->forceFill(['extras' => $data['extras'] ?? []]);
                $missing = $draft->aboutMissing();
                if ($missing !== []) {
                    throw ValidationException::withMessages(collect($missing)->mapWithKeys(fn ($message, $field) => ["data.extras.{$field}" => $message])->all());
                }
            }
            $data['content'] = '';

            return $data;
        }

        $content = (string) ($data['content'] ?? '');

        if (($data['is_published'] ?? false) && RequiredPage::hasPlaceholders($content)) {
            throw ValidationException::withMessages(['data.content' => 'Nội dung còn đoạn [CẦN BỔ SUNG]/[CẦN XÁC NHẬN]. Thay bằng thông tin thật trước khi bật hiển thị.']);
        }

        $site = TenantSite::site();
        $mentionsStore = $page->kind !== PageKind::Privacy;
        $violations = $site !== null ? ContentLinkRules::violations($content, $site, hasPurchaseContext: $mentionsStore) : [];

        if ($violations !== []) {
            throw ValidationException::withMessages(['data.content' => $violations]);
        }

        return $data;
    }
}
