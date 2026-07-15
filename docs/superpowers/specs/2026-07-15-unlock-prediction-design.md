# SP3: Mở khóa dự đoán bằng điểm — Design

> Feature cuối Giai đoạn 1 monetization. Khóa **dự đoán tỉ số của match_insight**; user tiêu **5 điểm** để mở khóa; đã-mở lưu theo user (mở 1 lần, xem mãi).

## Mục tiêu

- Biến điểm (tích từ SP2) thành thứ có giá trị: chi điểm để xem dự đoán → khép vòng earn→spend, dựng nền cho nạp tiền (Giai đoạn 2).
- Khóa nhất quán ở mọi chỗ hiển thị dự đoán (carousel trang chủ + trang /nhan-dinh/).

## Bối cảnh & ràng buộc

- SP2 xong: ví điểm `inc/points.php` (`bd_get_points`, `bd_award_points`, AJAX `bd_points` nonce). CPT `match_insight` có meta `prediction` (chuỗi tỉ số dự đoán).
- Dự đoán render 2 chỗ, cùng badge `bg-prediction`: `template-parts/match-insights.php` (carousel trang chủ, biến `$prediction`) + `template-parts/insight-card.php` (trang /nhan-dinh/, biến `$bd_pred`).
- `src/main.js` load mọi trang (build `npm run build:js`).

## Quyết định thiết kế (đã chốt)

| # | Quyết định | Chọn |
|---|-----------|------|
| Khóa gì | Nội dung | **Dự đoán tỉ số của match_insight** (`prediction`) |
| Giá | Mở 1 dự đoán | **5 điểm** (`BD_UNLOCK_COST`) |
| Bền | Mở rồi | Lưu theo user (`bd_unlocked_insights`), **mở 1 lần xem mãi** |
| Khách | Chưa đăng nhập | Badge khóa → link `/tai-khoan/` (không tiêu được điểm) |

## Kiến trúc / thành phần

### 1. `inc/points.php` (SỬA) — spend + unlock + helper render
- `BD_UNLOCK_COST = 5`. User meta `bd_unlocked_insights` (mảng insight IDs).
- `bd_spend_points($uid, $amount)` — `bd_get_points < $amount` → return false; else trừ (`update_user_meta bd_points`) → true. (Kiểm số dư server-side.)
- `bd_is_unlocked($uid, $iid)` — `in_array($iid, (array) bd_unlocked_insights)`.
- **AJAX `wp_ajax_bd_unlock`** (`check_ajax_referer('bd_points')` + `is_user_logged_in`):
  - `$iid=(int)$_POST['insight_id']`; `get_post($iid)` phải `post_type==='match_insight'` (else error 400).
  - `$pred = get_post_meta($iid,'prediction',true)`.
  - Đã mở (`bd_is_unlocked`) → `wp_send_json_success(['points'=>bd_get_points,'prediction'=>$pred])` (idempotent, KHÔNG trừ lại).
  - Chưa mở → `bd_spend_points($uid, BD_UNLOCK_COST)`: thiếu → `wp_send_json_error('nopoints', 402)`; đủ → ghi `$iid` vào `bd_unlocked_insights` → success `{points, prediction}`.
  - KHÔNG `nopriv`.
- **`bd_prediction_badge($iid, $prediction)`** (helper render, DRY):
  - `$prediction===''` → `''`.
  - Bọc `<div data-bd-pred-gate>` (để JS thay khi mở).
  - **Khách:** badge khóa 🔒 + link `/tai-khoan/` "Đăng nhập để xem dự đoán".
  - **Đăng nhập + đã mở:** badge `bg-prediction` với `esc_html($prediction)`.
  - **Đăng nhập + chưa mở:** badge khóa 🔒 + `<button data-bd-unlock data-bd-insight="$iid" data-bd-ajax data-bd-nonce>Mở khóa (5 điểm)</button>`.

### 2. Render — khóa 2 chỗ (SỬA)
- `match-insights.php`: thay khối badge `bg-prediction` (dòng ~81–85) bằng `echo bd_prediction_badge($id, $prediction);`.
- `insight-card.php`: thay dòng badge `bg-prediction` (dòng 34) bằng `echo bd_prediction_badge($bd_id, $bd_pred);`.

### 3. `src/main.js` (SỬA → build) — mở khóa
- Delegation toàn trang: click `[data-bd-unlock]` → `fetch bd_unlock (insight_id, _wpnonce)` →
  - success: thay nội dung `[data-bd-pred-gate]` bằng badge `bg-prediction` với `textContent = res.data.prediction` (**chống XSS**, không innerHTML giá trị) + cập nhật `[data-bd-points-balance]`.
  - error `nopoints` → nút đổi text "Không đủ điểm".
  - `.catch` im lặng.

## Data flow

Trang render dự đoán → `bd_prediction_badge` quyết định (khách/đã mở/chưa mở). User đăng nhập bấm "Mở khóa (5đ)" → AJAX `bd_unlock` → `bd_spend_points(5)` + ghi unlocked → trả prediction → JS hiện + số dư −5. Lần sau vào lại: `bd_is_unlocked` → hiện luôn.

## Error handling

- Khách → badge link đăng nhập (không AJAX).
- Thiếu điểm → error `nopoints` → UI "Không đủ điểm" (server KHÔNG trừ).
- insight_id sai / không phải match_insight → error 400.
- Đã mở → idempotent (trả prediction, không trừ lại).
- Escape: `esc_html`/`esc_url`/`esc_attr` phía PHP; JS dùng `textContent` cho giá trị prediction.

## Kiểm thử

- **Unit (wp eval-file):** `bd_spend_points` (đủ → trừ+true; thiếu → false + số dư giữ nguyên); `bd_is_unlocked`; mô phỏng unlock (spend 5 + ghi unlocked; gọi lại → idempotent không trừ).
- **E2E Playwright:** nạp điểm cho user test (`update_user_meta bd_points`) → mở /nhan-dinh/ (có match_insight có prediction) → badge khóa → click "Mở khóa" → dự đoán hiện + điểm −5; user 0 điểm → "Không đủ điểm"; khách → badge "đăng nhập". Dọn user.
- `curl` (khách) trang /nhan-dinh/ + trang chủ: badge khóa "Đăng nhập để xem dự đoán", KHÔNG lộ giá trị prediction trong HTML; 0 lỗi PHP.

## Tiêu chí thành công

- [ ] Dự đoán match_insight bị khóa ở carousel + /nhan-dinh/; khách không thấy giá trị (chỉ link đăng nhập).
- [ ] User đăng nhập đủ điểm → mở khóa → dự đoán hiện + trừ 5đ; mở rồi xem mãi (idempotent).
- [ ] Thiếu điểm → "Không đủ điểm", không trừ.
- [ ] Prediction KHÔNG lộ trong HTML khi chưa mở (server chỉ trả khi mở); AJAX nonce+login+kiểm số dư server-side.

## Ngoài phạm vi

- Mở khóa bài phân tích đầy đủ (chỉ prediction match_insight); gói "mở tất cả"/subscription; hoàn điểm; nạp tiền (Giai đoạn 2); lịch sử giao dịch điểm.
