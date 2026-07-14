# SEO-SP1: Hub pages — Nhận định + Kết quả — Design

> Thêm 2 trang landing hút search cho `bongda247`: **`/nhan-dinh/`** (Nhận định bóng đá — kết hợp CPT match_insight sắp tới + bài phân tích đầy đủ) và **`/ket-qua-bong-da/`** (Kết quả bóng đá — trận FINISHED 5 giải, nhóm theo ngày). Bám pattern page-template hiện có.

## Mục tiêu

- Lấp khoảng trống SEO lớn nhất: CPT `match_insight` chỉ có bài lẻ, **chưa có trang hub** ("nhận định/soi kèo bóng đá hôm nay") → `/nhan-dinh/` hiện 404.
- Thêm trang **Kết quả bóng đá** ("kết quả bóng đá hôm nay") gộp 5 giải.
- Tăng internal linking (nav + link chéo) — tốt cho SEO.

## Bối cảnh & ràng buộc

- Pattern trang có sẵn: WP Page + `page-{slug}.php` (VD `page-bang-xep-hang.php` slug `bang-xep-hang`, `page-lich-thi-dau.php` slug `lich-thi-dau`, đọc `?league=`).
- CPT `match_insight`: `public=true`, `has_archive=false`, ephemeral (auto-xoá 3h sau trận). Fields: `home_team, away_team, match_time (HH:mm - DD/MM), match_date (ISO UTC), hot (int), insights (array), prediction`. Query qua **`bd_insights($n)`** (inc/query.php) — trả `WP_Query`, đã lọc trận hết hạn, sắp theo hot + thời gian. Card carousel render ở `template-parts/match-insights.php`.
- Bài phân tích đầy đủ (bot Luồng 6) = `post` thường trong category giải đấu — **chưa gom riêng**.
- Data layer `inc/football-data.php`: `bd_fd_fixtures($code)` → trận -7..+14 ngày (cache 3h), mỗi trận `{utcDate,status,home,homeCrest,away,awayCrest,sh,sa}`; `BD_FD_LEAGUES` (5 giải); vừa thêm `bd_fd_featured_matches()`. Row style dòng trận ở `template-parts/featured-matches.php`.
- Theme: `.container`, heading `font-hemi text-2xl uppercase border-l-4 border-brand pl-4`, `bg-card`/`border-card`, `text-secondary`/`text-brand`. Giờ theo site: `wp_date($fmt,$ts)`.
- **Rate limit football-data 10 req/phút** → tái dùng cache `bd_fd_fixtures`, KHÔNG thêm endpoint.

## Quyết định thiết kế (chốt qua brainstorming)

| # | Quyết định | Chọn |
|---|-----------|------|
| Hub Nhận định | Liệt kê gì | **Kết hợp**: trên = cards CPT sắp tới; dưới = bài phân tích đầy đủ (tag `nhan-dinh`) |
| Bài phân tích | Cách gom | **Tag `nhan-dinh`** (không dùng category — tránh đụng slug trang; noindex tag archive qua RankMath → hub là canonical) |
| Kết quả | Sắp xếp | **Nhóm theo ngày** giảm dần (Hôm nay / Hôm qua / dd/mm), gộp 5 giải |
| Trang rỗng | Off-season | Nhận định: ẩn từng khối rỗng, vẫn giữ heading + hướng dẫn. Kết quả: hiện thông báo "chưa có kết quả" (KHÔNG ẩn trang) |

## Kiến trúc / thành phần

### 1. `inc/football-data.php` (SỬA) — thêm hàm gom kết quả theo ngày
```php
/**
 * Kết quả (FINISHED) 5 giải trong $days ngày gần nhất, nhóm theo ngày (giờ site) giảm dần.
 * Trả mảng nhóm: [ ['label'=>'Hôm nay'|'Hôm qua'|'dd/mm', 'date'=>'Y-m-d', 'matches'=>[...]], ... ]
 * Mỗi match = 1 trận bd_fd_fixtures() + 'ts' (unix) + 'league_slug' + 'league_name'.
 * Tái dùng cache bd_fd_fixtures() — không thêm API.
 */
function bd_fd_results_by_date($days = 7) { ... }
```
- Logic: lặp `BD_FD_LEAGUES` → `bd_fd_fixtures(code)` → lọc `status==='FINISHED'` & `ts >= now - $days*DAY` → gắn `ts/league_slug/league_name` → group theo `wp_date('Y-m-d',$ts)` → sort nhóm date desc, trong nhóm sort ts desc → gắn `label` (so với hôm nay/hôm qua site).

### 2. `template-parts/insight-card.php` (MỚI) — 1 card nhận định (DRY)
- Tách markup 1 card từ `match-insights.php` (đọc `get_the_ID()` trong vòng lặp `the_post()`): 2 đội, `match_time`, `prediction`, `insights` (list), cờ `hot`. Escape toàn bộ.
- Dùng lại được ở carousel trang chủ (tùy chọn refactor — KHÔNG bắt buộc trong SP này để tránh phình; ở đây chỉ tạo file + dùng cho page-nhan-dinh).

