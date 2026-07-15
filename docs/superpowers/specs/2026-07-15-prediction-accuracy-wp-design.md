# SP-A (WordPress): Độ chính xác nhận định AI — lưu bền + hiển thị — Design

> Phần WordPress của tính năng "Độ chính xác nhận định AI": CPT lưu bền `bd_prediction`, hàm gom số liệu, trang `/thanh-tich-du-doan/`, badge "AI đúng X%". Bot (SP-B, spec riêng) sẽ ghi record + đối chiếu kết quả.

## Mục tiêu

- Có nơi **lưu bền** dự đoán của bot (không bị auto-xoá như `match_insight`) + kết quả chấm.
- Hiển thị **% dự đoán đúng** (tín hiệu uy tín/E-E-A-T) trên web: trang tổng kết + badge tái sử dụng.
- Test được độc lập bot bằng dữ liệu seed.

## Bối cảnh & ràng buộc

- mu-plugin `wp/mu-plugins/bongda247-core.php` đã đăng ký CPT `match_insight` + meta (pattern: `register_post_type` + `register_post_meta` với `show_in_rest` + `auth_callback => 'bd_meta_auth'`). SP-A thêm CPT `bd_prediction` theo cùng pattern.
- Theme: pattern trang = WP Page + `page-{slug}.php` (như `page-lich-thi-dau.php`). Heading `font-hemi ... border-l-4 border-brand`, `bg-card`/`border-card`, `text-secondary`/`text-brand`.
- Data layer theme `inc/` (query.php, football-data.php, toc.php, schema.php) — thêm hàm gom số liệu.
- **Cách chấm (đã chốt):** 1X2 (thắng/hòa/thua) làm badge chính; tỉ số chính xác là thống kê phụ.
- **Phạm vi:** chỉ dự đoán tự động (có `match_id`). SP-A không quan tâm nguồn — chỉ đọc record đã `settled`.

## Mô hình dữ liệu — CPT `bd_prediction`

`register_post_type('bd_prediction', [ 'public'=>false, 'show_ui'=>true, 'show_in_rest'=>true, 'rest_base'=>'bd_prediction', 'supports'=>['title','custom-fields'], 'menu_icon'=>'dashicons-chart-line', 'labels'=>[...'Dự đoán'] ])` — **không public front-end** (không cần single), `show_in_rest` để bot ghi, `show_ui` để xem trong admin.

Meta (đều `show_in_rest`, `single`, `auth_callback=>'bd_meta_auth'`):
| key | type | ghi chú |
|-----|------|---------|
| `match_id` | integer | id football-data (dedup + đối chiếu) |
| `home_team`, `away_team` | string | tên đội |
| `league_code` | string | PL/PD/BL1/SA/FL1 |
| `match_date` | string | ISO UTC |
| `pred_home`, `pred_away` | integer | tỉ số dự đoán |
| `pred_text` | string | dự đoán dạng chữ |
| `status` | string | `pending` \| `settled` |
| `actual_home`, `actual_away` | integer | tỉ số thật (sau chấm) |
| `outcome_correct` | integer | 1X2 đúng: 0/1 |
| `score_correct` | integer | trúng tỉ số: 0/1 |
| `settled_at` | string | ISO khi chấm |

*(Dùng integer 0/1 cho cờ đúng — KHÔNG boolean, để WP_Query/đọc ổn định, theo tiền lệ meta `hot`.)*

## Kiến trúc / thành phần

### 1. `wp/mu-plugins/bongda247-core.php` (SỬA) — thêm CPT + meta
Thêm `register_post_type('bd_prediction', ...)` + `register_post_meta` cho các key trên (integer cho số, string cho chữ), cùng `bd_meta_auth` sẵn có.

