# SP2.1 Điểm core + Đọc + Like — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ví điểm (cộng/dedup/số dư) + AJAX earn cho **đọc** (cuộn ≥60% + ≥20s) và **like**, hiển thị số dư ở header + trang tài khoản.

**Architecture:** `inc/points.php` giữ `bd_award_points`/`bd_get_points` + 2 AJAX (`bd_award`, `bd_toggle_like`) dedup server-side; `single.php` render nút Like + data-attr; `src/main.js` phát hiện đọc + click like → AJAX → cập nhật số dư.

**Tech Stack:** WordPress (PHP 8.2), Tailwind v4 (`npm run build:css`), JS thuần (`npm run build:js` = cp src/main.js → dist/main.js). Test: `wp eval-file` + Playwright E2E.

**Spec:** `docs/superpowers/specs/2026-07-15-points-core-read-like-design.md`

## Global Constraints

- Điểm: `read=1, like=1, share=3, comment=5` (định nghĩa đủ 4; SP2.1 dùng read+like). Dedup **1 lần/(user,post,action)** server-side.
- AJAX: nonce `bd_points` (`check_ajax_referer`) + `is_user_logged_in`; KHÔNG có `nopriv` (khách không gọi được). param loại hành động là `sub` (KHÔNG `action` — admin-ajax chiếm `action` cho tên handler).
- Un-like KHÔNG trừ điểm (dedup award qua `bd_like_awarded_posts`). Khách: nút Like = link `/tai-khoan/`.
- Escape output (`esc_url`/`esc_attr`/`esc_html`). JS `fetch` có `credentials:'same-origin'` (gửi cookie auth). Lỗi AJAX → `.catch` im lặng.
- Bám class theme; nút/badge dùng `text-brand`/`border-brand`/`border-card`/`text-secondary`.

---

### Task 1: `inc/points.php` — ví điểm + AJAX

**Files:**
- Create: `wp/themes/bongda247/inc/points.php`
- Modify: `wp/themes/bongda247/functions.php` (require)
- Test (tạm, KHÔNG commit): `/tmp/bd_points_test.php`

**Interfaces:**
- Produces: `bd_get_points($uid):int`; `bd_award_points($uid,$action,$post_id):bool`; AJAX `bd_award` (param `sub`,`post_id`,`_wpnonce`) + `bd_toggle_like` (`post_id`,`_wpnonce`). Task 2 dùng.

- [ ] **Step 1: Viết test (thất bại vì chưa có hàm)**

Tạo `/tmp/bd_points_test.php`:
```php
<?php
$uid = wp_insert_user(['user_login'=>'bd_pts_test_'.wp_rand(1000,9999),'user_pass'=>'x','role'=>'subscriber']);
$fail = [];
if (bd_award_points($uid,'read',101) !== true) $fail[]='read award !true';
if (bd_get_points($uid) !== 1) $fail[]='points != 1 sau read';
if (bd_award_points($uid,'read',101) !== false) $fail[]='read dedup !false';
if (bd_get_points($uid) !== 1) $fail[]='points đổi khi dedup';
if (bd_award_points($uid,'like',101) !== true) $fail[]='like !true';
if (bd_get_points($uid) !== 2) $fail[]='points != 2 sau like';
if (bd_award_points($uid,'share',102) !== true) $fail[]='share !true';
if (bd_get_points($uid) !== 5) $fail[]='points != 5 sau share (2+3)';
if (bd_award_points($uid,'bogus',103) !== false) $fail[]='bogus !false';
if (bd_get_points($uid) !== 5) $fail[]='points đổi khi bogus';
require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($uid);
echo $fail ? ('FAIL: '.implode(' | ',$fail)."\n") : "PASS\n";
```

- [ ] **Step 2: Chạy — kỳ vọng FATAL (hàm chưa có)**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_points_test.php`
Expected: FATAL `Call to undefined function bd_award_points()`.

- [ ] **Step 3: Tạo `inc/points.php`**

```php
<?php
defined('ABSPATH') || exit;

