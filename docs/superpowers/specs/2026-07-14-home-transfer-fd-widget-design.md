# Trang chủ: Section Chuyển nhượng + Widget số liệu (BXH/Lịch/KQ) — Design

> Thêm vào trang chủ WordPress `bongda247`: 1 section 2 cột — tin Chuyển nhượng (trái) + widget số liệu compact có chọn giải (phải). Gọn, không làm trang dài quá.

## Mục tiêu

- Đưa **Chuyển nhượng** trở lại trang chủ (đã bỏ khỏi lưới "TIN THEO GIẢI ĐẤU").
- Thêm **BXH + Lịch + Kết quả** dạng **compact** (không dài): widget 1 tab hiện/lần, có **nút chọn giải** (5 giải VĐQG).

## Bối cảnh & ràng buộc

- Trang chủ hiện: hot-slider+sidebar · match-insights · lưới "TIN THEO GIẢI ĐẤU" (3 giải, `category-column.php`).
- SP4 có sẵn: `BD_FD_LEAGUES`, `bd_fd_code($slug)`, `bd_fd_standings($code)` (mảng ≤20 hàng, cache 6h), `bd_fd_fixtures($code)` (mảng trận cửa sổ -7..+14 ngày, cache 3h, mỗi trận có `status`/`sh`/`sa`).
- `bd_category_posts($slug, $n)` (inc/query.php) — bài theo category.
- Theme: `.container`/`.row`/`.col-8`/`.col-4`, heading `font-hemi ... border-l-4 border-brand`, card, `text-secondary`/`text-brand`. JS thuần trong `src/main.js` (event-delegation).
- **Rate limit football-data 10 req/phút** → KHÔNG render sẵn cả 5 giải (10 call/trang). Chỉ tải giải đang xem.

## Quyết định thiết kế

### A. 1 section 2 cột
Cột trái `col-8` = Chuyển nhượng; cột phải `col-4` = widget số liệu. Đặt SAU lưới "TIN THEO GIẢI ĐẤU", trước footer.

### B. Widget số liệu = dropdown giải + 3 tab (BXH/Lịch/KQ), AJAX đổi giải
- **Dropdown chọn giải** (`<select>`, 5 giải, mặc định `ngoai-hang-anh`).
- **3 tab** BXH | Lịch | Kết quả — JS thuần bật/tắt panel (1 tab hiện).
- **Đổi giải = AJAX** (`admin-ajax.php`, action `bd_fd_widget`): tải widget-body của giải chọn, JS thay `[data-fd-body]`, KHÔNG reload trang, KHÔNG load sẵn 5 giải. → mỗi lần chỉ 2 API call (có cache SP4).
- Trang tải lần đầu: **server-render sẵn giải mặc định** (2 call).
*Loại bỏ:* render sẵn 5 giải (10 call, chậm + vỡ rate limit); reload cả trang khi đổi giải.

### C. Compact — số dòng giới hạn
- BXH: **top 8** hàng, cột gọn (Hạng · Đội · Điểm) + "Xem đầy đủ →" `/bang-xep-hang/?league={slug}`.
- Lịch: **5 trận sắp tới** (lọc `status` ∈ SCHEDULED/TIMED, sắp xếp ngày tăng).
- Kết quả: **5 trận gần nhất** (lọc `status` = FINISHED, sắp xếp ngày giảm).

### D. Chuyển nhượng (cột trái)
Heading "Chuyển nhượng" + "Xem tất cả →" (`/chuyen-nhuong/`). 1 bài lead (ảnh `bd_hero` + tiêu đề + excerpt) + list 4–5 tin (tiêu đề + ngày). `bd_category_posts('chuyen-nhuong', 6)`. Rỗng → ẩn cột (hoặc thông báo).

### E. UI match-existing, JS thuần
Bám theme (dark/blue, card, font-hemi). Không thêm dependency; AJAX qua `fetch` + admin-ajax (WP core). Escape đầu ra (data ngoài từ API → `esc_html`/`esc_url`; input `$_GET['league']` → `sanitize_key` + validate BD_FD_LEAGUES).

## Kiến trúc / thành phần

### 1. `template-parts/transfer-list.php` (MỚI) — cột Chuyển nhượng
Đọc `get_query_var('bd_cat_slug')` (= 'chuyen-nhuong'); `bd_category_posts($slug, 6)`; render heading + lead + list. Rỗng → return.

