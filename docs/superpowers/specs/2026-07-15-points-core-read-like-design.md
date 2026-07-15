# SP2.1: Điểm core + Đọc + Like — Design

> Sub-project đầu của hệ thống điểm (SP2). Dựng **ví điểm** (cộng/dedup/số dư + hiển thị) và 2 nguồn earn đầu: **đọc bài** (cuộn ≥60% + ≥20s) và **like**. (SP2.2 comment, SP2.3 share dùng lại core này.)

## Mục tiêu

- Ví điểm bền theo user + hàm cộng điểm có dedup + hiển thị số dư (header + trang tài khoản).
- 2 nguồn earn: đọc bài (1 điểm/bài) + like (1 điểm/bài). Chống farm.

## Bối cảnh & ràng buộc

- SP1 xong: tài khoản (`inc/auth.php`, `/tai-khoan/`, header có trạng thái đăng nhập). User role `subscriber`.
- `single.php` có sẵn (E-E-A-T: TOC, author box, related). Chưa có like/points.
- Theme JS: `src/main.js` (event-delegation, build qua `npm run build:js` = `cp src/main.js dist/main.js`; CSS qua `npm run build:css`). admin-ajax pattern đã dùng (fd-widget: data-attr + fetch).
- Điểm/hành động (đã chốt): **Đọc 1 · Like 1 · Share 3 · Comment 5**. Phát hiện đọc: **cuộn ≥60% VÀ ở lại ≥20s**, 1 lần/bài.

## Kiến trúc / thành phần

### 1. `inc/points.php` (MỚI) — ví điểm + AJAX
**Lưu trữ (user meta):** `bd_points` (int số dư); mảng dedup post IDs: `bd_read_posts`, `bd_share_posts`, `bd_comment_posts` (award-dedup theo hành động), `bd_liked_posts` (trạng thái like hiện tại — để toggle/hiển thị), `bd_like_awarded_posts` (đã-cộng-điểm-like, dedup award). Post meta: `bd_like_count` (int).

**Hàm:**
```php
function bd_get_points($uid) { return (int) get_user_meta($uid, 'bd_points', true); }

/**
 * Cộng điểm 1 hành động cho 1 bài (dedup). Trả true nếu vừa cộng, false nếu đã earn / hành động lạ.
 * amounts = ['read'=>1,'like'=>1,'share'=>3,'comment'=>5]; dedup key theo hành động.
 */
function bd_award_points($uid, $action, $post_id) { ... }
```
- Dedup key: `read→bd_read_posts`, `like→bd_like_awarded_posts`, `share→bd_share_posts`, `comment→bd_comment_posts`. Nếu `$post_id` đã trong mảng → return false. Else thêm + `bd_points += amount` → return true.

**AJAX (admin-ajax, nonce `bd_points`, chỉ user đăng nhập):**
- `wp_ajax_bd_award` — param `sub` ∈ {`read`} (và `share` cho SP2.3 — KHÔNG dùng `action` vì admin-ajax đã chiếm cho tên handler `bd_award`), `post_id` int → `check_ajax_referer('bd_points')` + `is_user_logged_in` + kiểm `sub` hợp lệ + `bd_award_points($uid, $sub, $post_id)` → `wp_send_json_success(['points'=>bd_get_points, 'awarded'=>bool])`.
- `wp_ajax_bd_toggle_like` — `post_id` int → toggle: nếu post trong `bd_liked_posts` → gỡ + `bd_like_count--`; else thêm + `bd_like_count++` + (nếu chưa trong `bd_like_awarded_posts`) `bd_award_points('like')`. Un-like KHÔNG trừ điểm. Trả `['liked'=>bool,'count'=>int,'points'=>int]`.
- (Không có `nopriv` → khách không gọi được.)
- Require trong `functions.php`.

