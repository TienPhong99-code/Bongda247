# SP4: Gamification / Retention — Design

> Biến hệ điểm (SP2/SP3) thành **thói quen hằng ngày**: điểm danh + streak, nhiệm vụ hằng ngày, bảng xếp hạng thành viên, huy hiệu. Mục tiêu: tăng daily active users — tiền đề cho Giai đoạn 2 (nạp tiền).

## Mục tiêu

- Cho user **lý do quay lại mỗi ngày** (điểm danh streak + nhiệm vụ reset 0h).
- Làm điểm **có giá trị xã hội** (bảng xếp hạng công khai) và **thành tựu cá nhân** (huy hiệu).
- Tái dùng tối đa hạ tầng `inc/points.php` (ví `bd_points`, AJAX nonce `bd_points`, dedup user meta). KHÔNG đụng luồng SP3 (mở khóa dự đoán).

## Bối cảnh & ràng buộc

- `inc/points.php` đã có: `bd_get_points`, `bd_award_points($uid,$action,$post_id)` (read/like/share/comment, dedup per-post), AJAX `bd_award`/`bd_toggle_like`, comment hook, `bd_spend_points`, `bd_unlock`. Nonce dùng chung `'bd_points'`; AJAX chỉ user đăng nhập, KHÔNG nopriv.
- `/tai-khoan/` (`page-tai-khoan.php`): logged-in hiện card profile (display_name, email, ngày tham gia, **Điểm**, đăng xuất). Đây là nơi gắn UI gamify.
- Header có badge điểm `[data-bd-points-balance]` (chỉ khi đăng nhập) — JS `setBalance()` cập nhật.
- `src/main.js` (IIFE, DOMContentLoaded) build qua `npm run build:js`/`build:css`.
- Múi giờ: dùng `current_time('Y-m-d')` / `current_time('timestamp')` — mốc ngày theo **timezone site** (phải là Asia/Ho_Chi_Minh; nếu chưa set thì đó là việc cấu hình, ngoài code).

## Quyết định thiết kế (đã chốt)

| # | Quyết định | Chọn |
|---|-----------|------|
| Điểm danh | Thưởng/ngày | **+2đ**, mốc **7 ngày liên tiếp → +10đ** (lặp 7/14/21…) |
| Streak reset | Bỏ lỡ ≥1 ngày | Reset về **1** ở lần điểm danh kế |
| Nhiệm vụ | Bộ 3 cố định/ngày | Đọc 3 bài **+3đ** · Thích 1 bài **+2đ** · Bình luận 1 bài **+5đ** |
| Nhiệm vụ | Nhận thưởng | **Tự cộng** khi đủ tiến độ (không cần bấm nhận), 1 lần/ngày/nhiệm vụ |
| Leaderboard | Dạng | **Trang riêng** `/bang-xep-hang-thanh-vien/`, tab **Tuần + All-time** (query `?range`) |
| Leaderboard | Reset tuần | Thứ 2 (ISO week `Y-\WW`), lazy-reset per-user |
| Huy hiệu | Nguồn | **Suy ra từ chỉ số sẵn có** (không lưu state mới) |

## Kiến trúc / thành phần

### 1. `inc/points.php` (SỬA nhẹ) — cửa cộng điểm duy nhất + weekly
- Thêm **`bd_credit_points($uid, $amount)`**: cộng `bd_points` += amount **VÀ** bơm weekly:
  - `$wk = current_time('o-\WW')` (ISO year-week). Nếu user meta `bd_week_id !== $wk` → set `bd_points_week = 0`, `bd_week_id = $wk` (lazy-reset). Rồi `bd_points_week += amount`.
  - Chỉ cộng (amount > 0). Trả số dư mới.
