# Search + Mobile Nav + Archive nâng cao — Design

> **Sub-project 3** trong loạt "thêm section/page mới" cho WordPress theme `bongda247`. Sub-project 4 (dữ liệu bóng đá: BXH, lịch, tỷ số) có spec riêng, làm sau.

## Mục tiêu

Nâng cấp điều hướng + tìm kiếm + trang chuyên mục của theme: (1) thêm **tìm kiếm** (icon header + trang kết quả), (2) **mobile nav** (hamburger — hiện mobile không có menu), (3) **league landing nâng cao** (mô tả giải trên archive), (4) **polish trang tag**. Tất cả là theme UI, dùng WP core, không API ngoài.

## Bối cảnh & ràng buộc

- `header.php`: nav 3 link hardcode (NHA, Chuyển nhượng, La Liga) chỉ hiện từ `lg`; **mobile không có nav**; **không có ô search**; có `theme-toggle`.
- `archive.php`: archive tổng quát (title + grid card + `paginate_links` + empty). **Tag archive đã render qua file này** (WP fallback tag.php → archive.php). Chỉ hiện title, chưa có mô tả term.
- `index.php`: fallback cho search → title generic "Tin tức", không form/không số kết quả.
- JS theme ở `src/main.js` → build ra `dist/main.js`; CSS `src/main.css` → `dist/main.css` (Tailwind v4 CLI). Class mới trong PHP/JS phải rebuild.
- Categories có field `description` (WP native), sửa trong WP admin.
- Grid card sẵn có (archive.php): `rounded-lg bg-card border border-card`, thumbnail `bd_hero aspect-video`, title `line-clamp-2`, `hover:text-brand`.

## Quyết định thiết kế

### A. Search — icon xổ ô (desktop) + `search.php` + form trong mobile panel
Header có **icon 🔍**; bấm → xổ form GET (`method=get action=home name="s"`). `search.php` render trang kết quả (tái dùng card của archive). Không dùng `get_search_form()` mặc định của WP (khó style) — dùng form tự viết bám theme.
*Loại bỏ:* ô search luôn hiện (chiếm chỗ header), live-search/ajax (YAGNI).

### B. Mobile nav — nút hamburger xổ panel
`lg:hidden` hamburger `☰` → toggle panel chứa: link các giải (nav) + ô search + link trang tĩnh. JS thuần trong `src/main.js`.
*Loại bỏ:* off-canvas drawer phức tạp, dependency menu.

### C. Nav = 4 giải (đồng bộ trang chủ), dạng mảng cấu hình
Nav header + mobile panel dùng mảng `['ngoai-hang-anh','la-liga','champions-league','chuyen-nhuong']` (khớp `$bd_home_categories` của front-page về mặt giá trị). Desktop hiện inline (lg+), mobile trong panel.

### D. League landing — mô tả term trên archive
`archive.php`: nếu `term_description()` không rỗng → render dưới tiêu đề (trong `.prose-bd` hoặc `text-secondary`). Guard nếu rỗng. (BXH/lịch để SP4.)

### E. Tag polish — prefix tiêu đề
`archive.php`: nếu `is_tag()` → tiêu đề "**Chủ đề: {tên tag}**"; category giữ nguyên tên giải; các archive khác dùng `get_the_archive_title()`.

### F. UI match-existing
Bám theme (dark/blue, `font-hemi`, card, `text-secondary`/`text-brand`). JS thuần trong `main.js` (không thêm dependency/motion nặng). Icon: SVG inline đơn giản (kính lúp, hamburger, X) — theme chưa dùng thư viện icon.

## Kiến trúc / thành phần

### 1. `search.php` (MỚI)
- Tiêu đề: `Kết quả tìm kiếm cho: "<?php echo esc_html(get_search_query()); ?>"`.
- Số kết quả: `$wp_query->found_posts`.
- `if (have_posts())`: grid card (giống `archive.php`) + `paginate_links`.
- `else`: "Không tìm thấy bài viết nào cho '{query}'." + form search để thử lại.

### 2. `header.php` (SỬA)
- Thêm **icon search** (button) cạnh theme-toggle; **form search** ẩn (toggle bằng JS, id `bd-search`).
- Thêm **hamburger** (button, `lg:hidden`); **mobile panel** ẩn (toggle JS, id `bd-mobile-menu`) chứa nav 4 giải + form search + link trang tĩnh.
- Nav desktop: đổi mảng `$nav` sang 4 giải (thêm Champions League).
- Icon SVG inline (search, hamburger, close).

### 3. `src/main.js` (SỬA) → rebuild `dist/main.js`
- Toggle form search (icon click → thêm/bỏ class ẩn; focus input khi mở).
- Toggle mobile panel (hamburger → mở; nút X/chọn link → đóng).
- Giữ nguyên logic theme-toggle + Swiper init hiện có.

### 4. `archive.php` (SỬA)
- Tiêu đề: `is_tag()` → "Chủ đề: {tên}"; ngược lại giữ `single_term_title() ?: get_the_archive_title()`.
- Sau tiêu đề: nếu `term_description()` không rỗng → render mô tả.

### 5. `dist/main.css` — rebuild nếu có class Tailwind mới (panel, form, icon).

## Data flow
- Search: form GET `?s=query` → WP main query → `search.php` render `have_posts()` + `paginate_links`.
- Mobile nav / search toggle: thuần client-side (JS class toggle), không request.
- Archive description: đọc `term_description()` của term đang xem.

## Error handling
- Search rỗng/không kết quả → nhánh `else` với thông báo + form thử lại.
- `term_description()` rỗng → không render khối mô tả (không div rỗng).
- JS: guard `if (el)` trước khi gắn listener (tránh lỗi khi element không tồn tại).

## Tiêu chí thành công
- [ ] Header desktop: icon 🔍 bấm xổ ô nhập; nhập từ khoá + submit → tới `/?s=...` trả HTTP 200 qua `search.php`, tiêu đề "Kết quả tìm kiếm cho: ...".
- [ ] Search có kết quả → grid bài; không kết quả → thông báo "Không tìm thấy...".
- [ ] Mobile (< lg): nút ☰ bấm xổ panel có 4 link giải + ô search + link trang tĩnh; bấm lại/chọn link → đóng.
- [ ] Archive category có mô tả → hiện mô tả dưới tiêu đề; không mô tả → không hiện khối rỗng.
- [ ] Trang tag: tiêu đề "Chủ đề: {tag}".
- [ ] Không lỗi PHP/JS console; không thêm dependency; `dist/main.js`/`dist/main.css` rebuild & commit.

## Ngoài phạm vi (YAGNI / SP4)
- BXH, lịch thi đấu, tỷ số trực tiếp (SP4).
- Search nâng cao (lọc theo giải, gợi ý, live-search/ajax, phân trang ajax).
- Off-canvas drawer, thư viện icon, mega-menu.
