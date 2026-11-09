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

## Bổ sung đã chốt ở vòng 2

| Bảng / thay đổi | Cột quan trọng | Lý do |
|---|---|---|
| `users` | name, email, password, roles (spatie/laravel-permission) | `post_revisions.user_id` cần bảng này |
| `gsc_page_daily` | site_id, page_url, date, impressions, clicks, ctr, position | Đo traffic/thứ hạng (Q2), kéo tự động từ Search Console |
| `leads` (thêm cột) | status (mới / đủ chuẩn / báo giá / thắng / thua), deal_value, won_at, consent_text, ip | Đo doanh thu (Q2); bằng chứng đồng ý (Q21) |
| `standards` | code, name, field, issued_year | Ví dụ EN 388:2016, TCVN, ISO 22000 (Q10) |
| `post_standard` | post_id, standard_id | Nhiều-nhiều |
| `products` | site_id, name, sku, specs (JSON), buy_url (trangthietbi.vn), price, price_updated_at, is_active, source (csv/api/manual) | Gắn SP vào bài, schema Product (Q10); giá tham khảo, hết hạn sau 14 ngày (Q33–Q35) |
| Cột `locale` | trên `posts`, `categories`, `faqs` | Mở tiếng Anh sau mà không đổi cấu trúc (Q12) |

Database CMS tách riêng khỏi database Mautic (Q13).
