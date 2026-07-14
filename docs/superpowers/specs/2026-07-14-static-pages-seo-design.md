# Static Pages (SEO/AdSense) — Design

> **Sub-project 1** trong loạt "thêm section/page mới" cho WordPress theme `bongda247`. Các sub-project khác (section theo giải, trang chuyên mục nâng cao, trang dữ liệu bóng đá) có spec riêng, làm sau.

## Mục tiêu

Thêm bộ **4 trang tĩnh** (Chính sách bảo mật, Giới thiệu, Liên hệ, Điều khoản) để: (1) đủ điều kiện duyệt **Google AdSense** (bắt buộc có Privacy Policy + kênh liên hệ), (2) tăng tín hiệu **E-E-A-T** cho SEO, (3) tạo uy tín cho người đọc. Nội dung tiếng Việt.

## Bối cảnh & ràng buộc

- Theme hiện **chưa có `page.php`** → mọi WordPress Page đang fallback về `index.php` (hiển thị như archive, sai). Đây là lý do kỹ thuật chính phải làm.
- Theme conventions sẵn có (khớp `single.php`): class `container`, `.prose-bd` (đã có trong `src/main.css`), `font-hemi`, `text-secondary`, `bg-card`, breadcrumb "Trang chủ / …".
- Nội dung viết tiếng Việt; giọng trung tính, chuẩn mực.
- Deploy phải lặp lại được local ↔ prod (triết lý dự án: mọi thứ trong repo, provisioning bằng script).

## Quyết định thiết kế

### A. WordPress Pages + 1 template `page.php` (không hardcode nội dung)
Nội dung sống trong WP Pages (sửa được trong admin), theme chỉ cung cấp **một** template `page.php` dùng chung. Không tạo template riêng cho từng trang (`page-{slug}.php`) — YAGNI, 4 trang đều là nội dung dài dạng prose giống nhau.
*Loại bỏ:* hardcode nội dung vào file PHP (không sửa được trong admin), hoặc template riêng mỗi trang (thừa).

### B. Trang Liên hệ = chỉ thông tin/email (không form)
Hiển thị email + mailto (+ mạng xã hội nếu có sau). Không dùng plugin form, không xử lý gửi mail trên shared host (tránh spam + rủi ro deliverability). Đủ điều kiện AdSense. Form có thể thêm ở sub-project sau nếu cần.

### C. Nội dung do Claude draft, tailored đúng tích hợp thật của site
Không dùng template Privacy Policy generic. Privacy Policy nêu đúng: Google AdSense/DoubleClick + DART cookie, Google Analytics 4, football-data.org, không có hệ thống bình luận/đăng ký tài khoản. Điều khoản nêu đúng mô hình "viết lại tin từ RSS có dẫn nguồn" + attribution football-data.org. User review + chỉnh trước khi publish.

### D. Tạo Pages bằng seed script lặp lại được
Một script idempotent (theo pattern `web/scripts/seed-wp.mjs` + adapter `web/lib/wp.js`) tạo/cập nhật 4 Page từ file nội dung HTML trong repo. Chạy 1 lệnh có đủ trang ở cả local lẫn prod; nội dung version-controlled.
*Loại bỏ:* tạo tay trong admin (không lặp lại được cho prod, không version-control).

### E. UI-build: skill `design-taste-frontend` ở chế độ "match-existing" (chỉ nguyên tắc)
Áp **nguyên tắc** của `design-taste-frontend` (Design Read, kỷ luật typography/màu, anti-slop, Theme Lock §4.11, content density, copy self-audit, contrast a11y) — KHÔNG lấy stack React/Next/Motion/next-font của nó (dự án là WP PHP + Tailwind v4 CLI). Chế độ **preserve/match-existing**: giữ nguyên bộ nhận diện theme `bongda247` (dark, brand `#0232ff`, Oswald/Inter/SVN-Hemi, `.prose-bd`), không tạo aesthetic mới. Dials thấp (editorial/trust-first): VARIANCE~5, MOTION~2 (không GSAP), DENSITY~2.
*Loại bỏ:* `gpt-taste` (GSAP nặng — sai ngữ cảnh trang pháp lý), `minimalist-ui` (warm-monochrome xung đột theme dark/blue), `stitch-design-taste` (không dùng Stitch). Các skill này để dành sub-project 2 (section trang chủ).

### Thông tin thật (đã xác nhận)
- **Email liên hệ công khai:** `phonght.dev@gmail.com`
- **Tên/brand hiển thị:** **Bongda247** — blog vận hành bởi một cá nhân tại Việt Nam (Hồ Tiến Phong). Không phải công ty/pháp nhân → nội dung không bịa ra entity doanh nghiệp.

## Kiến trúc / thành phần

