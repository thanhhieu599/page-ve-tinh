<?php

namespace App\Support;

use App\Enums\PageKind;
use App\Models\CompanyProfile;

/**
 * Câu đồng ý xử lý dữ liệu cá nhân trên form RFQ. Nguyên văn được lưu vào từng lead
 * làm bằng chứng. Đổi câu này thì lead cũ vẫn giữ câu cũ đã đồng ý.
 *
 * Cần pháp chế/luật sư duyệt trước khi chạy thật (Luật Bảo vệ dữ liệu cá nhân,
 * Nghị định 13/2023/NĐ-CP).
 */
final class Consent
{
    /** Mẫu câu đồng ý; :company là tên pháp lý hiện tại trong "Thông tin công ty". */
    public const TEMPLATE = 'Tôi đồng ý để :company xử lý thông tin tôi cung cấp '
        .'(họ tên, công ty, email, số điện thoại, nhu cầu) để liên hệ tư vấn, báo giá và gửi thông tin sản phẩm liên quan, '
        .'theo Chính sách bảo vệ dữ liệu cá nhân.';

    /** Câu đồng ý hiện trên form và lưu kèm lead (tên công ty luôn khớp thông tin pháp lý đang dùng). */
    public static function text(): string
    {
        return str_replace(':company', (string) CompanyProfile::current()->value('legal_name'), self::TEMPLATE);
    }

    public const POLICY_PATH = '/'.PageKind::Privacy->value;
}
