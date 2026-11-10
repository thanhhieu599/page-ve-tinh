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

## Bổ sung khi triển khai (issue #4, #5)

| Bảng / thay đổi | Cột quan trọng | Ghi chú |
|---|---|---|
| `site_user` | site_id, user_id | Người dùng được gán vào site nào |
| roles/permissions (spatie) | admin, editor, writer; quyền `approve-expert-content` | Tạo qua migration |
| `posts` (thêm cột) | seo_title, meta_description, og_image, review_note | `review_note`: ghi chú của biên tập khi trả bài |
| `posts.status` | draft / pending / published | Chỉ đổi qua các bước duyệt, không gán hàng loạt |
| `post_revisions` | post_id, **title**, content, user_id, created_at | Thêm `title` để khôi phục cả tiêu đề |
| `sites` (thêm cột) | requires_expert_review | Site PPE: chỉ người có quyền duyệt chuyên môn đăng/sửa bài đang hiện |

## Bổ sung khi triển khai (issue #6)

| Bảng / thay đổi | Cột quan trọng | Ghi chú |
|---|---|---|
| `categories` | site_id, parent_id, slug, name, description, seo_title, meta_description, locale, requires_expert_review | Cờ duyệt chuyên môn kế thừa xuống danh mục con; chỉ Admin đổi/xoá danh mục có cờ |
| `post_category` | post_id, category_id (+ id) | Danh mục gắn đầu tiên dùng cho breadcrumb |
| `tags`, `post_tag` | site_id, slug, name | Tag trùng tên trong site được dùng lại |
| `faqs` | post_id, question, answer, sort, locale | |
| `authors` | name, slug, job_title, bio, expertise, avatar, linkedin_url | **Không có site_id**: tác giả dùng chung mọi site (hồ sơ E-E-A-T thống nhất); danh sách bài lọc theo site đang xem |
| `posts.author_id` | | |

Đổi slug danh mục hoặc tác giả cũng tự tạo redirect 301 (tác giả: trên mọi site).

## Bổ sung khi triển khai (issue #7)

| Bảng / thay đổi | Cột quan trọng | Ghi chú |
|---|---|---|
| `media` | site_id, path, alt, width, height, size, **variants** | `path` là thư mục chứa bản WebP 480/960/1600px; `variants` là danh sách chiều rộng đã tạo |

- Ảnh trong bài chỉ được chèn từ thư viện của site; ảnh dán từ nơi khác bị từ chối khi lưu và bị bỏ khi hiển thị.
- Ảnh đang dùng trong bài không xoá được.
- Đường dẫn file công khai là tương đối (`/storage/...`) để mỗi domain tự phục vụ. Khi cần URL tuyệt đối (og:image, sitemap) phải ghép domain của site.

## Bổ sung khi triển khai (issue #8)

| Bảng / thay đổi | Cột quan trọng | Ghi chú |
|---|---|---|
| `products` | site_id, name, sku, specs, buy_url, price (VNĐ, chưa VAT), price_updated_at, **slug, description, media_id**, is_active, source | UNIQUE(site_id, sku), UNIQUE(site_id, slug). `slug`, `description`, `media_id` (ảnh từ thư viện) do CMS quản lý, nhập CSV/Sapo không ghi đè |
| `post_product` | post_id, product_id | Khối "Sản phẩm liên quan" trong bài |

- Giá chỉ đổi qua `setPrice()` (0 = bỏ giá, chặn âm và > 50 tỷ) để `price_updated_at` luôn đi kèm.
- Giá cập nhật ngày D (giờ Việt Nam) hiện trên site đến hết ngày D + 14; sau đó hiện "Liên hệ báo giá".
- `buy_url` chỉ nhận `https://trangthietbi.vn/...`. Đổi slug sản phẩm tự tạo redirect 301.

## Bổ sung khi triển khai (issue #9)

| Bảng / thay đổi | Cột quan trọng | Ghi chú |
|---|---|---|
| `products` (thêm cột) | discontinued_at, replacement_product_id | `discontinued_at`: bị ẩn vì ngừng bán trên Sapo (khác ẩn tay). `replacement_product_id`: URL sản phẩm bị ẩn chuyển 301 sang đây; không có thì 410 |