- `bd_award_points`: đổi dòng cuối `update_user_meta('bd_points', ...)` → gọi `bd_credit_points($uid, BD_POINTS[$action])`. (Giữ nguyên dedup + return.)
- `bd_spend_points` (SP3): **KHÔNG đổi** (tiêu điểm không giảm weekly-earned).
- **Interface mới cho gamify:** `bd_credit_points($uid,$amount):int`.

### 2. `inc/gamify.php` (MỚI) — điểm danh, nhiệm vụ, leaderboard, huy hiệu
`require`-chain qua `functions.php` (sau points.php). Chứa hằng số + logic + AJAX + query.

**2a. Điểm danh + streak**
- Hằng: `BD_CHECKIN_REWARD = 2`, `BD_STREAK_BONUS_EVERY = 7`, `BD_STREAK_BONUS = 10`.
- `bd_checkin($uid): array` — trả `['already'=>bool,'reward'=>int,'streak'=>int,'points'=>int]`:
  - `$today = current_time('Y-m-d')`, `$last = get_user_meta('bd_checkin_last')`.
  - `$last === $today` → `already=true`, không cộng.
  - `$last === hôm qua` (`current_time('timestamp') - DAY` format Y-m-d) → `streak = bd_streak + 1`; ngược lại → `streak = 1`.
  - `reward = BD_CHECKIN_REWARD`; nếu `streak % 7 === 0` → `reward += BD_STREAK_BONUS`.
  - `bd_credit_points($uid, reward)`; update `bd_checkin_last=$today`, `bd_streak=$streak`, `bd_streak_best=max(cũ,$streak)`.
- AJAX `wp_ajax_bd_checkin` (nonce `bd_points` + login, KHÔNG nopriv) → JSON `bd_checkin()`.
- User meta: `bd_checkin_last` (Y-m-d), `bd_streak` (int), `bd_streak_best` (int).

**2b. Nhiệm vụ hằng ngày**
- Hằng `BD_QUESTS = ['read'=>['target'=>3,'reward'=>3], 'like'=>['target'=>1,'reward'=>2], 'comment'=>['target'=>1,'reward'=>5]]`.
- `bd_quest_state($uid): array` — lazy-reset: nếu `bd_quest_day !== today` → reset `bd_quest_progress=[]`, `bd_quest_done=[]`, `bd_quest_day=today`. Trả progress + done + defs.
- `bd_quest_bump($uid, $type)` — gọi từ luồng award khi user làm action (read/like/comment). Tăng `bd_quest_progress[$type]`; nếu đạt `target` và chưa `done[$type]` → `bd_credit_points($uid, reward)` + set `done[$type]=1`. Trả `['completed'=>bool,'reward'=>int]`.
- **Điểm gắn (hook):**
  - `bd_ajax_award` (sub=read) → gọi `bd_quest_bump($uid,'read')` **mỗi lần AJAX** (kể cả bài đã đọc trước đó — award điểm gốc dedup per-post all-time nên KHÔNG tính "đọc bài mới hôm nay"; quest đếm theo số lần đọc-đủ-điều-kiện). Gộp reward nhiệm vụ vào `points` trả về. *(Có thể farm nhẹ bằng reload cùng 1 bài; thưởng nhỏ 3đ, chấp nhận Giai đoạn 1.)*
  - `bd_ajax_toggle_like` → khi **liked=true** (không phải un-like) gọi `bd_quest_bump($uid,'like')`.
  - `bd_award_comment_points` (comment hook) → gọi `bd_quest_bump($uid,'comment')`.
  - (share KHÔNG có nhiệm vụ — giữ đơn giản.)
- User meta: `bd_quest_day` (Y-m-d), `bd_quest_progress` (assoc type→int), `bd_quest_done` (assoc type→1).
- **Lưu ý:** nhiệm vụ thưởng RIÊNG, KHÔNG thay điểm gốc của action (read vẫn +1, xong quest read +3).

