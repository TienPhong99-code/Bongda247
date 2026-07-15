# SP2.2: Bình luận + tích điểm — Design

> Sub-project 2 của hệ thống điểm. Render bình luận WordPress trên bài viết (bắt đăng nhập, tự duyệt) + cộng **5 điểm** cho bình luận đầu tiên/bài (dùng lại `bd_award_points` của SP2.1).

## Mục tiêu

- Người đọc đăng nhập bình luận trực tiếp trên bài + nhận điểm (nguồn earn thứ 3, sau đọc & like).
- Chống spam: chỉ user đăng nhập, dedup 1 lần/bài.

## Bối cảnh & ràng buộc

- SP2.1 xong: ví điểm `inc/points.php` (`bd_award_points($uid,$action,$post_id)`, bảng điểm gồm `comment=5`, dedup key `bd_comment_posts`); tài khoản (SP1).
- WP hiện: `default_comment_status=open`, `comment_registration=0`, `comment_moderation=0`. `single.php` CHƯA gọi `comments_template()`; theme CHƯA có `comments.php`.
- Theme: `.container`, `max-w-4xl mx-auto`, `bg-card`/`bg-control`/`border-card`, `font-hemi`, `text-brand`/`text-secondary`; heading section `font-hemi text-2xl uppercase border-l-4 border-brand pl-4`.

## Quyết định thiết kế (đã chốt)

| # | Quyết định | Chọn |
|---|-----------|------|
| Ai comment | Quyền | **Bắt đăng nhập** (`comment_registration=1`) |
| Duyệt | Moderation | **Tự duyệt** (auto-approve, giữ `comment_moderation=0`) |
| Điểm | Cộng khi nào | **5đ cho comment đầu tiên/bài** (dedup `bd_comment_posts`); comment sau không cộng |

## Kiến trúc / thành phần

### 1. `comments.php` (MỚI) — template bình luận
- `post_password_required()` → return.
- Section "Bình luận (N)" + danh sách qua `wp_list_comments(['style'=>'ol','avatar_size'=>40,'callback'=>'bd_comment_render'])` + `the_comments_pagination()`.
- **`bd_comment_render($comment,$args,$depth)`** (định nghĩa trong comments.php, guard `function_exists`): `<li>` themed — avatar + tên (`get_comment_author`, esc_html) + ngày (`get_comment_date`) + nội dung (`comment_text` — WP tự escape). KHÔNG tự đóng `</li>` (wp_list_comments đóng).
- **Form:**
  - Đăng nhập → `comment_form()` với args themed: `comment_field` = textarea (`bg-control border-card`, placeholder "+5 điểm cho bình luận đầu tiên"), `class_submit` = nút brand, bỏ `logged_in_as`/notes thừa.
  - Chưa đăng nhập → "Vui lòng [đăng nhập](/tai-khoan/) để bình luận và nhận điểm."

### 2. `single.php` (SỬA) — gọi comments_template
- Sau `get_template_part('template-parts/related-posts')` (trong The Loop, sau khi related-posts `wp_reset_postdata` → $post đã khôi phục), thêm `comments_template();`.

### 3. `inc/points.php` (SỬA) — hook cộng điểm khi comment
```php
add_action('comment_post', 'bd_award_comment_points', 10, 2);
function bd_award_comment_points($comment_id, $approved) {
    if ($approved !== 1) return;                 // chỉ khi đã duyệt (auto-approve = 1)
    $c = get_comment($comment_id);
    if (!$c || (int) $c->user_id < 1) return;    // chỉ user đăng nhập
    bd_award_points((int) $c->user_id, 'comment', (int) $c->comment_post_ID); // dedup 1 lần/bài
}
```

### 4. WP option `comment_registration=1` (wp-cli lúc triển khai)
- `wp option update comment_registration 1` → WP chặn khách bình luận ở server (khớp UI).

### 5. CSS bình luận (build) — bám card/dark theme (class Tailwind trong callback + form).

## Data flow

User đăng nhập gõ bình luận → submit (POST `wp-comments-post.php`, reload) → WP lưu (auto-approve) → `comment_post` → `bd_award_points(comment)` (dedup) → reload: bình luận hiện + header badge điểm cập nhật (đọc `bd_get_points` lúc render). Không AJAX.

## Error handling

- Khách submit comment → WP từ chối (`comment_registration=1`); UI chỉ hiện link đăng nhập.
- Comment thứ 2/bài → đăng được nhưng `bd_award_points` dedup → không cộng.
- `comment_post` chỉ cộng khi `$approved===1` + `user_id≥1`.
- Escape: `esc_html`/`esc_url`; `comment_text`/`comment_form` dùng escape của WP core.

## Kiểm thử

- **Unit (wp eval-file):** tạo user + comment (approved, gắn user_id) → `do_action('comment_post', $cid, 1)` → điểm +5; gọi lại (`do_action` với comment khác cùng bài) → không +. Dọn comment + user.
- **E2E Playwright:** đăng nhập → mở bài → gõ + gửi bình luận → bình luận hiện trong danh sách + điểm +5 (badge header); gửi bình luận 2 cùng bài → hiện nhưng điểm KHÔNG tăng. Dọn comment + user.
- `curl` bài (khách): section "Bình luận" render + "đăng nhập để bình luận" link; HTTP 200; 0 lỗi PHP.

## Tiêu chí thành công

- [ ] Bài viết render danh sách bình luận + form; chỉ user đăng nhập gửi được (option + UI).
- [ ] Comment đầu/bài của user đăng nhập → +5 điểm; comment sau không cộng (dedup).
- [ ] Khách → link đăng nhập; tự duyệt (comment hiện ngay).
- [ ] Không phá single.php cũ (TOC/author/related/like); escape đầy đủ.

## Ngoài phạm vi

- Reply lồng/phân trang nâng cao (dùng mặc định WP); trừ điểm khi comment bị xoá/spam sau; SP2.3 share; SP3 mở khóa dự đoán.