- Nhập sản phẩm qua `App\Support\ProductSync\ProductSync` (dùng chung cho CSV và Sapo API sau này): khớp SKU trong site; nguồn chỉ ghi tên, thông số, link mua, giá (giá đi qua `setPrice`, làm mới ngày giá); không ghi slug, mô tả, ảnh, liên kết bài.
- Ngừng bán → ẩn; bán lại → hiện lại (chỉ sản phẩm bị ẩn vì ngừng bán). Chế độ "file đầy đủ" ẩn SKU vắng mặt, trừ sản phẩm tạo tay.

## Bổ sung khi triển khai (issue #10)

| Bảng / thay đổi | Cột quan trọng | Ghi chú |
|---|---|---|
| `leads` | site_id, name, company, email, phone, need, **product_id**, page_url, **landing_url**, utm_source/medium/campaign, consent_text, consent_at, ip, **is_verified**, status, deal_value, won_at, synced_to_mautic_at | `is_verified`: đã qua Turnstile (gửi khi tắt JavaScript = chưa xác minh). `status`: new/qualified/quoted/won/lost; chuyển "won" tự ghi `won_at` |

- Nguồn truy cập (trang vào đầu, UTM) ghi vào phiên ở lần vào đầu tiên (middleware CaptureAttribution).
- Lead chỉ xem/sửa bởi Biên tập/Admin; thông tin khách và bằng chứng đồng ý chỉ đọc.

## Bổ sung khi triển khai (issue #11)

- `leads.mautic_contact_id`: id contact bên Mautic sau khi đồng bộ. Index (site_id, synced_to_mautic_at) cho bộ lọc "Chưa đồng bộ".
- Lead mới → job `SyncLeadToMautic` (hàng đợi): tạo/cập nhật contact theo email, tag `rfq`, `site-<domain>`, `nganh-<industry_tag>`, `chua-xac-minh` (nếu chưa qua Turnstile); UTM qua `/contacts/{id}/utm/add`; ghi chú nhu cầu; campaign `settings.mautic_campaign_id` của site. Thử 5 lần (1, 5, 15, 60 phút), sau đó ghi log lỗi và email `DEV_ALERT_EMAIL` (chỉ mã lỗi, không kèm nội dung phản hồi).
- `leads.mautic_synced_steps` (JSON): các bước đã xong (`utm`, `note`, `campaign`); lần thử lại bỏ qua bước đã xong để không tạo ghi chú/UTM trùng. Job là duy nhất theo lead (không xếp hàng trùng khi bấm "Đồng bộ lại" nhiều lần).
- Lead chưa xác minh (chưa qua Turnstile) vẫn vào Mautic với tag `chua-xac-minh` nhưng **không** vào campaign tự động.
- `MAUTIC_URL` bắt buộc https (Basic Auth). Cấu hình site: `settings.mautic_campaign_id` (số), `settings.industry_tag` (chữ thường, số, gạch nối).


## Bổ sung khi triển khai (issue #12)