**2c. Bảng xếp hạng**
- `bd_leaderboard($range, $limit=50): array` — `$range ∈ {week, all}`:
  - `all`: `WP_User_Query` orderby meta_value_num `bd_points` DESC, số > 0.
  - `week`: meta_query `bd_week_id = current_time('o-\WW')` AND orderby `bd_points_week` DESC.
  - Trả mảng `[{rank, user_id, name, points, streak, top_badge}]`.
- `bd_user_rank($uid, $range): int` — hạng của user (đếm số user điểm cao hơn + 1); 0 nếu chưa có điểm.

**2d. Huy hiệu (suy ra)**
- Hằng `BD_BADGES` (mảng def: id, name, desc, tier ∈ {bronze,silver,gold,brand}, icon (emoji), `check` = closure/xác định theo (metric, threshold)). Metric lấy từ: `bd_get_points`, `bd_streak_best`, count `bd_read_posts`/`bd_comment_posts`/`bd_unlocked_insights`.
- `bd_user_badges($uid): array` — trả tất cả badge kèm `earned` (bool). KHÔNG lưu meta.
- Bộ khởi đầu (8): points≥100 (Người mới,bronze) · ≥500 (Cao thủ,silver) · ≥2000 (Huyền thoại,gold) · streak_best≥7 (Chuyên cần,bronze) · ≥30 (Kiên định,gold) · read≥50 (Mọt tin,silver) · comment≥20 (Nhà bình luận,silver) · unlock≥20 (Nhà tiên tri,gold).

### 3. `template-parts/badge-grid.php` (MỚI) — render lưới huy hiệu
- Nhận `$uid`; gọi `bd_user_badges`. Mỗi huy hiệu = huy chương tròn: vòng ngoài **gradient theo tier** (bronze `#b45309→#fbbf24`, silver `#64748b→#e2e8f0`, gold `#ca8a04→#fde047`, brand `#0232ff→#60a5fa`), tâm `bg-card`, icon/emoji giữa, tên + mô tả dưới. `earned=false` → `grayscale opacity-40` + 🔒. Tailwind thuần (gradient qua `bg-[conic-gradient(...)]`/`bg-gradient-to-br` + ring).

### 4. `page-bang-xep-hang-thanh-vien.php` (MỚI) — trang leaderboard
- Slug `bang-xep-hang-thanh-vien` (tạo Page gán template, hoặc template theo tên file + tạo page). 2 tab link `?range=week|all` (mặc định week). Bảng: hạng · tên (+ huy hiệu cao nhất) · điểm · streak 🔥. **Tô đậm dòng của user hiện tại**; nếu user ngoài top → hiện dòng "Hạng của bạn: #N" dưới bảng. Khách xem được (read-only), có CTA đăng nhập.

### 5. UI `/tai-khoan/` (SỬA `page-tai-khoan.php`)
Mở rộng card profile logged-in, thêm (bám class theme):
- **Streak + điểm danh:** "🔥 Chuỗi N ngày" + nút `[data-bd-checkin]` "Điểm danh hôm nay (+2đ)"; nếu đã điểm danh hôm nay → nút disabled "Đã điểm danh hôm nay ✓".
- **Nhiệm vụ hôm nay:** 3 dòng với tiến độ (VD "Đọc bài 2/3") + ✓ khi xong.
- **Huy hiệu:** `get_template_part('template-parts/badge-grid')`.
- **Link** "🏆 Bảng xếp hạng" → `/bang-xep-hang-thanh-vien/` + "Hạng của bạn: #N".

### 6. `src/main.js` (SỬA → build) — điểm danh
- Delegated click `[data-bd-checkin]` → AJAX `bd_checkin` → success: cập nhật `[data-bd-points-balance]` (dùng `res.data.points`), đổi nút thành "Đã điểm danh ✓" (disable), cập nhật text streak `[data-bd-streak]`. `already` → chỉ disable. `.catch` im lặng. (Nhiệm vụ tự cộng ở backend — main.js không cần xử lý; số dư cập nhật ở lần load kế hoặc qua response read/like đã có.)

