# SP2.2 Bình luận + tích điểm — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Render bình luận WordPress trên bài (bắt đăng nhập, tự duyệt) + cộng 5 điểm cho bình luận đầu tiên/bài.

**Architecture:** Hook `comment_post` trong `inc/points.php` gọi `bd_award_points('comment')` (dedup sẵn có); `comments.php` render list + form (login-gated); `single.php` gọi `comments_template()`; option `comment_registration=1` chặn khách.

**Tech Stack:** WordPress (PHP 8.2), Tailwind v4 (`npm run build:css`). Test: `wp eval-file` + Playwright.

**Spec:** `docs/superpowers/specs/2026-07-15-comments-earn-design.md`

## Global Constraints

- Cộng 5đ CHỈ khi `comment_post` với `$approved===1` + comment có `user_id ≥ 1` (user đăng nhập). Dedup 1 lần/bài qua `bd_award_points($uid,'comment',$post_id)` (SP2.1). Comment sau/bài không cộng.
- Chỉ user đăng nhập bình luận: `comment_registration=1` (server) + UI chỉ hiện form cho user đăng nhập, khách → link `/tai-khoan/`.
- Escape output: `esc_html`/`esc_url`; `comment_text`/`comment_form` dùng escape WP core.
- Bám class theme; comment item + form dùng `bg-card`/`bg-control`/`border-card`, `font-hemi`, `text-brand`/`text-secondary`. Không phá single.php cũ (TOC/author/related/like).

---

### Task 1: Hook cộng điểm khi comment (`inc/points.php`)

**Files:**
- Modify: `wp/themes/bongda247/inc/points.php` (thêm hook cuối file)
- Test (tạm, KHÔNG commit): `/tmp/bd_cmt_test.php`

**Interfaces:**
- Consumes: `bd_award_points`, `bd_get_points` (SP2.1).
- Produces: hook `comment_post` → `bd_award_comment_points($comment_id,$approved)`.

- [ ] **Step 1: Viết test (thất bại vì chưa có hook)**

Tạo `/tmp/bd_cmt_test.php`:
```php
<?php
$uid = wp_insert_user(['user_login'=>'bd_cmt_'.wp_rand(1000,9999),'user_pass'=>'x','role'=>'subscriber']);
$pid = 103; // bài thật
$fail = [];
$before = bd_get_points($uid);
$cid1 = wp_insert_comment(['comment_post_ID'=>$pid,'user_id'=>$uid,'comment_content'=>'test 1','comment_approved'=>1]);
do_action('comment_post', $cid1, 1);
if (bd_get_points($uid) !== $before + 5) $fail[] = 'comment đầu không +5 (đang '.bd_get_points($uid).')';
$cid2 = wp_insert_comment(['comment_post_ID'=>$pid,'user_id'=>$uid,'comment_content'=>'test 2','comment_approved'=>1]);
do_action('comment_post', $cid2, 1);
if (bd_get_points($uid) !== $before + 5) $fail[] = 'comment 2 cùng bài lại +điểm (dedup hỏng)';
// comment chưa duyệt (approved 0) → không cộng
$cid3 = wp_insert_comment(['comment_post_ID'=>104,'user_id'=>$uid,'comment_content'=>'chờ duyệt','comment_approved'=>0]);
$p = bd_get_points($uid);
do_action('comment_post', $cid3, 0);
if (bd_get_points($uid) !== $p) $fail[] = 'comment chưa duyệt lại +điểm';
wp_delete_comment($cid1, true); wp_delete_comment($cid2, true); wp_delete_comment($cid3, true);
require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($uid);
echo $fail ? ('FAIL: '.implode(' | ',$fail)."\n") : "PASS\n";
```

- [ ] **Step 2: Chạy — kỳ vọng FAIL (hook chưa có → điểm không +5)**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_cmt_test.php`
Expected: `FAIL: comment đầu không +5 ...` (chưa có hook cộng điểm).

- [ ] **Step 3: Thêm hook vào cuối `inc/points.php`**

```php
// Cộng điểm khi user đăng nhập bình luận (5đ, dedup 1 lần/bài).
add_action('comment_post', 'bd_award_comment_points', 10, 2);
function bd_award_comment_points($comment_id, $approved) {
    if ($approved !== 1) {
        return; // chỉ khi đã duyệt (auto-approve = 1)
    }
    $c = get_comment($comment_id);
    if (!$c || (int) $c->user_id < 1) {
        return; // chỉ user đăng nhập
    }
    bd_award_points((int) $c->user_id, 'comment', (int) $c->comment_post_ID);
}
```

- [ ] **Step 4: Chạy lại — kỳ vọng PASS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_cmt_test.php`
Expected: `PASS`

- [ ] **Step 5: Xoá test + commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
rm -f /tmp/bd_cmt_test.php
git add wp/themes/bongda247/inc/points.php
git commit -m "feat(theme): cộng 5đ khi user đăng nhập bình luận (comment_post hook, dedup/bài)"
```

---

### Task 2: `comments.php` + render trong single + chặn khách + build

**Files:**
- Create: `wp/themes/bongda247/comments.php`
- Modify: `wp/themes/bongda247/single.php` (gọi `comments_template()`)
- Build: `wp/themes/bongda247/dist/main.css`

**Interfaces:**
- Consumes: hook cộng điểm (Task 1).

- [ ] **Step 1: Tạo `comments.php`**

```php
<?php
defined('ABSPATH') || exit;
if (post_password_required()) {
    return;
}

