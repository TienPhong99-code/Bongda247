# Category News Sections (trang chủ) — Design

> **Sub-project 2** trong loạt "thêm section/page mới" cho WordPress theme `bongda247`. Các sub-project khác (trang chuyên mục nâng cao, dữ liệu bóng đá) có spec riêng, làm sau.

## Mục tiêu

Thêm các **block tin theo giải/chủ đề** vào trang chủ: mỗi block hiển thị bài mới nhất của một category (giải đấu hoặc chuyên mục), tăng độ sâu nội dung trang chủ và điều hướng người đọc vào từng giải. Dùng dữ liệu sẵn có (posts theo category), không gọi API ngoài.

## Bối cảnh & ràng buộc

- Trang chủ hiện có: section 1 (hot-news-slider `col-8` + sidebar-slider `col-4`), section 2 (match-insights carousel). Section render qua `get_template_part(...)`.
- Query helpers sẵn có trong `inc/query.php` (`bd_hot_posts`, `bd_sidebar_posts`, `bd_insights`, `bd_insight_is_upcoming`).
- Data: `post` có category (8 category: `ngoai-hang-anh`, `champions-league`, `la-liga`, `bundesliga`, `serie-a`, `ligue-1`, `chuyen-nhuong`, `ngoai-san-co`).
- Grid sẵn có: `.row` (flex) + `.col`/`.col-8`/`.col-4`. Heading style sẵn có: `font-hemi text-2xl uppercase border-l-4 border-brand pl-4`.
- Image sizes: `bd_hero` (1200×675), `bd_thumb` (200×200).
- **KHÔNG track lượt xem** (WP không track sẵn) → "xem nhiều nhất" ngoài phạm vi.

## Quyết định thiết kế

### A. Một template-part tái dùng + mảng slug cấu hình (không lặp code mỗi giải)
Một `template-parts/category-section.php` render 1 block cho bất kỳ category; `front-page.php` giữ **mảng slug** và loop. Thêm/bớt block = sửa mảng, không đụng template.
*Loại bỏ:* viết template riêng cho từng giải (lặp code), hoặc hardcode nhiều block trong front-page.

### B. Layout "Lead + list" (không phải grid 4 card đều nhau)
Mỗi block: 1 **bài lead** (ảnh `bd_hero` + tiêu đề + excerpt + ngày) chiếm ~⅔, cạnh **danh sách 4 tin phụ** (tiêu đề + ngày, không thumbnail) chiếm ~⅓. Kiểu báo chính thống, có điểm nhấn; tránh "slop 4 card giống hệt" khi lặp nhiều block (nguyên tắc design-taste anti-slop). Mobile: stack dọc.
*Loại bỏ:* grid 4 card đều nhau (dễ templated khi lặp), list item có thumbnail (rườm rà cạnh ảnh lead).

### C. Category hiển thị (cấu hình, khuyến nghị 4 block)
Mảng mặc định: `['ngoai-hang-anh', 'la-liga', 'champions-league', 'chuyen-nhuong']`. Đặt sau section match-insights. Dễ đổi sau.

### D. Empty handling — ẩn block khi category rỗng
Category 0 bài → **không render block** (không có heading rỗng). 1–4 bài → render lead + list rút gọn theo số bài thực có.

### E. UI: match-existing (design-taste principles, không stack mới)
Bám bộ nhận diện theme (dark/blue, `font-hemi` heading, `.row/.col`, card). Không thêm dependency, JS, motion. Dials thấp.

## Kiến trúc / thành phần

### 1. `inc/query.php` — thêm `bd_category_posts($slug, $n = 5)`
```
WP_Query: post_type=post, category_name=$slug, posts_per_page=$n,
          ignore_sticky_posts=true, no_found_rows=true, orderby=date DESC
```
Trả `WP_Query`. (Không đụng các helper hiện có.)

### 2. `template-parts/category-section.php` (MỚI)
- Nhận slug qua `get_query_var('bd_cat_slug')`.
- Lấy term qua `get_category_by_slug($slug)` (để có tên + link archive). Nếu term không tồn tại → return sớm (không render).
- `$q = bd_category_posts($slug, 5)`. Nếu `!$q->have_posts()` → return sớm (ẩn block).
- Render:
  - **Heading:** `<h2>` tên giải (style `font-hemi ...`) + link "Xem tất cả ›" tới `get_category_link`.
  - **Lead** (bài đầu tiên): ảnh `bd_hero` (nếu có), tiêu đề (link), excerpt, ngày `d/m/Y`.
  - **List** (các bài còn lại, tối đa 4): tiêu đề (link) + ngày; phân cách `divide-y border-card`.
- `wp_reset_postdata()` sau vòng lặp.
- Escape đầu ra: `esc_url`, `esc_html`, `the_title()`/`the_permalink()` (theo convention theme hiện có).

### 3. `front-page.php` — thêm loop block
Sau section match-insights, thêm:
```php
<?php
$bd_home_categories = ['ngoai-hang-anh', 'la-liga', 'champions-league', 'chuyen-nhuong'];
foreach ($bd_home_categories as $bd_cat_slug) :
    set_query_var('bd_cat_slug', $bd_cat_slug);
    get_template_part('template-parts/category-section');
endforeach;
?>
```
Bọc trong `<section><div class="container">...</div></section>` phù hợp.

## Data flow
`front-page` loop slug → `set_query_var('bd_cat_slug')` → `category-section.php` đọc slug → `bd_category_posts($slug)` → render lead + list → `wp_reset_postdata()`. Không API ngoài.

## Error handling
- Term không tồn tại hoặc 0 bài → template-part return sớm, không render (không PHP warning).
- Lead không có ảnh → bỏ khối ảnh, vẫn render tiêu đề + excerpt.
- `wp_reset_postdata()` đảm bảo không rò global `$post` sang block sau.

## Tiêu chí thành công
- [ ] Trang chủ trả HTTP 200, không lỗi PHP.
- [ ] Với category có bài (VD `ngoai-hang-anh`): block hiển thị heading tên giải + link "Xem tất cả" (tới `/{slug}/`) + bài lead (tiêu đề) + (nếu ≥2 bài) list.
- [ ] Category rỗng (VD `champions-league` khi chưa có bài): **không** xuất hiện block/heading nào cho nó.
- [ ] Link "Xem tất cả" và tiêu đề bài trỏ đúng URL.
- [ ] `bd_category_posts` không phá match-insights/hot-slider (mỗi vòng `wp_reset_postdata`).
- [ ] Layout khớp theme (heading `font-hemi`, grid `.row/.col`), không thêm JS/dependency.

## Ngoài phạm vi (YAGNI / sub-project khác)
- "Xem nhiều nhất" (cần view counter), video/highlights.
- Tab lọc giải bằng JS, phân trang trong block, load-more.
- Trang landing giải nâng cao (sub-project 3).
- Thumbnail cho list item.