### 2. `single.php` (SỬA) — nút Like + wrapper data-attr
- Bọc vùng bài (chỉ khi `is_user_logged_in()`) bằng `<div data-bd-points data-bd-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-bd-nonce="<?php echo esc_attr(wp_create_nonce('bd_points')); ?>" data-bd-post="<?php echo esc_attr(get_the_ID()); ?>">` (để JS tìm cho cả đọc lẫn like).
- Nút **Like** cuối bài:
  - Đăng nhập: `<button data-bd-like aria-pressed="<đã thích?>">♥ <span data-bd-like-count>N</span></button>` (N = `bd_like_count`).
  - Chưa đăng nhập: link "♥ Thích" → `/tai-khoan/`.

### 3. `src/main.js` (SỬA → build) — đọc + like
- Nếu có `[data-bd-points]` trên trang:
  - **Đọc:** theo dõi `%cuộn` tối đa + timer; khi `%cuộn ≥ 60` VÀ `≥20s` VÀ chưa gửi → `fetch(ajax, action=bd_award&sub=read&post_id=&_wpnonce=)` (POST) 1 lần → cập nhật số dư header.
  - **Like:** click `[data-bd-like]` → `fetch(bd_toggle_like)` → cập nhật `aria-pressed` + `[data-bd-like-count]` + số dư header. `.catch` im lặng.

### 4. Hiển thị số dư
- **Header** (khi đăng nhập): badge `⭐ <span data-bd-points-balance>N</span>` cạnh dropdown user (N = `bd_get_points`). JS cập nhật sau khi earn.
- **`page-tai-khoan.php`** (SỬA): thay "Điểm: — (sắp có)" bằng số thật `bd_get_points(get_current_user_id())`.

## Data flow

User đăng nhập đọc single → JS phát hiện cuộn+thời gian → AJAX `bd_award(read)` → `bd_award_points` (dedup) → số dư +1. Click Like → AJAX `bd_toggle_like` → count ± + điểm +1 (lần đầu) → số dư cập nhật. Header + trang tài khoản đọc `bd_get_points`.

## Error handling

- Chưa đăng nhập → nút Like là link `/tai-khoan/` (không AJAX); wrapper `[data-bd-points]` không render → JS không chạy earn.
- AJAX thiếu nonce/không đăng nhập → `wp_send_json_error` (403); JS `.catch` im lặng, UI không vỡ.
- Dedup server-side: mỗi (user,post,action) chỉ cộng 1 lần dù client gọi lại. Un-like không trừ điểm.
- post_id không hợp lệ / bài không tồn tại → bỏ qua.
- Escape mọi output; số/nonce qua `esc_attr`.

## Kiểm thử

- **Unit `bd_award_points`** (wp eval-file): cộng đúng theo bảng điểm; dedup (gọi lần 2 cùng post/action → false, số dư không đổi); hành động lạ → false. Cleanup user meta.
- **E2E Playwright:** đăng nhập → mở 1 single → click Like → số lượt +1, nút `aria-pressed=true`, số dư header +1 + trang tài khoản +1; click lại (unlike) → count −1 nhưng **điểm KHÔNG giảm**; giả lập cuộn ≥60% + chờ ≥20s (hoặc gọi trực tiếp qua evaluate) → đọc +1; tải lại + like lại → điểm không tăng thêm (dedup). Dọn user + meta.
- `curl` single (khách) 200, không lỗi PHP, nút Like là link đăng nhập.

## Tiêu chí thành công

- [ ] User đăng nhập: đọc bài (cuộn+thời gian) +1 điểm/bài; like +1 điểm/bài; số dư hiện ở header + trang tài khoản, cập nhật realtime sau earn.
- [ ] Dedup: mỗi bài chỉ +1/hành động; un-like không trừ điểm.
- [ ] Khách: nút Like → trang đăng nhập; không tích điểm.
- [ ] `bd_award_points` hỗ trợ đủ 4 hành động (read/like/share/comment) cho SP2.2/2.3; AJAX bảo mật (nonce + login + dedup server-side).

## Ngoài phạm vi (SP2.1)

- Comment earn (SP2.2), Share earn (SP2.3), mở khóa dự đoán (SP3), nạp tiền, bảng transaction (dùng user meta arrays cho MVP), lịch sử điểm.