### 3. `page-nhan-dinh.php` (MỚI) — template trang Nhận định
- Heading trang "Nhận định bóng đá".
- **Khối trên:** `$q = bd_insights(8)`; nếu có bài → lưới cards (`insight-card.php`), tiêu đề phụ "Nhận định trận sắp tới"; rỗng → ẩn khối.
- **Khối dưới:** `WP_Query(['tag'=>'nhan-dinh','posts_per_page'=>8,'ignore_sticky_posts'=>true])`; có bài → lưới bài (ảnh `bd_hero` + tiêu đề + excerpt + ngày, link bài); rỗng → ẩn khối.
- Cả hai rỗng → hiện đoạn "Chưa có nhận định — quay lại sau khi có trận sắp diễn ra."
- `wp_reset_postdata()` sau mỗi vòng lặp.

### 4. `page-ket-qua.php` (MỚI) — template trang Kết quả
- Heading "Kết quả bóng đá".
- `$groups = bd_fd_results_by_date(7)`; rỗng → `<p>` "Chưa có kết quả trong 7 ngày qua."
- Mỗi nhóm: heading ngày (`label`) + list dòng trận (tái dùng style dòng `featured-matches.php`): `FT` · logo+đội nhà · **tỉ số `sh–sa`** · logo+đội khách · giải; cả dòng link `/lich-thi-dau/?league={league_slug}`. Escape toàn bộ.

### 5. `header.php` (SỬA) — nav
- Thêm 2 link vào menu chính: **Nhận định** (`/nhan-dinh/`) + **Kết quả** (`/ket-qua-bong-da/`), cạnh BXH/Lịch. Dùng `home_url()`.

### 6. Tạo dữ liệu WP (bước triển khai, qua wp-cli)
- WP Page "Nhận định bóng đá" slug `nhan-dinh` (status publish).
- WP Page "Kết quả bóng đá" slug `ket-qua-bong-da` (status publish).
- Tag `nhan-dinh` (name "Nhận định", taxonomy post_tag).

## Phụ thuộc bot (follow-up, KHÔNG chặn SP này)
- Bot Luồng 6 (`generateMatchArticle` → tạo post): thêm **tag `nhan-dinh`** khi đăng bài nhận định (qua `lib/wp.js`, param tags). Ghi lại làm task nhỏ riêng. Hub degrade tốt: khối "bài phân tích" chỉ ẩn tới khi có bài gắn tag.

## Data flow

- `/nhan-dinh/` → `bd_insights(8)` (CPT, đã lọc hạn) + `WP_Query tag=nhan-dinh`. Không API football-data.
- `/ket-qua-bong-da/` → `bd_fd_results_by_date(7)` → 5× `bd_fd_fixtures(code)` (cache 3h, chia sẻ cache với dải featured + widget). Cache ấm 0 call.

## Error handling

- Thiếu `FOOTBALL_DATA_KEY`/API lỗi/off-season → `bd_fd_results_by_date` trả `[]` → trang Kết quả hiện thông báo (không vỡ).
- CPT rỗng (không trận sắp tới) → ẩn khối trên trang Nhận định.
- Tag `nhan-dinh` chưa có bài → ẩn khối dưới.
- Trận thiếu logo → không in `<img>` (chỉ tên đội). `utcDate` không parse → bỏ trận.
- Escape mọi dữ liệu ngoài: `esc_url` (crest/link), `esc_html` (tên/tỉ số/giờ/ngày).

## Kiểm thử (thủ công — theme PHP không có harness)

- [ ] Tạo 2 Page + tag qua wp-cli. `curl` `/nhan-dinh/` & `/ket-qua-bong-da/` → HTTP 200, 0 lỗi PHP.
- [ ] **Kết quả có dữ liệu:** prime cache `bd_fd_fixtures_*` với trận FINISHED nhiều ngày (2-3 giải) → `/ket-qua-bong-da/` nhóm đúng theo ngày (Hôm nay/Hôm qua/dd-mm), tỉ số đúng, link đúng slug. Off-season (dọn cache) → thông báo "chưa có kết quả".
- [ ] **Nhận định có dữ liệu:** seed 1-2 `match_insight` (match_date tương lai) + 1 `post` gắn tag `nhan-dinh` → `/nhan-dinh/` hiện cả 2 khối; xoá seed → hiện đoạn "chưa có nhận định".
- [ ] Playwright chụp 2 trang. Nav header có 2 link mới, bấm ra đúng trang.

## Tiêu chí thành công

- [ ] `/nhan-dinh/` & `/ket-qua-bong-da/` HTTP 200 (không còn 404), có trong nav.
- [ ] Nhận định: khối cards CPT sắp tới + khối bài tag `nhan-dinh`, mỗi khối tự ẩn khi rỗng, có fallback khi cả hai rỗng.
- [ ] Kết quả: trận FINISHED 5 giải nhóm theo ngày giảm dần, tỉ số + giải + link; thông báo khi rỗng.
- [ ] Không thêm API endpoint; escape đầy đủ; giờ theo `wp_date`.

## Ngoài phạm vi

- SportsEvent schema (SEO-SP2/schema riêng); refactor carousel trang chủ dùng `insight-card.php`; trang đội bóng; trang BXH tổng; sửa bot Luồng 6 (follow-up); noindex tag archive (config RankMath).
