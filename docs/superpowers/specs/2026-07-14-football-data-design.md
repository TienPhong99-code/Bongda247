# Dữ liệu bóng đá (BXH + Lịch thi đấu) — Design

> **Sub-project 4** (cuối) trong loạt "thêm section/page mới" cho WordPress theme `bongda247`.

## Mục tiêu

Hiển thị **bảng xếp hạng** + **lịch thi đấu** của 5 giải VĐQG (NHA, La Liga, Bundesliga, Serie A, Ligue 1) trên: (a) archive từng giải, và (b) trang riêng `/bang-xep-hang/` + `/lich-thi-dau/`. Dữ liệu từ football-data.org, WP tự fetch + cache.

## Bối cảnh & ràng buộc

- football-data.org: base `https://api.football-data.org/v4`, header `X-Auth-Token`, per-competition. Free plan **10 req/phút**, không H2H, không push real-time.
- Endpoint: `/competitions/{code}/standings` (BXH ở `standings[0].table`) + `/competitions/{code}/matches` (lịch/kết quả).
- Bot đã dùng API này (`web/bot-press.js`, env `PUBLIC_FOOTBALL_DATA_KEY`) — **KHÔNG đụng bot**; SP4 tự chứa trong WP.
- Theme: archive.php (grid tin), header.php (nav), grid/card + `.container`/`.row` sẵn có. Image sizes `bd_hero`/`bd_thumb`.
- Trang tĩnh SP1 dùng `page.php`; page template theo slug `page-{slug}.php` được WP ưu tiên.

## Quyết định thiết kế

### A. WP tự fetch + cache stale-while-revalidate (không đụng bot)
Theme gọi API qua `wp_remote_get`, cache: `option` (data bền) + `transient` (cờ tươi theo TTL). Tươi → trả option; hết hạn → fetch, thành công cập nhật cả hai, **hỏng API → phục vụ data cũ** (option). Tự chứa, deploy = upload theme + set 1 hằng key.
*Loại bỏ:* bot đẩy data (phải sửa bot-press.js/wp.js, couple site vào bot); gọi API mỗi page-load không cache (vỡ rate limit).

### B. 5 giải VĐQG, map slug→code
`ngoai-hang-anh→PL`, `la-liga→PD`, `bundesliga→BL1`, `serie-a→SA`, `ligue-1→FL1`. Bỏ Champions League (BXH bảng nhóm/knockout phức tạp — ngoài phạm vi).

### C. API key qua hằng `FOOTBALL_DATA_KEY` (wp-config)
Đọc từ `FOOTBALL_DATA_KEY` định nghĩa trong `wp-config.php` (KHÔNG commit). Thiếu key → feature trả rỗng, ẩn section (degrade, không fatal). Local test: lấy giá trị từ `web/.env` (`PUBLIC_FOOTBALL_DATA_KEY`) đặt vào wp-config Local.

### D. Hiển thị: nhúng archive + trang riêng (tab giải)
- Archive giải: dưới lưới tin, thêm BXH + lịch của giải đó (chỉ khi slug map ra code).
- Trang riêng `/bang-xep-hang/`, `/lich-thi-dau/`: tab chọn giải (link `?league=<slug>`, server-render, KHÔNG JS), mặc định NHA; mỗi lần xem chỉ tải 1 giải → ≤2 API call.
- Nav header + mobile: thêm "BXH" + "Lịch".

### E. UI match-existing
Bám theme (dark/blue, `font-hemi`, card, `.container`). BXH = bảng scroll ngang mobile. Không thêm dependency/JS (tab = link).

## Kiến trúc / thành phần