## Data flow

Vào `/tai-khoan/` → thấy streak, nhiệm vụ, huy hiệu, hạng. Bấm **Điểm danh** → `bd_checkin` cộng điểm + tăng streak (qua `bd_credit_points` → cũng bơm weekly). Đọc/like/comment quanh site → luồng award cũ chạy + `bd_quest_bump` cộng thưởng nhiệm vụ khi đủ. Mọi điểm cộng đều vào weekly → **bảng xếp hạng tuần** phản ánh hoạt động tuần này; all-time theo `bd_points`. Huy hiệu tính lại mỗi lần xem hồ sơ.

## Error handling

- AJAX: nonce sai → 403 (check_ajax_referer); chưa đăng nhập → `wp_send_json_error('auth',403)`; KHÔNG nopriv.
- Điểm danh idempotent (đã điểm danh → `already=true`, không cộng).
- Nhiệm vụ dedup `bd_quest_done` (1 lần/ngày/nhiệm vụ); lazy-reset an toàn khi đổi ngày.
- Weekly lazy-reset khi đổi tuần (user không hoạt động giữ giá trị cũ nhưng leaderboard week lọc theo `bd_week_id` hiện tại → không lọt).
- Escape PHP đầy đủ (`esc_html`/`esc_attr`/`esc_url`); huy hiệu/tên user qua `esc_html`.
- Leaderboard giới hạn 50, `no_found_rows` khi được.

## Kiểm thử

- **Unit (`wp eval-file`):** `bd_credit_points` cộng + bơm weekly + reset tuần khi đổi week_id · `bd_checkin` idempotent trong ngày, streak++ ngày kế (giả lập `bd_checkin_last`=hôm qua), reset khi cách quãng, bonus khi streak%7==0 · `bd_quest_bump` tăng tiến độ + cộng thưởng đúng 1 lần khi đạt target + lazy-reset sang ngày mới · `bd_leaderboard`/`bd_user_rank` xếp đúng thứ tự · `bd_user_badges` earned đúng theo ngưỡng.
- **E2E Playwright:** đăng nhập → `/tai-khoan/`: điểm danh → +2đ, streak "1", nút thành "Đã điểm danh"; đọc 3 bài (giả lập/kịch bản) → nhiệm vụ read xong +3đ; mở `/bang-xep-hang-thanh-vien/` → thấy user trong bảng, dòng user tô đậm; huy hiệu: nạp điểm ≥100 → badge "Người mới" sáng. Khách: UI gamify ẩn (link đăng nhập); trang leaderboard vẫn xem được, 0 lỗi PHP.
- **Kinh tế:** không kiểm tự động — ghi nhận rộng rãi có chủ đích, tinh chỉnh ở Giai đoạn 2.

## Tiêu chí thành công

- [ ] Điểm danh 1 lần/ngày cộng điểm + tăng streak; bỏ lỡ reset; mốc 7 thưởng.
- [ ] 3 nhiệm vụ hằng ngày tự cộng thưởng khi đủ, reset 0h, không cộng trùng.
- [ ] Trang `/bang-xep-hang-thanh-vien/` xếp hạng Tuần + All-time đúng, tô đậm user, khách xem được.
- [ ] Lưới huy hiệu hiện trong `/tai-khoan/`, sáng khi đạt ngưỡng, đẹp (preview duyệt trước).
- [ ] Mọi điểm cộng qua `bd_credit_points` (đồng bộ weekly); SP3 mở khóa không bị ảnh hưởng.
- [ ] AJAX nonce + login + không nopriv; escape đầy đủ.

## Ngoài phạm vi

- Cấp bậc/level thanh XP, nhiệm vụ tuần/tháng, chia sẻ huy hiệu ra MXH, thông báo toast khi đạt huy hiệu, hoàn điểm, nạp tiền (Giai đoạn 2), lịch sử giao dịch điểm.