if (!function_exists('bd_comment_render')) {
    function bd_comment_render($comment, $args, $depth) {
        ?>
        <li <?php comment_class('rounded-2xl border border-card bg-card p-4 list-none'); ?> id="comment-<?php comment_ID(); ?>">
          <div class="flex items-center gap-3 mb-2">
            <?php echo get_avatar($comment, 40, '', '', ['class' => 'rounded-full shrink-0']); ?>
            <div>
              <div class="font-semibold text-sm"><?php echo esc_html(get_comment_author()); ?></div>
              <time class="text-xs text-secondary"><?php echo esc_html(get_comment_date('d/m/Y')); ?></time>
            </div>
          </div>
          <div class="text-sm text-secondary leading-relaxed"><?php comment_text(); ?></div>
        <?php // wp_list_comments tự đóng </li>
    }
}
?>
<section id="comments" class="max-w-4xl mx-auto mt-12">
  <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4 mb-6">Bình luận<?php if (get_comments_number()) : ?> (<?php echo (int) get_comments_number(); ?>)<?php endif; ?></h2>

  <?php if (have_comments()) : ?>
    <ol class="space-y-4 mb-8">
      <?php wp_list_comments(['style' => 'ol', 'avatar_size' => 40, 'callback' => 'bd_comment_render']); ?>
    </ol>
    <?php the_comments_pagination(['mid_size' => 1]); ?>
  <?php endif; ?>

  <?php if (is_user_logged_in()) :
    comment_form([
      'title_reply'          => 'Để lại bình luận',
      'title_reply_before'   => '<h3 class="font-hemi text-lg uppercase mb-3">',
      'title_reply_after'    => '</h3>',
      'logged_in_as'         => '',
      'comment_notes_before' => '',
      'comment_notes_after'  => '',
      'comment_field'        => '<p class="mb-3"><textarea id="comment" name="comment" rows="4" required class="w-full rounded-lg bg-control border border-card px-3 py-2 text-sm focus:outline-none focus:border-brand" placeholder="Viết bình luận (+5 điểm cho bình luận đầu tiên của bạn)..."></textarea></p>',
      'class_submit'         => 'rounded-lg bg-brand text-white px-5 py-2 text-sm font-medium hover:opacity-90 transition-opacity cursor-pointer',
      'label_submit'         => 'Gửi bình luận',
    ]);
  else : ?>
    <p class="text-secondary text-sm rounded-2xl border border-card bg-card p-4">Vui lòng <a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="text-brand hover:underline">đăng nhập</a> để bình luận và nhận điểm.</p>
  <?php endif; ?>
</section>
```

- [ ] **Step 2: `single.php` — gọi comments_template.** NGAY SAU `get_template_part('template-parts/related-posts')` (trong The Loop), thêm:
```php
    <?php comments_template(); ?>
```

- [ ] **Step 3: Bật `comment_registration` (chặn khách server-side)**

Run:
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
./wp/bin/wp option update comment_registration 1
echo "comment_registration = $(./wp/bin/wp option get comment_registration)"
```
Expected: `comment_registration = 1`.

- [ ] **Step 4: Build CSS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247 && npm run build:css`
Expected: `Done in ...ms`.

- [ ] **Step 5: Verify (khách)**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
POST=$(./wp/bin/wp post get 103 --field=url 2>/dev/null)
curl -s "$POST" -o /tmp/c.html -w "HTTP %{http_code}\n"
echo "section bình luận: $(grep -c 'id="comments"' /tmp/c.html) | khách thấy link đăng nhập: $(grep -c 'đăng nhập</a> để bình luận' /tmp/c.html) | form comment (khách KHÔNG có): $(grep -c 'name="comment"' /tmp/c.html) | lỗi PHP: $(grep -ci 'fatal error\|warning:\|notice:' /tmp/c.html)"
```
Expected: HTTP 200; `id="comments"`=1; link đăng nhập ≥1; `name="comment"` (form) = 0 với khách; lỗi PHP=0. *(E2E đăng nhập → comment → +5đ: controller Playwright sau.)*

- [ ] **Step 6: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/comments.php wp/themes/bongda247/single.php wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): render bình luận (comments.php) + comments_template trong single"
```

---

## Sau khi xong 2 task

- Controller Playwright E2E (đăng nhập test user): gửi bình luận → hiện trong list + điểm +5 (badge); gửi comment 2 cùng bài → không +. Dọn comment + user.
- Cập nhật CLAUDE.md (comments.php; single có bình luận; comment earn 5đ; option comment_registration=1) + data model điểm (thêm nguồn comment).
- Ledger `.superpowers/sdd/comments-earn/progress.md`.
- Finishing gate (bot `npm test` 15/15 — không đụng bot; curl bài 200) → merge + push.
- Tiếp theo: **SP2.3 Share earn** (nút chia sẻ + AJAX bd_award sub=share) rồi **SP3 mở khóa dự đoán**.