### 1. `inc/football-data.php` (MỚI) — lớp dữ liệu
- `BD_FD_LEAGUES` = map slug → `{ code, name }` (5 giải).
- `bd_fd_code($slug)` → code | null.
- `bd_fd_get($key, $ttl, callable $fetch)` — stale-while-revalidate (option `bd_fd_{key}` + transient `bd_fd_fresh_{key}`).
- `bd_fd_api($path)` — `wp_remote_get` + `X-Auth-Token`, timeout 10s, trả mảng decode hoặc null (lỗi/thiếu key).
- `bd_fd_standings($code)` → `$fetch` gọi `/competitions/{code}/standings`, map `standings[0].table[]` → `{position, name, crest, playedGames, won, draw, lost, goalDifference, points}`. TTL 6h.
- `bd_fd_fixtures($code)` → `/competitions/{code}/matches`, lọc: N kết quả gần nhất (FINISHED) + N trận sắp tới (SCHEDULED/TIMED) → `{utcDate, status, home, away, scoreHome, scoreAway}`. TTL 3h.
- `functions.php`: `require inc/football-data.php`.

### 2. template-parts (MỚI)
- `standings-table.php` — nhận `$rows`; render `<table>`: Hạng · Đội (crest+tên) · Trận · T · H · B · HS · Điểm. Wrapper `overflow-x-auto` cho mobile. Rỗng → không render.
- `fixtures-list.php` — nhận `$matches`; nhóm theo ngày (`d/m`), mỗi trận: home — [tỉ số | giờ HH:mm] — away. Rỗng → không render.

### 3. `archive.php` (SỬA)
Sau lưới tin: `if (is_category() && $code = bd_fd_code(get_queried_object()->slug))` → section "Bảng xếp hạng {tên giải}" (set_query_var rows → standings-table) + "Lịch thi đấu" (fixtures-list).

### 4. Trang riêng (MỚI)
- `page-bang-xep-hang.php`, `page-lich-thi-dau.php` — đọc `?league=<slug>` (sanitize theo `BD_FD_LEAGUES`, default `ngoai-hang-anh`); render tab giải (link) + standings/fixtures của giải chọn.
- Tạo 2 WP Page slug `bang-xep-hang`, `lich-thi-dau` (nội dung rỗng — template render). Tạo qua wp-cli (idempotent); prod chạy lại tương tự.

### 5. `header.php` (SỬA)
Thêm "BXH" (`/bang-xep-hang/`) + "Lịch" (`/lich-thi-dau/`) vào `$bd_nav` (desktop) + mobile panel. Rebuild `dist/main.css` nếu có class mới.

## Data flow
Page/archive → `bd_fd_standings($code)`/`bd_fd_fixtures($code)` → `bd_fd_get` (cache) → khi cần: `bd_fd_api` (`wp_remote_get`) → map → cache → template-part render. Tab trang riêng đổi `$code` theo `?league`.

## Error handling
- Thiếu `FOOTBALL_DATA_KEY` hoặc API lỗi → `bd_fd_api` trả null → `bd_fd_get` phục vụ option cũ; nếu chưa có → mảng rỗng → template-part không render (section ẩn).
- Timeout 10s để không treo trang lâu.
- Response thiếu field → dùng `??` default an toàn.

## Tiêu chí thành công
- [ ] `inc/football-data.php`: `bd_fd_standings('PL')` trả mảng ≥1 hàng (khi có key); chạy lại trong TTL KHÔNG gọi API lại (transient).
- [ ] Archive `/ngoai-hang-anh/`: HTTP 200, có "Bảng xếp hạng" + tên vài đội + "Lịch thi đấu". Category không phải giải (VD `/chuyen-nhuong/`) → KHÔNG có BXH.
- [ ] `/bang-xep-hang/?league=la-liga`: HTTP 200, tab giải, BXH La Liga. `/lich-thi-dau/`: HTTP 200, lịch.
- [ ] Nav có "BXH" + "Lịch".
- [ ] Thiếu key / API lỗi → trang vẫn 200, section BXH ẩn (không fatal).
- [ ] Rate limit: mỗi page-load ≤2 API call; trong TTL 0 call.

## Ngoài phạm vi (YAGNI)
- Tỷ số live real-time, Champions League (bảng nhóm), H2H, chi tiết trận.
- WP-Cron warming (lazy refresh đủ), tự host crest (dùng URL crest của API).
- Lọc/sort BXH tương tác, phân trang lịch.
