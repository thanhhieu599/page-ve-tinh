<?php

namespace App\Support;

/**
 * Câu đồng ý xử lý dữ liệu cá nhân trên form RFQ. Nguyên văn được lưu vào từng lead
 * làm bằng chứng. Đổi câu này thì lead cũ vẫn giữ câu cũ đã đồng ý.
 *
 * Cần pháp chế/luật sư duyệt trước khi chạy thật (Luật Bảo vệ dữ liệu cá nhân,
 * Nghị định 13/2023/NĐ-CP).
 */
final class Consent
{
    public const TEXT = 'Tôi đồng ý để Công ty TNHH TMDV Phát triển Kỹ thuật D & T xử lý thông tin tôi cung cấp '
        .'(họ tên, công ty, email, số điện thoại, nhu cầu) để liên hệ tư vấn, báo giá và gửi thông tin sản phẩm liên quan, '
        .'theo Chính sách bảo vệ dữ liệu cá nhân.';

    public const POLICY_PATH = '/chinh-sach-bao-mat';
}