const BD_POINTS = ['read' => 1, 'like' => 1, 'share' => 3, 'comment' => 5];
const BD_DEDUP_META = [
    'read'    => 'bd_read_posts',
    'like'    => 'bd_like_awarded_posts',
    'share'   => 'bd_share_posts',
    'comment' => 'bd_comment_posts',
];

function bd_get_points($uid) {
    return (int) get_user_meta($uid, 'bd_points', true);
}

/** Cộng điểm 1 hành động cho 1 bài (dedup). true nếu vừa cộng, false nếu đã earn / hành động lạ. */
function bd_award_points($uid, $action, $post_id) {
    if (!isset(BD_POINTS[$action])) {
        return false;
    }
    $post_id  = (int) $post_id;
    $meta_key = BD_DEDUP_META[$action];
    $earned   = (array) get_user_meta($uid, $meta_key, true);
    if (in_array($post_id, $earned, true)) {
        return false;
    }
    $earned[] = $post_id;
    update_user_meta($uid, $meta_key, $earned);
    update_user_meta($uid, 'bd_points', bd_get_points($uid) + BD_POINTS[$action]);
    return true;
}

// AJAX: cộng điểm hành động đơn (read / share)
add_action('wp_ajax_bd_award', 'bd_ajax_award');
function bd_ajax_award() {
    check_ajax_referer('bd_points');
    if (!is_user_logged_in()) {
        wp_send_json_error('auth', 403);
    }
    $sub     = sanitize_key($_POST['sub'] ?? '');
    $post_id = (int) ($_POST['post_id'] ?? 0);
    if (!in_array($sub, ['read', 'share'], true) || !get_post($post_id)) {
        wp_send_json_error('invalid', 400);
    }
    $uid     = get_current_user_id();
    $awarded = bd_award_points($uid, $sub, $post_id);
    wp_send_json_success(['points' => bd_get_points($uid), 'awarded' => $awarded]);
}

// AJAX: toggle like (+ cộng điểm lần đầu)
add_action('wp_ajax_bd_toggle_like', 'bd_ajax_toggle_like');
function bd_ajax_toggle_like() {
    check_ajax_referer('bd_points');
    if (!is_user_logged_in()) {
        wp_send_json_error('auth', 403);
    }
    $post_id = (int) ($_POST['post_id'] ?? 0);
    if (!get_post($post_id)) {
        wp_send_json_error('invalid', 400);
    }
    $uid       = get_current_user_id();
    $liked     = (array) get_user_meta($uid, 'bd_liked_posts', true);
    $count     = (int) get_post_meta($post_id, 'bd_like_count', true);
    $now_liked = in_array($post_id, $liked, true);

    if ($now_liked) {
        $liked = array_values(array_diff($liked, [$post_id]));
        $count = max(0, $count - 1);
    } else {
        $liked[] = $post_id;
        $count++;
        bd_award_points($uid, 'like', $post_id); // dedup qua bd_like_awarded_posts → re-like không cộng lại
    }
    update_user_meta($uid, 'bd_liked_posts', $liked);
    update_post_meta($post_id, 'bd_like_count', $count);
    wp_send_json_success(['liked' => !$now_liked, 'count' => $count, 'points' => bd_get_points($uid)]);
}
```

- [ ] **Step 4: Require trong `functions.php`** — sau require `inc/auth.php`:
```php
require_once get_stylesheet_directory() . '/inc/points.php';
```

- [ ] **Step 5: Chạy lại test — kỳ vọng PASS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_points_test.php`
Expected: `PASS`

