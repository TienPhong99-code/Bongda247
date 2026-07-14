# Trang chủ: Dải trận đấu nổi bật (lịch + kết quả 5 giải) — Design

> Thêm 1 dải gọn ở **đầu trang chủ** WordPress `bongda247`: gộp kết quả vừa đá + trận sắp tới của 5 giải VĐQG, sắp theo thời gian quanh hôm nay. Off-season (không có trận) → ẩn hẳn.

## Mục tiêu

- Cho trang chủ một điểm nhấn "thể thao sống" ở đầu trang: các trận **đáng chú ý gần thời điểm hiện tại** (vừa đá + sắp đá) của Ngoại hạng Anh, La Liga, Bundesliga, Serie A, Ligue 1.
- Layout **gọn theo chiều cao**, không làm trang dài. Không phụ thuộc nội dung bài viết (dùng football-data).

## Bối cảnh & ràng buộc

- Data layer SP4 `inc/football-data.php` có sẵn:
  - `BD_FD_LEAGUES` (5 slug→`{code,name}`), `bd_fd_code($slug)`, `bd_fd_league_name($slug)`.
  - `bd_fd_fixtures($code)` → mảng trận cửa sổ **-7..+14 ngày**, cache **3h** stale-while-revalidate; mỗi trận: `utcDate,status,home,homeCrest,away,awayCrest,sh,sa`.
  - `bd_fd_get($key,$ttl,$fetch)` — helper cache; `bd_fd_api()` — gọi API, thiếu key → `null`.
- Trang chủ hiện có 4 section: hot-slider+sidebar · match-insights · lưới "Tin theo giải" · Chuyển nhượng+widget số liệu.
- Theme: `.container`/`.row`/`.col-*`, heading `font-hemi text-2xl uppercase border-l-4 border-brand pl-4`, card `bg-card border-card`, `text-secondary`/`text-brand`. Trang `/lich-thi-dau/?league={slug}` (SP4) hiển thị lịch+KQ đầy đủ 1 giải.
- **Rate limit football-data 10 req/phút.** Dải tái dùng `bd_fd_fixtures()` (đã cache) — không thêm endpoint mới. Cache ấm 0 call; hết hạn tối đa 5 gọi tuần tự (chấp nhận được).

## Quyết định thiết kế (đã chốt qua brainstorming)

| # | Quyết định | Chọn |
|---|-----------|------|
| Nội dung | Hiển thị gì | **Cả hai, gộp theo thời gian** (kết quả vừa đá + trận sắp tới) |
| Nguồn | Mấy giải / lọc | **Gộp cố định 5 giải, tĩnh, không JS/không filter** |
| Bố cục | Kiểu trình bày | **Danh sách dòng ngang gọn** (mỗi trận 1 dòng) |
| Rỗng | Không có trận | **Ẩn hẳn dải** (không render section, không placeholder) |
| Vị trí | Đặt ở đâu | **Đầu trang**, trên section "Tin mới cập nhật" |

## Layout

```
── TRẬN ĐẤU NỔI BẬT                              Xem lịch đầy đủ →
FT     [logo] Arsenal    2–0  Chelsea   [logo]      · NHA
22:00  [logo] Barcelona    –  Real Madrid [logo]    · La Liga    T7 15/03
FT     [logo] Inter      1–1  Milan     [logo]      · Serie A
21:45  [logo] PSG          –  Lyon      [logo]      · Ligue 1    CN 16/03
```

- **Cột trái:** `FT` (badge, trận FINISHED) hoặc giờ `HH:mm` (SCHEDULED/TIMED) theo giờ site (VN).
- **Cột giữa:** logo + tên đội nhà — **tỉ số `sh–sa`** (đã đá) / **`–`** (sắp đá) — logo + tên đội khách.
- **Cột phải:** tên giải (nhỏ, `text-secondary`) + ngày ngắn `d/m` nếu không phải hôm nay.
- **Cả dòng** = link tới `/lich-thi-dau/?league={slug}`.
- Mỗi dòng ngăn nhau bằng `border-t border-card`, padding dọc nhỏ; responsive: mobile ẩn bớt (tên giải/logo) nếu chật, giữ đội + tỉ số/giờ.

## Thuật toán chọn trận (cân bằng quanh "now")

`bd_fd_featured_matches($limit = 8)`:
1. Lặp 5 slug trong `BD_FD_LEAGUES`; với mỗi slug gọi `bd_fd_fixtures(code)`.
2. Gắn vào mỗi trận: `league_slug`, `league_name`.
3. Gộp tất cả trận 5 giải vào 1 mảng.
4. Tách:
   - `finished` = `status === 'FINISHED'`, sắp **giảm dần** theo `utcDate` (gần now nhất trước) → lấy **tối đa `floor($limit/2)`** (=4).
   - `upcoming` = `status ∈ {SCHEDULED, TIMED}`, sắp **tăng dần** theo `utcDate` (gần now nhất trước) → lấy **tối đa `$limit - count(finished_taken)`** (ưu tiên lấp đủ tới `$limit`).
   - Trận `IN_PLAY`/`PAUSED`/khác (đang đá — live ngoài phạm vi) → bỏ qua, không đưa vào dải.
   - Nếu `upcoming` ít hơn phần còn trống, lấy thêm `finished` (đến khi đủ ≤ `$limit`), không vượt `$limit`.