- `sites.is_indexable` (mặc định `false`): công tắc index. Khi tắt, mọi phản hồi của site có header `X-Robots-Tag: noindex, nofollow` (kể cả 404, 410), mọi trang có `<meta name="robots" content="noindex, nofollow">`; `robots.txt` vẫn cho thu thập (để Google đọc được noindex, kể cả khi tắt index một site đã lên Google) nhưng không khai báo sitemap. Domain không phải site (trang quản trị) trả `Disallow: /`.
- Khi bật: `robots.txt` cho thu thập và khai báo `Sitemap: https://<domain>/sitemap.xml`. Trang tự khai `@section('robots', 'noindex')` nếu không muốn được index (vd. trang cảm ơn sau khi gửi báo giá).
- Chỉ Admin bật/tắt, tại trang quản trị "Mở index (SEO)", và chỉ bật được khi đạt checklist (`App\Support\Indexing\IndexReadiness`): ≥ 20 bài đã đăng; mọi bài đã đăng có tác giả kèm hồ sơ (bio); trang giới thiệu công ty, chính sách quyền riêng tư, liên hệ (luôn "chưa có" cho tới issue #15).
- `sitemap.xml` theo site (spatie/laravel-sitemap): bài đã đăng (không nháp, chờ duyệt, hẹn giờ, đã xoá), sản phẩm đang bán, danh mục và tác giả có ít nhất một bài đã đăng; `lastmod` = `updated_at`.
- Thẻ canonical: URL chuẩn của trang (https, không www, chữ thường, không "/" cuối), bỏ mọi tham số; riêng trang danh sách (danh mục, tác giả) giữ `page` > 1, và trang vượt trang cuối trả 404. URL tuyệt đối ghép từ domain của site (`Site::url()`).
- `public/robots.txt` đã bỏ: robots.txt do ứng dụng sinh theo domain. Nginx không được phục vụ file robots.txt tĩnh.

## Bổ sung khi triển khai (issue #13)

- Không thêm bảng. Schema JSON-LD sinh khi hiển thị bằng spatie/schema-org (`App\Support\StructuredData`).
- Thông tin D&T ở một nơi: `config/company.php` (biến môi trường `COMPANY_*`). Mục trống thì schema bỏ qua.
- Mọi trang: `Organization` (D&T). Trang có breadcrumb: `BreadcrumbList` sinh từ chính danh sách breadcrumb hiển thị.
- Organization có `@id` = `<COMPANY_URL>/#organization`; `publisher` của Article trỏ cùng `@id`. Logo chỉ nhận URL https. Ảnh dùng `Site::absoluteUrl()` (giữ nguyên URL CDN tuyệt đối).
- Bài viết: `Article` (headline, mô tả nếu có, tác giả + URL trang hồ sơ, ngày đăng/cập nhật theo giờ Việt Nam, ảnh = OG image hoặc ảnh thư viện đầu tiên trong bài, publisher = D&T); `FAQPage` khi bài có câu hỏi, đúng thứ tự hiển thị.
- Sản phẩm: `Product` (tên, SKU, mô tả, ảnh, URL); `offers` (giá VND, `priceValidUntil`) **chỉ khi** giá còn hạn 14 ngày. Không khai tồn kho.

## Bổ sung khi triển khai (issue #14)

- `keyword_map` (site_id, keyword, target_url, intent): `keyword` lưu dạng chuẩn (chữ thường, một khoảng trắng), collation `utf8mb4_bin`, **unique toàn hệ thống** (mỗi từ khoá một trang đích). `target_url` là đường dẫn chuẩn trong site. `intent`: informational / commercial / transactional / navigational.
- Gán từ khoá đã thuộc trang khác (cùng hoặc khác site) → báo lỗi kèm URL trang đang giữ; chỉ Admin bật "Chuyển từ khoá sang trang này" để ghi đè (dòng cũ được chuyển, không nhân đôi). Người viết không vào màn hình này.
- Từ khoá chuẩn hoá Unicode NFC trước khi lưu/so khớp. Ngoại lệ có chủ đích với quy tắc collation chung: cột `keyword` dùng `utf8mb4_bin` để "găng tay" và "gang tay" là hai từ khoá khác nhau. (Chủ dự án đã duyệt ngày 10/11/2026.)
- Biên tập: gán từ khoá mới, đổi mục đích. Chỉ Admin: đổi trang đích, ghi đè, xoá (policy `reassign`/`delete`), để không ai lách quy tắc bằng sửa hoặc xoá rồi tạo lại.
- `posts.has_purchase_context` (mặc định false): bài có ngữ cảnh mua hàng/báo giá; chỉ Biên tập/Admin thấy và bật.
- Chính sách liên kết khi lưu bài (`App\Support\ContentLinkRules`): link sang domain site vệ tinh khác (kể cả www, subdomain, viết hoa, href không nháy, "\\") → chặn; khôi phục bản cũ có link cấm cũng bị chặn; link sang trangthietbi.vn (và subdomain) → chặn, trừ link mua của sản phẩm đang bán trên site hoặc bài có `has_purchase_context`. Link trong khối sản phẩm/báo giá của theme không thuộc nội dung bài nên luôn được phép.
- Footer theme: ghi rõ thuộc D&T, chỉ nhãn điều hướng, không anchor từ khoá, không link sang site vệ tinh khác hay trangthietbi.vn. Test chạy cho mọi theme trong `resources/themes`.
- Việc để sau (đã thống nhất): cảnh báo trong trang quản trị liệt kê các bài đang có link mua tới sản phẩm đã ngừng bán (bài vẫn hiện, nhưng lần sửa tiếp theo sẽ bị chặn cho tới khi gỡ link).
