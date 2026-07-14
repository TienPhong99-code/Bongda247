# Gộp block tin theo giải thành 1 section lưới 3 cột — Design

> Redesign UI cho phần "tin theo giải" trên trang chủ (thay layout SP2). Nhỏ, 1 nhánh.

## Mục tiêu

Gộp 4 section full-width "tin theo giải" (SP2) thành **1 section "TIN THEO GIẢI ĐẤU"** dạng lưới 3 cột (NHA · La Liga · Champions League) cho gọn — mỗi cột: tên giải + 1 ảnh+tiêu đề + 2 tin phụ (bullet), như ảnh tham chiếu người dùng cung cấp.

## Bối cảnh

- Hiện: `front-page.php` loop `$bd_home_categories = ['ngoai-hang-anh','la-liga','champions-league','chuyen-nhuong']` → mỗi giải 1 section full-width `template-parts/category-section.php` (lead `col-8` + list `col-4`), xếp dọc.
- `category-section.php` CHỈ front-page dùng → thay xong sẽ xoá.
- Helper `bd_category_posts($slug, $n)` (inc/query.php) giữ nguyên, tái dùng.
- Theme: `.container`, heading `font-hemi text-2xl uppercase border-l-4 border-brand pl-4`, card/`border-card`/`text-secondary`/`text-brand`, image size `bd_hero`.

## Quyết định thiết kế

### A. 1 section + lưới 3 cột, config 3 slug
`front-page.php`: 1 `<section>` với 1 tiêu đề chung "TIN THEO GIẢI ĐẤU" + `grid grid-cols-1 md:grid-cols-3 gap-6`. Mảng `$bd_home_categories = ['ngoai-hang-anh', 'la-liga', 'champions-league']` (3 giải, bỏ Chuyển nhượng khỏi section này).

### B. Template-part cột compact (thay lead+list full-width)
`template-parts/category-column.php` (MỚI): 1 cột giải = **tên giải** (link archive, chữ nhỏ uppercase `text-brand`) → **bài lead** (ảnh `bd_hero` + tiêu đề đậm, link) → **2 tin phụ** (tiêu đề link, bullet `▪`). Dùng `bd_category_posts($slug, 3)` (1 lead + 2 phụ). Nhận slug qua `get_query_var('bd_cat_slug')`. Cột rỗng (0 bài) → return, ẩn cột.

### C. Xoá `category-section.php`
Không còn ai dùng → `git rm`.

### D. Responsive + build
Mobile: `grid-cols-1` (stack). Class lưới mới → rebuild `dist/main.css`.

## Thành phần
- Sửa: `front-page.php` (thay khối loop bằng section lưới).
- Tạo: `template-parts/category-column.php`.
- Xoá: `template-parts/category-section.php`.
- Rebuild: `dist/main.css`.

## Tiêu chí thành công
- [ ] Trang chủ HTTP 200, KHÔNG lỗi PHP.
- [ ] 1 section duy nhất "TIN THEO GIẢI ĐẤU" thay cho 4 section rời; bên trong lưới 3 cột NHA/La Liga/CL.
- [ ] Mỗi cột (giải có bài): tên giải + 1 ảnh+tiêu đề + ≤2 bullet. Cột giải 0 bài (VD Champions League chưa có bài) → ẩn.
- [ ] Link tên giải + tiêu đề trỏ đúng.
- [ ] Mobile: 3 cột stack dọc.
- [ ] `category-section.php` đã xoá; không còn tham chiếu.

## Ngoài phạm vi
- Chuyển nhượng (bỏ khỏi section này; vẫn ở nav + archive).
- Các section khác trang chủ (hot slider, sidebar, match insights) — giữ nguyên.
- Thumbnail cho bullet; "xem nhiều nhất".