5. Ghép `finished_taken` + `upcoming_taken`, **sắp lại tăng dần theo `utcDate`** để hiển thị (quá khứ → tương lai).
6. Trả mảng ≤ `$limit`. Rỗng → `[]`.

Kết quả: luôn có cả kết quả lẫn lịch khi còn dữ liệu; tổng ≤ 8 dòng.

## Kiến trúc / thành phần

### 1. `inc/football-data.php` (SỬA) — thêm hàm gộp
```php
/**
 * Gộp trận nổi bật 5 giải: tối đa $limit trận cân bằng quanh now
 * (vừa đá + sắp tới), sắp tăng dần theo utcDate để hiển thị.
 * Mỗi phần tử = 1 trận của bd_fd_fixtures() + 'league_slug' + 'league_name'.
 * Tái dùng cache bd_fd_fixtures() — không thêm API endpoint.
 */
function bd_fd_featured_matches($limit = 8) { ... }
```
- Ranh giới: input = `$limit`; output = mảng trận đã gắn league + đã chọn/sắp. Không in HTML, không side-effect ngoài cache sẵn có của `bd_fd_fixtures`.

### 2. `template-parts/featured-matches.php` (MỚI) — dải hiển thị
- Gọi `bd_fd_featured_matches()`. **Rỗng → `return;` ngay, không in gì** (kể cả `<section>`).
- Tự bọc `<section><div class="container">…</div></section>` (để khi rỗng ẩn hoàn toàn).
- Heading "Trận đấu nổi bật" + link "Xem lịch đầy đủ →" (`/lich-thi-dau/`).
- Lặp render mỗi trận 1 dòng theo layout trên. Escape đầu ra: `esc_url` (crest, link), `esc_html` (tên đội/giải/tỉ số/giờ).
- Giờ: `get_date_from_gmt($utcDate)` → format `HH:mm` (trạng thái sắp đá) và `d/m` (ngày, nếu != hôm nay). `FT` cho FINISHED.

### 3. `front-page.php` (SỬA)
Thêm ở đầu, ngay sau `get_header()`, **trước** section "Tin mới cập nhật":
```php
<?php get_template_part('template-parts/featured-matches'); ?>
```
Không bọc markup ngoài (section nằm trong template part → rỗng thì ẩn sạch).

## Data flow

front-page → featured-matches.php → `bd_fd_featured_matches(8)` → lặp 5× `bd_fd_fixtures(code)` (cache 3h; chia sẻ cache NHA với widget số liệu) → gộp/tách/chọn/sắp → render dòng. Cache ấm 0 call; hết hạn tối đa 5 gọi tuần tự (SP4 stale-while-revalidate).

## Error handling

- Thiếu `FOOTBALL_DATA_KEY` / API lỗi → `bd_fd_fixtures` trả `[]` → `bd_fd_featured_matches` trả `[]` → **ẩn dải**.
- 0 trận trong cửa sổ -7..+14 ngày (off-season) → `[]` → **ẩn dải**.
- Trận thiếu logo (`homeCrest`/`awayCrest` rỗng) → không in `<img>` (fallback: chỉ tên đội).
- `utcDate` rỗng/không parse được → bỏ qua trận đó (không đưa vào danh sách).

## Kiểm thử

Theme PHP không có test harness (test suite chỉ cho bot Node). Verify thủ công:
- [ ] **Off-season hiện tại:** trang chủ HTTP 200, 0 lỗi PHP, **dải KHÔNG xuất hiện** (đầu trang là "Tin mới cập nhật" như cũ).
- [ ] **Có dữ liệu (prime cache):** nạp tạm `update_option('bd_fd_fixtures_PL', [...mẫu...])` + `set_transient('bd_fd_fresh_fixtures_PL',1,…)` cho vài giải (gồm cả trận FINISHED + SCHEDULED, ngày quanh now) → reload → dải hiện đúng thứ tự thời gian, FT+tỉ số cho trận đã đá, HH:mm cho trận sắp đá, link đúng `/lich-thi-dau/?league=…`. Chụp màn hình. Xoá option/transient tạm sau khi verify.
- [ ] **Giới hạn:** dữ liệu > 8 trận → chỉ hiện ≤ 8 dòng, cân bằng vừa đá/sắp tới.
- [ ] **Thiếu logo:** trận không crest vẫn render tên đội, không vỡ layout.

## Tiêu chí thành công

- [ ] Đầu trang chủ có dải "Trận đấu nổi bật" khi còn dữ liệu; **ẩn hoàn toàn** khi không có trận.
- [ ] Mỗi trận 1 dòng: trạng thái (FT/giờ) · đội nhà · tỉ số/`–` · đội khách · giải (+ngày ngắn).
- [ ] Gộp 5 giải, ≤ 8 dòng, sắp theo thời gian; cân bằng vừa đá + sắp tới.
- [ ] Không thêm API endpoint mới; rate limit an toàn (tái dùng cache fixtures).
- [ ] Toàn bộ dữ liệu ngoài được escape; cả dòng link tới trang lịch giải tương ứng.

## Ngoài phạm vi

- Live real-time / auto-refresh; lọc theo giải (dropdown/tab); Champions League (bảng nhóm); trang chi tiết từng trận; prime cache bằng cron.