- [ ] **Step 6: Xoá test + verify AJAX đăng ký + commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
rm -f /tmp/bd_points_test.php
./wp/bin/wp eval 'echo has_action("wp_ajax_bd_award")?"award OK\n":"MISSING\n"; echo has_action("wp_ajax_bd_toggle_like")?"like OK\n":"MISSING\n"; echo has_action("wp_ajax_nopriv_bd_award")?"NOPRIV LEAK\n":"no-nopriv OK\n";'
git add wp/themes/bongda247/inc/points.php wp/themes/bongda247/functions.php
git commit -m "feat(theme): ví điểm bd_award_points/bd_get_points + AJAX bd_award/bd_toggle_like"
```
Expected: `award OK`, `like OK`, `no-nopriv OK` (khách không gọi được).

---

### Task 2: Nút Like (single) + phát hiện đọc (JS) + hiển thị số dư

**Files:**
- Modify: `wp/themes/bongda247/single.php` (khối reactions cuối bài)
- Modify: `wp/themes/bongda247/header.php` (badge điểm khi đăng nhập)
- Modify: `wp/themes/bongda247/page-tai-khoan.php` (số dư thật)
- Modify: `wp/themes/bongda247/src/main.js` (đọc + like)
- Build: `wp/themes/bongda247/dist/main.js` + `dist/main.css`

**Interfaces:**
- Consumes: `bd_get_points`, AJAX `bd_award`/`bd_toggle_like` (Task 1).

- [ ] **Step 1: `single.php` — khối reactions.** Ngay SAU `get_template_part('template-parts/author-box')` (trong hộp `.bg-card`, trước `</div>` đóng hộp), thêm:
```php
        <?php if (is_user_logged_in()) :
          $bd_pid   = get_the_ID();
          $bd_liked = in_array($bd_pid, (array) get_user_meta(get_current_user_id(), 'bd_liked_posts', true), true);
        ?>
          <div data-bd-points data-bd-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-bd-nonce="<?php echo esc_attr(wp_create_nonce('bd_points')); ?>" data-bd-post="<?php echo esc_attr($bd_pid); ?>" class="mt-8 flex items-center gap-3">
            <button data-bd-like type="button" aria-pressed="<?php echo $bd_liked ? 'true' : 'false'; ?>" class="flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition-colors <?php echo $bd_liked ? 'text-brand border-brand' : 'text-secondary border-card hover:border-brand'; ?>">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7.5-4.9-9.6-9.2C1 8.5 2.8 5.5 5.9 5.5c1.9 0 3.3 1 4.1 2.3C10.8 6.5 12.2 5.5 14.1 5.5c3.1 0 4.9 3 3.5 6.3C19.5 16.1 12 21 12 21z"/></svg>
              <span data-bd-like-count><?php echo (int) get_post_meta($bd_pid, 'bd_like_count', true); ?></span>
            </button>
            <span class="text-xs text-secondary">Thích để +1 điểm</span>
          </div>
        <?php else : ?>
          <div class="mt-8">
            <a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="inline-flex items-center gap-2 rounded-full border border-card px-4 py-2 text-sm text-secondary hover:border-brand transition-colors">♥ Thích (đăng nhập để tích điểm)</a>
          </div>
        <?php endif; ?>
```

- [ ] **Step 2: `header.php` — badge điểm.** Trong nhánh `is_user_logged_in()` (nơi có `$bd_cu`), NGAY TRƯỚC `<div class="hidden lg:block relative group">`, thêm:
```php
            <span class="hidden lg:flex items-center gap-1 text-sm text-brand font-semibold" title="Điểm">★ <span data-bd-points-balance><?php echo (int) bd_get_points($bd_cu->ID); ?></span></span>
```

- [ ] **Step 3: `page-tai-khoan.php` — số dư thật.** Thay:
```php
      <p class="text-sm text-secondary mt-2">Điểm: <span class="text-brand font-bold">— (sắp có)</span></p>
```
bằng:
```php
      <p class="text-sm text-secondary mt-2">Điểm: <span class="text-brand font-bold"><?php echo (int) bd_get_points($bd_u->ID); ?></span></p>