### 1. `wp/themes/bongda247/page.php` (MỚI — file code chính)
Template trang tĩnh chung:
```
get_header()
  <div class="container">
    breadcrumb: Trang chủ / {get_the_title()}
    <h1 class="font-hemi ...">{title}</h1>
    <div class="prose-bd">{the_content()}</div>
  </div>
get_footer()
```
Không render post meta, category, tags, featured image. Guard `while (have_posts()) the_post()`.

### 2. `wp/themes/bongda247/footer.php` (SỬA)
Thêm **hàng link footer** phía trên dòng copyright, trỏ tới 4 trang. Dùng `get_permalink(get_page_by_path('slug'))` với guard nếu trang chưa tồn tại (không vỡ nếu seed chưa chạy). Style tối giản khớp footer hiện có.

### 3. Nội dung 4 trang (file HTML trong repo tại `web/content/pages/{slug}.html`)
| Trang | Slug | Nội dung chính |
|-------|------|----------------|
| Chính sách bảo mật | `chinh-sach-bao-mat` | Mở đầu; Dữ liệu thu thập (log, cookie); Cookie & công nghệ theo dõi; **Quảng cáo bên thứ ba — Google AdSense, DART cookie, link tới chính sách Google + trang tắt quảng cáo cá nhân hoá**; **Google Analytics 4**; Nguồn dữ liệu bên thứ ba (football-data.org); Không thu thập dữ liệu nhạy cảm / không có tài khoản người dùng; Quyền của bạn; Thay đổi chính sách; Liên hệ |
| Giới thiệu | `gioi-thieu` | Bongda247 là gì; Chủ đề (các giải lớn); **Quy trình nội dung: AI (Gemini) hỗ trợ soạn + con người kiểm duyệt trước khi đăng**; Cam kết chất lượng/nguồn; Ai đứng sau (cá nhân đam mê bóng đá) — tín hiệu E-E-A-T; Liên hệ |
| Liên hệ | `lien-he` | Lời mời liên hệ; **Email `phonght.dev@gmail.com` (mailto)**; (chỗ trống cho mạng xã hội sau); thời gian phản hồi dự kiến |
| Điều khoản | `dieu-khoan` | Chấp nhận điều khoản; Sử dụng nội dung; **Bản quyền & nguồn — nội dung viết lại từ nguồn tin có dẫn nguồn, dữ liệu số liệu từ football-data.org (ghi công); yêu cầu gỡ/khiếu nại bản quyền**; Miễn trừ trách nhiệm (nhận định/dự đoán chỉ tham khảo, không phải lời khuyên cá cược); Giới hạn trách nhiệm; Thay đổi điều khoản |

### 4. Seed script `web/scripts/seed-pages.mjs` (MỚI) hoặc mở rộng seed hiện có
- Dùng `web/lib/wp.js` (cần thêm helper `ensurePage(slug, title, html)` — tạo nếu chưa có, cập nhật nếu đã có → idempotent).
- Đọc nội dung 4 trang từ file repo, upsert theo slug.
- In kết quả (id + link mỗi trang).

## Data flow
Tĩnh: WP Page → `page.php` → `the_content()`. Không query thêm, không gọi API ngoài. Footer đọc permalink theo slug (cache của WP).

## Error handling
- `page.php`: nếu nội dung rỗng vẫn render khung (title + breadcrumb).
- `footer.php`: mỗi link bọc guard — trang chưa tồn tại thì bỏ qua link đó, không PHP warning.
- Seed: idempotent — chạy lại không nhân đôi (match theo slug).

## Tiêu chí thành công
- [ ] 4 trang trả **HTTP 200**, render qua `page.php` (có breadcrumb + `<h1>` + `.prose-bd`), không lỗi PHP.
- [ ] Trang `/chinh-sach-bao-mat/` chứa các cụm bắt buộc cho AdSense: "cookie", "Google AdSense" (hoặc "DoubleClick"), "quảng cáo", "Google Analytics", email liên hệ.
- [ ] **4 link footer** hiện trên mọi trang, click sang đúng trang.
- [ ] Seed idempotent: chạy 2 lần → vẫn đúng 4 trang, không nhân đôi.
- [ ] `page.php` KHÔNG hiển thị meta bài viết/category (khác `single.php`).

## Ngoài phạm vi (YAGNI / sub-project khác)
- Cài mã Google AdSense thật + GA4 (thuộc **deploy runbook**, không phải trang tĩnh).
- Form liên hệ.
- Trang tĩnh khác (FAQ, tuyển dụng...).
- Các sub-project 2–4 (section theo giải, chuyên mục nâng cao, dữ liệu bóng đá).
- Multi-language, sitemap (RankMath tự lo ở deploy).