### 2. `template-parts/fd-widget.php` (MỚI) — khung widget
- Heading "Số liệu bóng đá".
- `<select data-fd-league>` (5 giải, `selected` = giải hiện tại) + attribute `data-fd-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"`.
- `<div data-fd-body>` include `fd-widget-body.php` cho giải mặc định.

### 3. `template-parts/fd-widget-body.php` (MỚI) — phần đổi theo giải (AJAX render lại phần này)
Đọc `get_query_var('bd_fd_widget_slug')`. Tính:
- `$code = bd_fd_code($slug)`; `$rows = array_slice(bd_fd_standings($code), 0, 8)`.
- `$fx = bd_fd_fixtures($code)`; tách `$upcoming` (SCHEDULED/TIMED, ≤5) + `$results` (FINISHED, ≤5, đảo mới nhất).
Render: 3 nút tab (`data-fd-tab=bxh|lich|kq`) + 3 panel (`data-fd-panel=...`), panel BXH hiện mặc định, 2 panel kia `hidden`. Mỗi panel compact + link "Xem đầy đủ →".

### 4. `functions.php` (SỬA) — AJAX handler
```
add_action('wp_ajax_bd_fd_widget', 'bd_fd_widget_ajax');
add_action('wp_ajax_nopriv_bd_fd_widget', 'bd_fd_widget_ajax');
function bd_fd_widget_ajax() {
  $req  = isset($_GET['league']) ? sanitize_key(wp_unslash($_GET['league'])) : '';
  $slug = array_key_exists($req, BD_FD_LEAGUES) ? $req : 'ngoai-hang-anh';
  set_query_var('bd_fd_widget_slug', $slug);
  get_template_part('template-parts/fd-widget-body');
  wp_die();
}
```

### 5. `src/main.js` (SỬA) → rebuild
- `change` trên `[data-fd-league]`: `fetch(ajaxUrl + '?action=bd_fd_widget&league=' + slug)` → thay `innerHTML` của `[data-fd-body]` → reset tab về BXH.
- `click` trên `[data-fd-tab]`: hiện `[data-fd-panel=<tab>]`, ẩn panel khác, đánh dấu tab active.
- Giữ nguyên các handler cũ (theme-toggle, search, menu, Swiper).

### 6. `front-page.php` (SỬA)
Thêm section 2 cột sau lưới "TIN THEO GIẢI ĐẤU":
```
<section><div class="container"><div class="row">
  <div class="col col-8"> set_query_var('bd_cat_slug','chuyen-nhuong'); get_template_part('transfer-list'); </div>
  <div class="col col-4"> set_query_var('bd_fd_widget_slug','ngoai-hang-anh'); get_template_part('fd-widget'); </div>
</div></div></section>
```

## Data flow
front-page → transfer-list (posts) + fd-widget (server-render NHA: standings+fixtures = 2 call, cache). JS: dropdown → AJAX (admin-ajax → fd-widget-body giải mới, 2 call cache) → thay body; tab → toggle client. Rate limit an toàn.

## Error handling
- API lỗi/thiếu key → `bd_fd_standings`/`fixtures` trả [] → panel "Chưa có dữ liệu ...".
- `$_GET['league']` sai → default NHA (validate BD_FD_LEAGUES).
- AJAX fail (network) → widget giữ nội dung cũ; JS `catch` không vỡ.
- Chuyển nhượng 0 bài → ẩn cột.

## Tiêu chí thành công
- [ ] Trang chủ HTTP 200, 0 lỗi PHP; xuất hiện 1 section "Chuyển nhượng" (cột trái) + "Số liệu bóng đá" (cột phải).
- [ ] Widget hiện BXH giải mặc định (top ≤8) + 3 tab + dropdown 5 giải; tab đổi client-side.
- [ ] `admin-ajax.php?action=bd_fd_widget&league=la-liga` trả HTML widget-body La Liga (có tên đội La Liga), HTTP 200.
- [ ] Đổi giải trên UI (dropdown) → widget đổi data qua AJAX, KHÔNG reload.
- [ ] Cột Chuyển nhượng hiện tin (lead + list) linh `/chuyen-nhuong/`.
- [ ] Rate limit: trang chủ ≤2 call; đổi giải ≤2 call; đổi tab 0 call.

## Ngoài phạm vi
- Live real-time; H2H; Champions League (bảng nhóm); đổi các section khác của trang chủ.