```

- [ ] **Step 4: `src/main.js` — đọc + like.** Thêm vào TRONG hàm `DOMContentLoaded` (cạnh khối Swiper), đoạn:
```js
    // --- Điểm: đọc + like ---
    var bdPts = document.querySelector('[data-bd-points]');
    if (bdPts) {
      var bdAjax = bdPts.getAttribute('data-bd-ajax');
      var bdNonce = bdPts.getAttribute('data-bd-nonce');
      var bdPost = bdPts.getAttribute('data-bd-post');
      var setBalance = function (p) {
        var b = document.querySelector('[data-bd-points-balance]');
        if (b && typeof p === 'number') b.textContent = p;
      };
      var bdSend = function (action, extra) {
        var params = { action: action, post_id: bdPost, _wpnonce: bdNonce };
        for (var k in (extra || {})) params[k] = extra[k];
        return fetch(bdAjax, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(params),
        }).then(function (r) { return r.json(); });
      };
      // Đọc: cuộn >=60% VÀ ở lại >=20s → cộng 1 lần
      var readSent = false, maxScroll = 0, enoughTime = false;
      var tryRead = function () {
        if (readSent || !enoughTime || maxScroll < 60) return;
        readSent = true;
        bdSend('bd_award', { sub: 'read' }).then(function (res) {
          if (res && res.success) setBalance(res.data.points);
        }).catch(function () {});
      };
      setTimeout(function () { enoughTime = true; tryRead(); }, 20000);
      window.addEventListener('scroll', function () {
        var h = document.documentElement;
        var pct = (h.scrollTop + window.innerHeight) / h.scrollHeight * 100;
        if (pct > maxScroll) maxScroll = pct;
        tryRead();
      }, { passive: true });
      // Like
      var likeBtn = bdPts.querySelector('[data-bd-like]');
      if (likeBtn) {
        likeBtn.addEventListener('click', function () {
          bdSend('bd_toggle_like').then(function (res) {
            if (!res || !res.success) return;
            var d = res.data;
            likeBtn.setAttribute('aria-pressed', d.liked ? 'true' : 'false');
            likeBtn.classList.toggle('text-brand', d.liked);
            likeBtn.classList.toggle('border-brand', d.liked);
            likeBtn.classList.toggle('text-secondary', !d.liked);
            likeBtn.classList.toggle('border-card', !d.liked);
            var c = likeBtn.querySelector('[data-bd-like-count]');
            if (c) c.textContent = d.count;
            setBalance(d.points);
          }).catch(function () {});
        });
      }
    }
```

- [ ] **Step 5: Build JS + CSS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247 && npm run build:js && npm run build:css`
Expected: build không lỗi.

- [ ] **Step 6: Verify (khách + build)**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
POST=$(./wp/bin/wp post get 103 --field=url 2>/dev/null)
curl -s "$POST" -o /tmp/s.html -w "HTTP %{http_code}\n"
echo "khách: nút login-like: $(grep -c 'Thích (đăng nhập' /tmp/s.html) | data-bd-points (không có với khách): $(grep -c 'data-bd-points' /tmp/s.html) | lỗi PHP: $(grep -ci 'fatal error\|warning:\|notice:' /tmp/s.html)"
echo "JS build có like/read: $(grep -c 'bd_toggle_like' wp/themes/bongda247/dist/main.js) + $(grep -c 'sub: .read.' wp/themes/bongda247/dist/main.js)"
```
Expected: HTTP 200; khách thấy `Thích (đăng nhập`=1, `data-bd-points`=0 (chỉ render khi đăng nhập), lỗi PHP=0; dist/main.js chứa `bd_toggle_like` + `sub: 'read'`. *(E2E đăng nhập→like→đọc: controller chạy Playwright sau.)*

- [ ] **Step 7: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/single.php wp/themes/bongda247/header.php wp/themes/bongda247/page-tai-khoan.php wp/themes/bongda247/src/main.js wp/themes/bongda247/dist/main.js wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): nút Like + phát hiện đọc (JS AJAX) + hiển thị số dư điểm"
```

---

## Sau khi xong 2 task

- Controller chạy Playwright E2E (đăng nhập test user): like bài → count+1 + số dư+1 + nút đổi trạng thái; unlike → count−1, điểm KHÔNG giảm; đọc (evaluate giả lập) → +1; reload+like lại → điểm không tăng (dedup). Dọn user + meta.
- Cập nhật CLAUDE.md (`inc/points.php`; single có like; điểm ở header/tài khoản) + data model (user meta bd_points...).
- Ledger `.superpowers/sdd/points-core/progress.md`.
- Finishing gate (bot `npm test` 9/9; curl single 200) → merge + push.
- Tiếp theo: **SP2.2 Comment earn** + **SP2.3 Share earn** (dùng lại `bd_award_points`).