### 2. `wp/themes/bongda247/inc/prediction.php` (MỚI) — hàm gom số liệu
```php
/**
 * Số liệu độ chính xác từ CPT bd_prediction (chỉ record status=settled).
 * @return [ 'total'=>int, 'outcome_correct'=>int, 'score_correct'=>int,
 *           'outcome_pct'=>int, 'score_pct'=>int,
 *           'recent'=>[ ['home','away','pred_home','pred_away','actual_home','actual_away','outcome_correct','match_date','league_code'], ... ] ]
 */
function bd_prediction_stats($recent = 10) { ... }
```
- Query 1: tất cả `settled` (`fields=ids`, `no_found_rows`) → `update_meta_cache('post', $ids)` → cộng `outcome_correct` + `score_correct` → tính %.
- Query 2: `$recent` record `settled` mới nhất theo `match_date` desc → gom trường hiển thị. `wp_reset_postdata()`.
- 0 record → total 0, pct 0, recent [].
- Require trong `functions.php` (cạnh query/football-data/toc/schema).

### 3. `wp/themes/bongda247/template-parts/prediction-badge.php` (MỚI) — badge tái dùng
- Gọi `bd_prediction_stats()`; `total === 0` → return (ẩn). Ngược lại: khối gọn "AI dự đoán đúng **X%**" + "qua N trận" + link `/thanh-tich-du-doan/`. Dùng được ở trang Nhận định / trang chủ.

### 4. `wp/themes/bongda247/page-thanh-tich-du-doan.php` (MỚI) — trang tổng kết
- WP Page slug `thanh-tich-du-doan` (tạo qua wp-cli).
- Heading "Thành tích dự đoán". `$s = bd_prediction_stats(20)`.
- `total===0` → "Chưa có dữ liệu — quay lại sau khi có trận được chấm."
- Ngược lại: khối headline (X% đúng 1X2 · N trận · Y% trúng tỉ số) + **bảng** trận gần nhất: cột *Trận* (home–away) · *Dự đoán* (pred_home–pred_away) · *Kết quả* (actual) · *✓/✗* (outcome_correct). Escape toàn bộ.

### 5. `header.php` (SỬA, tùy chọn nhẹ) — link "Thành tích" trong dropdown "Giải đấu" hoặc top-level
- Thêm 1 link tới `/thanh-tich-du-doan/` (giữ nav gọn — có thể để trong dropdown thay vì top-level).

## Data flow

SP-B (bot) ghi/cập nhật record `bd_prediction` qua REST → SP-A đọc: `bd_prediction_stats()` gom từ CPT (`settled`) → badge + trang hiển thị. Không gọi API ngoài (số liệu nội bộ).

## Error handling

- 0 record settled → badge ẩn, trang hiện "Chưa có dữ liệu".
- Record thiếu field (dữ liệu bẩn) → cast `(int)`/`(string)`, mặc định an toàn.
- Escape: `esc_html` (tên/tỉ số/%), `esc_url` (link).

## Kiểm thử (thủ công + wp-cli seed)

- [ ] CPT `bd_prediction` đăng ký OK (`wp post-type list` có `bd_prediction`); REST base tồn tại.
- [ ] Seed 5 record qua wp-cli (2 đúng 1X2 + 1 trúng tỉ số, 3 sai, đều `settled`) → trang `/thanh-tich-du-doan/` hiện đúng %: outcome_pct = round(2/5*100)=40, score_pct đúng; bảng liệt kê 5 trận + ✓/✗ đúng. Badge hiện "40%".
- [ ] 0 record → trang "Chưa có dữ liệu", badge ẩn.
- [ ] `curl` trang HTTP 200, 0 lỗi PHP. Playwright chụp. Xoá seed.

## Tiêu chí thành công

- [ ] CPT `bd_prediction` lưu bền (không auto-xoá), ghi được qua REST (cho SP-B), xem được trong admin.
- [ ] `/thanh-tich-du-doan/`: % đúng 1X2 + N trận + % trúng tỉ số + bảng trận gần nhất; "Chưa có dữ liệu" khi rỗng.
- [ ] Badge "AI đúng X%" tái dùng, tự ẩn khi chưa có số liệu.
- [ ] Không API ngoài; escape đầy đủ.

## Ngoài phạm vi (SP-A)

- Bot ghi record + cấu trúc predScore + cron đối chiếu (SP-B). Biểu đồ/thống kê theo giải/tháng (phase sau). Chấm tay cho manual insight.
