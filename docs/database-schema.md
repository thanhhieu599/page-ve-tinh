# Thiết kế database – bản nháp v0 (do anh Thụy đưa ra ở vòng 1, Q5)

Mô hình: một database phục vụ nhiều site (đa site). Mọi bảng nội dung đều có `site_id`.

## Bảng lõi

| Bảng | Cột quan trọng | Ghi chú |
|---|---|---|
| `sites` | id, domain, name, theme, settings (JSON), is_active | Mỗi domain một dòng |
| `posts` | site_id, slug, title, excerpt, content, seo_title, meta_description, og_image, author_id, status, published_at, updated_at, deleted_at | UNIQUE(site_id, slug) |
| `categories` | site_id, parent_id, slug, name, description, seo_title, meta_description | Danh mục cha-con |
| `post_category`, `tags`, `post_tag` | khoá ngoại | Quan hệ nhiều-nhiều |
| `faqs` | post_id, question, answer, sort | Nguồn cho schema FAQPage |
| `authors` | name, slug, bio, expertise, avatar, linkedin_url | Trang hồ sơ tác giả (E-E-A-T) |
| `media` | site_id, path, alt, width, height, size | Alt bắt buộc |
| `redirects` | site_id, from_path, to_path, code (301/410), hits | Tự sinh khi đổi slug |
| `leads` | site_id, name, company, email, phone, need, page_url, utm_source/medium/campaign, consent_at, synced_to_mautic_at | Lưu cả bằng chứng đồng ý |
| `post_revisions` | post_id, content, user_id, created_at | Khôi phục bản cũ |
| `keyword_map` | keyword, site_id, target_url, intent | Chống các site tự cạnh tranh từ khoá |

## Quy tắc bắt buộc

- Charset `utf8mb4`, collation `utf8mb4_unicode_ci` (tiếng Việt đầy đủ dấu).
- Mọi thay đổi cấu trúc đi qua migration trong Git, không sửa tay trên server.
- Index trên `(site_id, status, published_at)` và `(site_id, slug)`.
- Xoá mềm (soft delete) cho bài viết.
- Thời gian lưu UTC, hiển thị theo giờ Việt Nam.

## Điểm còn mở (xem vòng 2 trong `grilling-log.md`)

- Bảng số liệu hiệu quả (GSC) và gắn doanh thu cho lead – phục vụ Q2 "cả 3 chỉ số".
- Bảng tiêu chuẩn / sản phẩm – phục vụ độ khác biệt nội dung.
- Bảng `users` (đang được tham chiếu bởi `post_revisions.user_id` nhưng chưa định nghĩa).
- Cột ngôn ngữ (`locale`) nếu làm bản tiếng Anh cho khách FDI.
