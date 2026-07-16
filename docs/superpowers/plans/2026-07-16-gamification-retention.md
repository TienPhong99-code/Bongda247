# SP4 Gamification / Retention Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Biến hệ điểm thành thói quen hằng ngày — điểm danh+streak, nhiệm vụ hằng ngày, bảng xếp hạng thành viên (tuần+all-time), huy hiệu.

**Architecture:** Thêm cửa cộng điểm duy nhất `bd_credit_points` (points.php) đồng bộ điểm tuần; logic gamify tách file mới `inc/gamify.php`; UI mở rộng `/tai-khoan/` + trang leaderboard mới. Tái dùng ví `bd_points` + AJAX nonce `bd_points`.

**Tech Stack:** WordPress (PHP 8.2), Tailwind v4, JS thuần. Test: `wp eval-file` (unit) + Playwright (E2E).

**Spec:** `docs/superpowers/specs/2026-07-16-gamification-retention-design.md`

## Global Constraints

- Mọi điểm CỘNG đi qua `bd_credit_points($uid,$amount)` (đồng bộ `bd_points` + `bd_points_week`). Tiêu điểm (`bd_spend_points`, SP3) KHÔNG gọi hàm này.
- AJAX: `check_ajax_referer('bd_points')` + `is_user_logged_in` (403 nếu không) + **KHÔNG** `nopriv`.
- Mốc ngày/tuần theo timezone site: `current_time('Y-m-d')`, `current_time('o-\WW')` (ISO year-week), yesterday = `gmdate('Y-m-d', current_time('timestamp') - DAY_IN_SECONDS)`.
- Idempotent: điểm danh 1 lần/ngày; nhiệm vụ thưởng 1 lần/ngày/loại; lazy-reset khi đổi ngày/tuần.
- Nhiệm vụ thưởng RIÊNG, KHÔNG thay điểm gốc của action (read vẫn +1, xong quest read +3).
- Huy hiệu SUY RA từ chỉ số (không lưu meta mới).
- Escape PHP đầy đủ (`esc_html`/`esc_attr`/`esc_url`). Không phá luồng điểm cũ (SP2/SP3).
- WP-CLI qua `./wp/bin/wp` từ gốc dự án `/Users/hotienphong/Desktop/Personal/Bongda247`. Unit test qua `./wp/bin/wp eval-file <path>`. Dọn user test bằng `require_once ABSPATH."wp-admin/includes/user.php"; wp_delete_user($id);` (KHÔNG `wp user delete` — lỗi posix). Test luôn dọn user kể cả khi FAIL.

## File Structure

- `wp/themes/bongda247/inc/points.php` (SỬA) — thêm `bd_credit_points`; refactor `bd_award_points`; gắn `bd_quest_bump` vào 3 luồng award.
- `wp/themes/bongda247/inc/gamify.php` (MỚI) — điểm danh, nhiệm vụ, huy hiệu, leaderboard + AJAX `bd_checkin`.
- `wp/themes/bongda247/functions.php` (SỬA) — require gamify.php.
- `wp/themes/bongda247/template-parts/badge-grid.php` (MỚI) — lưới huy hiệu.
- `wp/themes/bongda247/page-bang-xep-hang-thanh-vien.php` (MỚI) — trang leaderboard (+ tạo WP Page slug tương ứng).
- `wp/themes/bongda247/page-tai-khoan.php` (SỬA) — thêm điểm danh + nhiệm vụ + huy hiệu + link rank.
- `wp/themes/bongda247/src/main.js` (SỬA→build) — handler điểm danh.

---

### Task 1: `bd_credit_points` + điểm tuần (points.php)

**Files:**
- Modify: `wp/themes/bongda247/inc/points.php`
- Test (tạm): `/tmp/bd_credit_test.php`

**Interfaces:**
- Consumes: `bd_get_points($uid)` (sẵn có).
- Produces: `bd_credit_points($uid,$amount):int` — cộng `bd_points` += amount và bơm `bd_points_week` (reset khi đổi ISO week). Task 2/3 dùng.

- [ ] **Step 1: Viết test** — tạo `/tmp/bd_credit_test.php`:
```php
<?php
$uid = wp_insert_user(['user_login'=>'bd_credit_'.wp_rand(1000,9999),'user_pass'=>'x','role'=>'subscriber']);
$fail = [];
update_user_meta($uid, 'bd_points', 0);
if (bd_credit_points($uid, 5) !== 5) $fail[] = 'credit trả total sai';
if ((int) get_user_meta($uid,'bd_points',true) !== 5) $fail[] = 'bd_points != 5';
if ((int) get_user_meta($uid,'bd_points_week',true) !== 5) $fail[] = 'week != 5';
if ((string) get_user_meta($uid,'bd_week_id',true) !== current_time('o-\WW')) $fail[] = 'week_id sai';
bd_credit_points($uid, 3);
if ((int) get_user_meta($uid,'bd_points_week',true) !== 8) $fail[] = 'week != 8 sau cộng tiếp';
update_user_meta($uid, 'bd_week_id', '2000-W01'); // giả lập tuần cũ
bd_credit_points($uid, 2);
if ((int) get_user_meta($uid,'bd_points_week',true) !== 2) $fail[] = 'week không reset khi đổi tuần';
if ((int) get_user_meta($uid,'bd_points',true) !== 10) $fail[] = 'total sai sau reset tuần (phải 10)';
$before = bd_get_points($uid);
if (bd_credit_points($uid, 0) !== $before) $fail[] = 'credit 0 làm đổi điểm';
require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($uid);
echo $fail ? ('FAIL: '.implode(' | ',$fail)."\n") : "PASS\n";
```

- [ ] **Step 2: Chạy — kỳ vọng FATAL** — `./wp/bin/wp eval-file /tmp/bd_credit_test.php` → `Call to undefined function bd_credit_points()`.

- [ ] **Step 3: Thêm `bd_credit_points`** ngay SAU hàm `bd_get_points` trong `inc/points.php`:
```php
/**
 * Cửa cộng điểm DUY NHẤT: cộng bd_points và bơm bd_points_week (điểm kiếm trong tuần
 * ISO, lazy-reset khi sang tuần mới). Mọi nguồn cộng điểm (award action, điểm danh,
 * nhiệm vụ) đi qua đây để bảng xếp hạng tuần đồng bộ. Tiêu điểm (bd_spend_points) KHÔNG gọi.
 */
function bd_credit_points($uid, $amount) {
    $amount = (int) $amount;
    if ($amount <= 0) {
        return bd_get_points($uid);
    }
    $wk = current_time('o-\WW'); // VD 2026-W29
    if ((string) get_user_meta($uid, 'bd_week_id', true) !== $wk) {
        update_user_meta($uid, 'bd_week_id', $wk);
        update_user_meta($uid, 'bd_points_week', 0);
    }
    update_user_meta($uid, 'bd_points_week', (int) get_user_meta($uid, 'bd_points_week', true) + $amount);
    $total = bd_get_points($uid) + $amount;
    update_user_meta($uid, 'bd_points', $total);
    return $total;
}
```

- [ ] **Step 4: Refactor `bd_award_points`** — trong `bd_award_points`, đổi:
```php
    $earned[] = $post_id;
    update_user_meta($uid, $meta_key, $earned);
    update_user_meta($uid, 'bd_points', bd_get_points($uid) + BD_POINTS[$action]);
    return true;
```
thành:
```php
    $earned[] = $post_id;
    update_user_meta($uid, $meta_key, $earned);
    bd_credit_points($uid, BD_POINTS[$action]);
    return true;
```

- [ ] **Step 5: Chạy lại — kỳ vọng PASS** — `./wp/bin/wp eval-file /tmp/bd_credit_test.php` → `PASS`.

- [ ] **Step 6: Xoá test + commit**
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
rm -f /tmp/bd_credit_test.php
git add wp/themes/bongda247/inc/points.php
git commit -m "feat(theme): bd_credit_points — cửa cộng điểm duy nhất + điểm tuần (leaderboard)"
```

---

### Task 2: Điểm danh + streak (gamify.php)

**Files:**
- Create: `wp/themes/bongda247/inc/gamify.php`
- Modify: `wp/themes/bongda247/functions.php`
- Test (tạm): `/tmp/bd_checkin_test.php`

**Interfaces:**
- Consumes: `bd_credit_points` (Task 1), `bd_get_points`.
- Produces: `bd_checkin($uid):array` (`['already'=>bool,'reward'=>int,'streak'=>int,'points'=>int]`); AJAX `bd_checkin`. User meta `bd_checkin_last`/`bd_streak`/`bd_streak_best`.

- [ ] **Step 1: Tạo `inc/gamify.php`** với header + logic điểm danh:
```php
<?php
defined('ABSPATH') || exit;

// ─── Điểm danh + streak ────────────────────────────────────────────────────
const BD_CHECKIN_REWARD     = 2;
const BD_STREAK_BONUS_EVERY = 7;
const BD_STREAK_BONUS       = 10;

/**
 * Điểm danh 1 lần/ngày (timezone site). Trả:
 *   ['already'=>bool, 'reward'=>int, 'streak'=>int, 'points'=>int]
 */
function bd_checkin($uid) {
    $today = current_time('Y-m-d');
    $last  = (string) get_user_meta($uid, 'bd_checkin_last', true);
    if ($last === $today) {
        return ['already' => true, 'reward' => 0,
                'streak' => (int) get_user_meta($uid, 'bd_streak', true),
                'points' => bd_get_points($uid)];
    }
    $yesterday = gmdate('Y-m-d', current_time('timestamp') - DAY_IN_SECONDS);
    $streak = ($last === $yesterday) ? ((int) get_user_meta($uid, 'bd_streak', true) + 1) : 1;

    $reward = BD_CHECKIN_REWARD;
    if ($streak % BD_STREAK_BONUS_EVERY === 0) {
        $reward += BD_STREAK_BONUS;
    }
    $points = bd_credit_points($uid, $reward);
    update_user_meta($uid, 'bd_checkin_last', $today);
    update_user_meta($uid, 'bd_streak', $streak);
    update_user_meta($uid, 'bd_streak_best', max((int) get_user_meta($uid, 'bd_streak_best', true), $streak));
    return ['already' => false, 'reward' => $reward, 'streak' => $streak, 'points' => $points];
}

add_action('wp_ajax_bd_checkin', 'bd_ajax_checkin');
function bd_ajax_checkin() {
    check_ajax_referer('bd_points');
    if (!is_user_logged_in()) {
        wp_send_json_error('auth', 403);
    }
    wp_send_json_success(bd_checkin(get_current_user_id()));
}
```

- [ ] **Step 2: Require gamify.php** — trong `functions.php`, NGAY SAU dòng `require_once get_stylesheet_directory() . '/inc/points.php';` thêm:
```php
require_once get_stylesheet_directory() . '/inc/gamify.php';
```

- [ ] **Step 3: Viết test** — `/tmp/bd_checkin_test.php`:
```php
<?php
$uid = wp_insert_user(['user_login'=>'bd_ci_'.wp_rand(1000,9999),'user_pass'=>'x','role'=>'subscriber']);
$fail = [];
$yest = gmdate('Y-m-d', current_time('timestamp') - DAY_IN_SECONDS);
$two  = gmdate('Y-m-d', current_time('timestamp') - 2*DAY_IN_SECONDS);
// lần đầu
$r = bd_checkin($uid);
if ($r['already'] !== false || $r['streak'] !== 1 || $r['reward'] !== 2) $fail[] = 'điểm danh lần đầu sai';
// trong ngày → already
$r = bd_checkin($uid);
if ($r['already'] !== true) $fail[] = 'không idempotent trong ngày';
// hôm qua → streak++
update_user_meta($uid,'bd_checkin_last',$yest); update_user_meta($uid,'bd_streak',3);
$r = bd_checkin($uid);
if ($r['streak'] !== 4) $fail[] = 'streak không +1 ngày kế';
// cách quãng → reset 1
update_user_meta($uid,'bd_checkin_last',$two); update_user_meta($uid,'bd_streak',9);
$r = bd_checkin($uid);
if ($r['streak'] !== 1) $fail[] = 'streak không reset khi cách quãng';
// mốc 7: hôm qua streak 6 → thành 7, reward 12
update_user_meta($uid,'bd_checkin_last',$yest); update_user_meta($uid,'bd_streak',6);
$r = bd_checkin($uid);
if ($r['streak'] !== 7 || $r['reward'] !== 12) $fail[] = 'bonus mốc 7 sai (reward='.$r['reward'].')';
if ((int) get_user_meta($uid,'bd_streak_best',true) !== 7) $fail[] = 'streak_best không cập nhật';
require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($uid);
echo $fail ? ('FAIL: '.implode(' | ',$fail)."\n") : "PASS\n";
```

- [ ] **Step 4: Chạy — kỳ vọng PASS** — `./wp/bin/wp eval-file /tmp/bd_checkin_test.php` → `PASS`. (Hàm đã viết ở Step 1 nên chạy là pass; nếu FAIL, sửa gamify.php.)

- [ ] **Step 5: Verify hook + xoá test + commit**
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
./wp/bin/wp eval 'echo has_action("wp_ajax_bd_checkin")?"checkin OK\n":"MISSING\n"; echo has_action("wp_ajax_nopriv_bd_checkin")?"NOPRIV LEAK\n":"no-nopriv OK\n";'
rm -f /tmp/bd_checkin_test.php
git add wp/themes/bongda247/inc/gamify.php wp/themes/bongda247/functions.php
git commit -m "feat(theme): điểm danh hằng ngày + streak (gamify.php, AJAX bd_checkin)"
```
Expected: `checkin OK`, `no-nopriv OK`.

---

### Task 3: Nhiệm vụ hằng ngày (gamify.php + gắn vào luồng award)

**Files:**
- Modify: `wp/themes/bongda247/inc/gamify.php`
- Modify: `wp/themes/bongda247/inc/points.php` (gắn `bd_quest_bump` vào 3 luồng)
- Test (tạm): `/tmp/bd_quest_test.php`

**Interfaces:**
- Consumes: `bd_credit_points`, `bd_get_points`.
- Produces: `BD_QUESTS` (const), `bd_quest_state($uid):array` (`['progress'=>[],'done'=>[]]`), `bd_quest_bump($uid,$type):array` (`['completed'=>bool,'reward'=>int]`). Task 6 dùng `BD_QUESTS`/`bd_quest_state`.

- [ ] **Step 1: Thêm nhiệm vụ vào `inc/gamify.php`** (cuối file):
```php
// ─── Nhiệm vụ hằng ngày ────────────────────────────────────────────────────
const BD_QUESTS = [
    'read'    => ['target' => 3, 'reward' => 3, 'label' => 'Đọc 3 bài hôm nay'],
    'like'    => ['target' => 1, 'reward' => 2, 'label' => 'Thích 1 bài'],
    'comment' => ['target' => 1, 'reward' => 5, 'label' => 'Bình luận 1 bài'],
];

/** Lazy-reset khi sang ngày mới; trả progress + done hiện tại. */
function bd_quest_state($uid) {
    $today = current_time('Y-m-d');
    if ((string) get_user_meta($uid, 'bd_quest_day', true) !== $today) {
        update_user_meta($uid, 'bd_quest_day', $today);
        update_user_meta($uid, 'bd_quest_progress', []);
        update_user_meta($uid, 'bd_quest_done', []);
    }
    return [
        'progress' => (array) get_user_meta($uid, 'bd_quest_progress', true),
        'done'     => (array) get_user_meta($uid, 'bd_quest_done', true),
    ];
}

/** Tăng tiến độ 1 loại; cộng thưởng đúng 1 lần khi đạt target. */
function bd_quest_bump($uid, $type) {
    if (!isset(BD_QUESTS[$type])) {
        return ['completed' => false, 'reward' => 0];
    }
    $state    = bd_quest_state($uid); // đảm bảo đúng ngày
    $progress = $state['progress'];
    $done     = $state['done'];
    if (!empty($done[$type])) {
        return ['completed' => false, 'reward' => 0]; // đã xong hôm nay
    }
    $progress[$type] = (int) ($progress[$type] ?? 0) + 1;
    update_user_meta($uid, 'bd_quest_progress', $progress);
    if ($progress[$type] >= BD_QUESTS[$type]['target']) {
        $done[$type] = 1;
        update_user_meta($uid, 'bd_quest_done', $done);
        bd_credit_points($uid, BD_QUESTS[$type]['reward']);
        return ['completed' => true, 'reward' => BD_QUESTS[$type]['reward']];
    }
    return ['completed' => false, 'reward' => 0];
}
```

- [ ] **Step 2: Gắn `bd_quest_bump` vào `inc/points.php`** — 3 chỗ:

(a) Trong `bd_ajax_award`, đổi:
```php
    $uid     = get_current_user_id();
    $awarded = bd_award_points($uid, $sub, $post_id);
    wp_send_json_success(['points' => bd_get_points($uid), 'awarded' => $awarded]);
```
thành:
```php
    $uid     = get_current_user_id();
    $awarded = bd_award_points($uid, $sub, $post_id);
    if ($sub === 'read') {
        bd_quest_bump($uid, 'read'); // nhiệm vụ đọc — bump mỗi lần AJAX read đủ điều kiện
    }
    wp_send_json_success(['points' => bd_get_points($uid), 'awarded' => $awarded]);
```

(b) Trong `bd_ajax_toggle_like`, nhánh `else` (mới like), đổi:
```php
    } else {
        $liked[] = $post_id;
        $count++;
        bd_award_points($uid, 'like', $post_id); // dedup qua bd_like_awarded_posts → re-like không cộng lại
    }
```
thành:
```php
    } else {
        $liked[] = $post_id;
        $count++;
        bd_award_points($uid, 'like', $post_id); // dedup qua bd_like_awarded_posts → re-like không cộng lại
        bd_quest_bump($uid, 'like');
    }
```

(c) Trong `bd_award_comment_points`, đổi dòng cuối:
```php
    bd_award_points((int) $c->user_id, 'comment', (int) $c->comment_post_ID);
```
thành:
```php
    bd_award_points((int) $c->user_id, 'comment', (int) $c->comment_post_ID);
    bd_quest_bump((int) $c->user_id, 'comment');
```

- [ ] **Step 3: Viết test** — `/tmp/bd_quest_test.php`:
```php
<?php
$uid = wp_insert_user(['user_login'=>'bd_q_'.wp_rand(1000,9999),'user_pass'=>'x','role'=>'subscriber']);
$fail = [];
update_user_meta($uid,'bd_quest_day',current_time('Y-m-d'));
update_user_meta($uid,'bd_quest_progress',[]); update_user_meta($uid,'bd_quest_done',[]);
update_user_meta($uid,'bd_points',0);
$p0 = bd_get_points($uid);
// like target 1 → xong ngay +2
$r = bd_quest_bump($uid,'like');
if ($r['completed'] !== true || $r['reward'] !== 2) $fail[] = 'quest like không xong +2';
if (bd_get_points($uid) !== $p0 + 2) $fail[] = 'quest like không cộng điểm';
// bump like lần nữa → đã done
$r = bd_quest_bump($uid,'like');
if ($r['completed'] !== false || bd_get_points($uid) !== $p0 + 2) $fail[] = 'quest like cộng trùng';
// read target 3
bd_quest_bump($uid,'read'); bd_quest_bump($uid,'read');
$st = bd_quest_state($uid);
if ((int)($st['progress']['read'] ?? 0) !== 2) $fail[] = 'read progress != 2';
if (!empty($st['done']['read'])) $fail[] = 'read xong sớm';
$r = bd_quest_bump($uid,'read');
if ($r['completed'] !== true || $r['reward'] !== 3) $fail[] = 'read không xong lần 3';
// lazy reset ngày mới
update_user_meta($uid,'bd_quest_day','2000-01-01');
$st = bd_quest_state($uid);
if (!empty($st['progress']) || !empty($st['done'])) $fail[] = 'không reset sang ngày mới';
require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($uid);
echo $fail ? ('FAIL: '.implode(' | ',$fail)."\n") : "PASS\n";
```

- [ ] **Step 4: Chạy — kỳ vọng PASS** — `./wp/bin/wp eval-file /tmp/bd_quest_test.php` → `PASS`.

- [ ] **Step 5: Xoá test + commit**
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
rm -f /tmp/bd_quest_test.php
git add wp/themes/bongda247/inc/gamify.php wp/themes/bongda247/inc/points.php
git commit -m "feat(theme): nhiệm vụ hằng ngày (đọc/like/bình luận) tự cộng thưởng + gắn vào luồng award"
```

---

### Task 4: Huy hiệu (gamify.php + badge-grid)

**Files:**
- Modify: `wp/themes/bongda247/inc/gamify.php`
- Create: `wp/themes/bongda247/template-parts/badge-grid.php`
- Test (tạm): `/tmp/bd_badge_test.php`

**Interfaces:**
- Consumes: `bd_get_points`; user meta `bd_streak_best`/`bd_read_posts`/`bd_comment_posts`/`bd_unlocked_insights`.
- Produces: `bd_user_badges($uid):array` (mỗi phần tử `['id','name','desc','icon','tier','metric','need','earned'=>bool]`), `bd_badge_tier_rank($tier):int`. Task 5 (`bd_leaderboard`) dùng `bd_user_badges`.

- [ ] **Step 1: Thêm huy hiệu vào `inc/gamify.php`** (cuối file):
```php
// ─── Huy hiệu (suy ra từ chỉ số, không lưu state) ──────────────────────────
// tier ∈ bronze|silver|gold|brand ; metric ∈ points|streak|read|comment|unlock
const BD_BADGES = [
    ['id'=>'rookie',    'name'=>'Người mới',     'desc'=>'Đạt 100 điểm',       'icon'=>'🥉','tier'=>'bronze','metric'=>'points', 'need'=>100],
    ['id'=>'pro',       'name'=>'Cao thủ',       'desc'=>'Đạt 500 điểm',       'icon'=>'🥈','tier'=>'silver','metric'=>'points', 'need'=>500],
    ['id'=>'legend',    'name'=>'Huyền thoại',   'desc'=>'Đạt 2000 điểm',      'icon'=>'🥇','tier'=>'gold',  'metric'=>'points', 'need'=>2000],
    ['id'=>'diligent',  'name'=>'Chuyên cần',    'desc'=>'Streak 7 ngày',      'icon'=>'🔥','tier'=>'bronze','metric'=>'streak', 'need'=>7],
    ['id'=>'steadfast', 'name'=>'Kiên định',     'desc'=>'Streak 30 ngày',     'icon'=>'💎','tier'=>'gold',  'metric'=>'streak', 'need'=>30],
    ['id'=>'reader',    'name'=>'Mọt tin',       'desc'=>'Đọc 50 bài',         'icon'=>'📰','tier'=>'silver','metric'=>'read',   'need'=>50],
    ['id'=>'talker',    'name'=>'Nhà bình luận', 'desc'=>'Bình luận 20 bài',   'icon'=>'💬','tier'=>'silver','metric'=>'comment','need'=>20],
    ['id'=>'oracle',    'name'=>'Nhà tiên tri',  'desc'=>'Mở khóa 20 dự đoán', 'icon'=>'🔮','tier'=>'gold',  'metric'=>'unlock', 'need'=>20],
];

function bd_badge_metric($uid, $metric) {
    switch ($metric) {
        case 'points':  return bd_get_points($uid);
        case 'streak':  return (int) get_user_meta($uid, 'bd_streak_best', true);
        case 'read':    return count(array_filter((array) get_user_meta($uid, 'bd_read_posts', true)));
        case 'comment': return count(array_filter((array) get_user_meta($uid, 'bd_comment_posts', true)));
        case 'unlock':  return count(array_filter((array) get_user_meta($uid, 'bd_unlocked_insights', true)));
    }
    return 0;
}

/** Thứ hạng tier để chọn huy hiệu "cao nhất". */
function bd_badge_tier_rank($tier) {
    $rank = ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'brand' => 3];
    return $rank[$tier] ?? 0;
}

/** Tất cả huy hiệu + earned (bool). Không lưu meta. */
function bd_user_badges($uid) {
    $out = [];
    foreach (BD_BADGES as $b) {
        $b['earned'] = bd_badge_metric($uid, $b['metric']) >= $b['need'];
        $out[] = $b;
    }
    return $out;
}
```

- [ ] **Step 2: Tạo `template-parts/badge-grid.php`**:
```php
<?php
defined('ABSPATH') || exit;
$bd_uid    = (int) ($args['uid'] ?? get_current_user_id());
$bd_badges = bd_user_badges($bd_uid);
// gradient vòng ngoài theo tier (chuỗi literal để Tailwind scan thấy)
$bd_ring = [
    'bronze' => 'from-amber-700 to-amber-400',
    'silver' => 'from-slate-500 to-slate-200',
    'gold'   => 'from-yellow-600 to-yellow-300',
    'brand'  => 'from-brand to-blue-400',
];
?>
<div class="grid grid-cols-4 sm:grid-cols-6 gap-4">
  <?php foreach ($bd_badges as $bd_b) :
      $bd_earned = !empty($bd_b['earned']);
      $bd_grad   = $bd_ring[$bd_b['tier']] ?? $bd_ring['bronze'];
  ?>
    <div class="flex flex-col items-center text-center <?php echo $bd_earned ? '' : 'opacity-40 grayscale'; ?>" title="<?php echo esc_attr($bd_b['desc']); ?>">
      <div class="w-14 h-14 rounded-full p-[3px] bg-gradient-to-br <?php echo esc_attr($bd_grad); ?>">
        <div class="w-full h-full rounded-full bg-card flex items-center justify-center text-2xl">
          <?php echo $bd_earned ? esc_html($bd_b['icon']) : '🔒'; ?>
        </div>
      </div>
      <span class="mt-1 text-[11px] leading-tight text-secondary"><?php echo esc_html($bd_b['name']); ?></span>
    </div>
  <?php endforeach; ?>
</div>
```

- [ ] **Step 3: Viết test** — `/tmp/bd_badge_test.php`:
```php
<?php
$uid = wp_insert_user(['user_login'=>'bd_b_'.wp_rand(1000,9999),'user_pass'=>'x','role'=>'subscriber']);
$fail = [];
update_user_meta($uid,'bd_points',150);
update_user_meta($uid,'bd_streak_best',10);
$badges = bd_user_badges($uid);
if (count($badges) !== 8) $fail[] = 'số huy hiệu != 8';
$by = []; foreach ($badges as $b) $by[$b['id']] = $b['earned'];
if ($by['rookie'] !== true)    $fail[] = 'rookie chưa sáng ở 150đ';
if ($by['pro'] !== false)      $fail[] = 'pro sáng sai ở 150đ';
if ($by['diligent'] !== true)  $fail[] = 'diligent chưa sáng streak_best 10';
if ($by['steadfast'] !== false)$fail[] = 'steadfast sáng sai streak_best 10';
if (bd_badge_tier_rank('gold') <= bd_badge_tier_rank('bronze')) $fail[] = 'tier_rank sai';
require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($uid);
echo $fail ? ('FAIL: '.implode(' | ',$fail)."\n") : "PASS\n";
```

- [ ] **Step 4: Chạy — kỳ vọng PASS** — `./wp/bin/wp eval-file /tmp/bd_badge_test.php` → `PASS`.

- [ ] **Step 5: Xoá test + commit**
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
rm -f /tmp/bd_badge_test.php
git add wp/themes/bongda247/inc/gamify.php wp/themes/bongda247/template-parts/badge-grid.php
git commit -m "feat(theme): huy hiệu suy ra từ chỉ số + lưới badge-grid (gradient theo hạng)"
```
*(Ảnh preview huy hiệu render ở Task 6 sau khi build CSS — gửi user duyệt "đẹp".)*

---

### Task 5: Bảng xếp hạng (query + trang)

**Files:**
- Modify: `wp/themes/bongda247/inc/gamify.php`
- Create: `wp/themes/bongda247/page-bang-xep-hang-thanh-vien.php`
- Test (tạm): `/tmp/bd_lb_test.php`

**Interfaces:**
- Consumes: `bd_user_badges`, `bd_badge_tier_rank` (Task 4); user meta `bd_points`/`bd_points_week`/`bd_week_id`/`bd_streak`.
- Produces: `bd_leaderboard($range='week',$limit=50):array` (`[{rank,user_id,name,points,streak,top_badge}]`), `bd_user_rank($uid,$range='week'):int`.

- [ ] **Step 1: Thêm leaderboard vào `inc/gamify.php`** (cuối file):
```php
// ─── Bảng xếp hạng ─────────────────────────────────────────────────────────
/** Top thành viên. $range ∈ {week, all}. */
function bd_leaderboard($range = 'week', $limit = 50) {
    $limit = max(1, min(100, (int) $limit));
    if ($range === 'all') {
        $args = [
            'number'     => $limit,
            'meta_query' => ['pts' => ['key' => 'bd_points', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC']],
            'orderby'    => ['pts' => 'DESC'],
            'fields'     => ['ID', 'display_name'],
        ];
        $pt_key = 'bd_points';
    } else {
        $args = [
            'number'     => $limit,
            'meta_query' => [
                'relation' => 'AND',
                'wk'  => ['key' => 'bd_week_id', 'value' => current_time('o-\WW')],
                'pts' => ['key' => 'bd_points_week', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
            ],
            'orderby'    => ['pts' => 'DESC'],
            'fields'     => ['ID', 'display_name'],
        ];
        $pt_key = 'bd_points_week';
    }
    $out  = [];
    $rank = 0;
    foreach (get_users($args) as $u) {
        $rank++;
        $earned = array_values(array_filter(bd_user_badges($u->ID), function ($b) { return $b['earned']; }));
        usort($earned, function ($a, $b) { return bd_badge_tier_rank($b['tier']) <=> bd_badge_tier_rank($a['tier']); });
        $out[] = [
            'rank'      => $rank,
            'user_id'   => (int) $u->ID,
            'name'      => $u->display_name,
            'points'    => (int) get_user_meta($u->ID, $pt_key, true),
            'streak'    => (int) get_user_meta($u->ID, 'bd_streak', true),
            'top_badge' => $earned ? $earned[0]['icon'] : '',
        ];
    }
    return $out;
}

/** Hạng của user (đếm số người điểm cao hơn +1). 0 nếu chưa có điểm. */
function bd_user_rank($uid, $range = 'week') {
    if ($range === 'all') {
        $mine = (int) get_user_meta($uid, 'bd_points', true);
        if ($mine <= 0) return 0;
        $higher = get_users([
            'fields'     => 'ID',
            'meta_query' => ['h' => ['key' => 'bd_points', 'value' => $mine, 'compare' => '>', 'type' => 'NUMERIC']],
        ]);
    } else {
        $wk = current_time('o-\WW');
        if ((string) get_user_meta($uid, 'bd_week_id', true) !== $wk) return 0;
        $mine = (int) get_user_meta($uid, 'bd_points_week', true);
        if ($mine <= 0) return 0;
        $higher = get_users([
            'fields'     => 'ID',
            'meta_query' => [
                'relation' => 'AND',
                'wk' => ['key' => 'bd_week_id', 'value' => $wk],
                'h'  => ['key' => 'bd_points_week', 'value' => $mine, 'compare' => '>', 'type' => 'NUMERIC'],
            ],
        ]);
    }
    return count($higher) + 1;
}
```

- [ ] **Step 2: Tạo `page-bang-xep-hang-thanh-vien.php`**:
```php
<?php defined('ABSPATH') || exit; get_header();
$bd_range  = (isset($_GET['range']) && $_GET['range'] === 'all') ? 'all' : 'week';
$bd_rows   = bd_leaderboard($bd_range, 50);
$bd_uid    = get_current_user_id();
$bd_myrank = $bd_uid ? bd_user_rank($bd_uid, $bd_range) : 0;
$bd_medal  = ['', '🥇', '🥈', '🥉'];
?>
<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Bảng xếp hạng thành viên</h1>

  <div class="flex gap-2 mb-6">
    <a href="?range=week" class="px-4 py-2 rounded-full text-sm font-medium <?php echo $bd_range === 'week' ? 'bg-brand text-white' : 'border border-card text-secondary hover:border-brand'; ?>">Tuần này</a>
    <a href="?range=all" class="px-4 py-2 rounded-full text-sm font-medium <?php echo $bd_range === 'all' ? 'bg-brand text-white' : 'border border-card text-secondary hover:border-brand'; ?>">Mọi thời đại</a>
  </div>

  <?php if (!$bd_rows) : ?>
    <p class="text-secondary">Chưa có dữ liệu xếp hạng.</p>
  <?php else : ?>
    <div class="rounded-2xl border border-card bg-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="text-secondary uppercase text-xs">
          <tr class="border-b border-card">
            <th class="text-left p-3 w-16">Hạng</th>
            <th class="text-left p-3">Thành viên</th>
            <th class="text-right p-3">Điểm</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bd_rows as $bd_r) : $bd_me = ($bd_r['user_id'] === $bd_uid); ?>
            <tr class="border-b border-card/50 <?php echo $bd_me ? 'bg-brand/10 font-semibold' : ''; ?>">
              <td class="p-3"><?php echo $bd_r['rank'] <= 3 ? $bd_medal[$bd_r['rank']] : '#' . (int) $bd_r['rank']; ?></td>
              <td class="p-3"><?php echo esc_html($bd_r['top_badge']); ?> <?php echo esc_html($bd_r['name']); ?><?php if ($bd_r['streak'] > 0) : ?> <span class="text-xs text-secondary">🔥<?php echo (int) $bd_r['streak']; ?></span><?php endif; ?></td>
              <td class="p-3 text-right text-brand font-bold"><?php echo (int) $bd_r['points']; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($bd_uid && $bd_myrank > 50) : ?>
      <p class="mt-4 text-sm text-secondary">Hạng của bạn: <span class="text-brand font-bold">#<?php echo (int) $bd_myrank; ?></span></p>
    <?php elseif (!$bd_uid) : ?>
      <p class="mt-4 text-sm text-secondary"><a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="text-brand hover:underline">Đăng nhập</a> để tích điểm và lên bảng xếp hạng.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php get_footer(); ?>
```

- [ ] **Step 3: Tạo WP Page (slug khớp template)** + verify:
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
./wp/bin/wp post create --post_type=page --post_status=publish --post_title="Bảng xếp hạng thành viên" --post_name="bang-xep-hang-thanh-vien" --porcelain
```

- [ ] **Step 4: Viết test** — `/tmp/bd_lb_test.php` (3 user điểm khác nhau, kiểm thứ tự + rank):
```php
<?php
require_once ABSPATH.'wp-admin/includes/user.php';
$ids = [];
foreach ([['a',300],['b',100],['c',500]] as $x) {
    $id = wp_insert_user(['user_login'=>'bd_lb_'.$x[0].'_'.wp_rand(1000,9999),'user_pass'=>'x','role'=>'subscriber','display_name'=>'LB'.$x[0]]);
    update_user_meta($id,'bd_points',$x[1]);
    $ids[$x[0]] = $id;
}
$fail = [];
$all = bd_leaderboard('all', 50);
$names = array_map(function($r){ return $r['name']; }, $all);
$posA = array_search('LBa', $names); $posB = array_search('LBb', $names); $posC = array_search('LBc', $names);
if (!($posC < $posA && $posA < $posB)) $fail[] = 'thứ tự all-time sai (c>a>b theo điểm)';
// rank all: c hạng 1, b thấp nhất trong 3
if (bd_user_rank($ids['c'],'all') !== 1) $fail[] = 'rank c all != 1 (có thể có user khác điểm cao hơn — bỏ qua nếu môi trường bẩn)';
if (bd_user_rank($ids['a'],'all') >= bd_user_rank($ids['b'],'all')) $fail[] = 'rank a phải nhỏ hơn b';
foreach ($ids as $id) wp_delete_user($id);
echo $fail ? ('FAIL: '.implode(' | ',$fail)."\n") : "PASS\n";
```

- [ ] **Step 5: Chạy test + verify trang khách** — kỳ vọng PASS + trang 200:
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
./wp/bin/wp eval-file /tmp/bd_lb_test.php
for R in week all; do
  code=$(curl -s -o /tmp/lb.html -w "%{http_code}" "http://bongda247.local/bang-xep-hang-thanh-vien/?range=$R")
  echo "range=$R → HTTP $code | lỗi PHP: $(grep -ci 'fatal error\|parse error\|warning:\|notice:' /tmp/lb.html) | có bảng: $(grep -c 'Bảng xếp hạng thành viên' /tmp/lb.html)"
done
```
Expected: `PASS`; mỗi range HTTP 200, lỗi PHP 0. *(Nếu rank test FAIL do user cũ điểm cao trong DB local — kiểm tay, không block.)*

- [ ] **Step 6: Xoá test + commit**
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
rm -f /tmp/bd_lb_test.php
git add wp/themes/bongda247/inc/gamify.php wp/themes/bongda247/page-bang-xep-hang-thanh-vien.php
git commit -m "feat(theme): trang bảng xếp hạng thành viên (tuần + all-time) + query bd_leaderboard/bd_user_rank"
```

---

### Task 6: UI `/tai-khoan/` + main.js điểm danh + build + E2E

**Files:**
- Modify: `wp/themes/bongda247/page-tai-khoan.php`
- Modify: `wp/themes/bongda247/src/main.js`
- Build: `wp/themes/bongda247/dist/main.js` + `dist/main.css`

**Interfaces:**
- Consumes: `bd_checkin_last`/`bd_streak` meta, `BD_QUESTS`, `bd_quest_state`, `bd_user_rank` (Tasks 2–5), helper `bd_prediction_badge` không liên quan. AJAX `bd_checkin`.

- [ ] **Step 1: Đọc block logged-in `page-tai-khoan.php`** để chèn đúng chỗ:
```bash
sed -n '6,15p' /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247/page-tai-khoan.php
```
Xác định dòng `<a ... Đăng xuất</a>` trong card `max-w-md` (logged-in).

- [ ] **Step 2: Mở rộng card profile** — trong `page-tai-khoan.php`, NGAY TRƯỚC dòng `<a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" ...>Đăng xuất</a>`, chèn:
```php
      <?php
      $bd_today      = current_time('Y-m-d');
      $bd_done_today = ((string) get_user_meta($bd_u->ID, 'bd_checkin_last', true) === $bd_today);
      $bd_streak     = (int) get_user_meta($bd_u->ID, 'bd_streak', true);
      $bd_qs         = bd_quest_state($bd_u->ID);
      $bd_rank_all   = bd_user_rank($bd_u->ID, 'all');
      ?>
      <div class="mt-4 pt-4 border-t border-card" data-bd-checkin-box data-bd-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-bd-nonce="<?php echo esc_attr(wp_create_nonce('bd_points')); ?>">
        <div class="flex items-center gap-3 flex-wrap">
          <span class="text-sm">🔥 Chuỗi <span data-bd-streak class="font-bold text-brand"><?php echo $bd_streak; ?></span> ngày</span>
          <button data-bd-checkin type="button" <?php echo $bd_done_today ? 'disabled' : ''; ?> class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors <?php echo $bd_done_today ? 'border border-card text-secondary cursor-default' : 'bg-brand text-white hover:opacity-90'; ?>">
            <?php echo $bd_done_today ? 'Đã điểm danh hôm nay ✓' : 'Điểm danh hôm nay (+2đ)'; ?>
          </button>
        </div>

        <div class="text-sm font-hemi uppercase text-secondary mt-4 mb-2">Nhiệm vụ hôm nay</div>
        <ul class="space-y-1 text-sm">
          <?php foreach (BD_QUESTS as $bd_qk => $bd_q) :
              $bd_prog  = (int) ($bd_qs['progress'][$bd_qk] ?? 0);
              $bd_qdone = !empty($bd_qs['done'][$bd_qk]);
          ?>
            <li class="flex items-center justify-between">
              <span class="<?php echo $bd_qdone ? 'text-brand' : 'text-secondary'; ?>"><?php echo $bd_qdone ? '✓' : '○'; ?> <?php echo esc_html($bd_q['label']); ?> (+<?php echo (int) $bd_q['reward']; ?>đ)</span>
              <span class="text-xs text-secondary"><?php echo min($bd_prog, (int) $bd_q['target']); ?>/<?php echo (int) $bd_q['target']; ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="text-sm font-hemi uppercase text-secondary mt-4 mb-2">Huy hiệu</div>
        <?php get_template_part('template-parts/badge-grid', null, ['uid' => $bd_u->ID]); ?>

        <a href="<?php echo esc_url(home_url('/bang-xep-hang-thanh-vien/')); ?>" class="inline-block mt-4 text-sm text-brand hover:underline">🏆 Bảng xếp hạng thành viên<?php echo $bd_rank_all ? ' — Hạng của bạn: #' . (int) $bd_rank_all : ''; ?></a>
      </div>
```

- [ ] **Step 3: Handler điểm danh trong `src/main.js`** — trong `DOMContentLoaded`, NGAY SAU handler `[data-bd-unlock]` (mở khóa dự đoán), TRƯỚC `if (typeof Swiper...`, thêm:
```js
    // --- Điểm danh ---
    document.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-bd-checkin]') : null;
      if (!btn || btn.disabled) return;
      var box = btn.closest('[data-bd-checkin-box]');
      if (!box) return;
      btn.disabled = true;
      fetch(box.getAttribute('data-bd-ajax'), {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'bd_checkin', _wpnonce: box.getAttribute('data-bd-nonce') }),
      }).then(function (r) { return r.json(); }).then(function (res) {
        if (res && res.success) {
          var d = res.data;
          var bal = document.querySelector('[data-bd-points-balance]');
          if (bal && typeof d.points === 'number') bal.textContent = d.points;
          var st = box.querySelector('[data-bd-streak]');
          if (st && typeof d.streak === 'number') st.textContent = d.streak;
          btn.textContent = 'Đã điểm danh hôm nay ✓';
          btn.className = 'rounded-full px-4 py-1.5 text-sm font-medium border border-card text-secondary cursor-default';
        } else {
          btn.disabled = false;
        }
      }).catch(function () { btn.disabled = false; });
    });
```

- [ ] **Step 4: Build JS + CSS**
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247 && npm run build:js && npm run build:css
```
Expected: build không lỗi. `grep -c "'bd_checkin'" dist/main.js` = 1.

- [ ] **Step 5: E2E Playwright (controller thực hiện — mô tả)** — kịch bản:
  1. Tạo user test (`wp eval-file` với wp_insert_user, pass biết trước) + cho 100 điểm (để badge "Người mới" sáng).
  2. Đăng nhập `/tai-khoan/` → kiểm: có nút "Điểm danh hôm nay (+2đ)", streak 0, lưới huy hiệu (badge rookie earned — không grayscale), link bảng xếp hạng.
  3. **Render ảnh preview huy hiệu**: `browser_take_screenshot` phần badge-grid → gửi user duyệt "đẹp".
  4. Click điểm danh → số dư +2 (100→102), streak "1", nút thành "Đã điểm danh ✓" (disabled). Reload → vẫn disabled.
  5. Mở `/bang-xep-hang-thanh-vien/?range=all` → user xuất hiện, dòng user tô đậm (`bg-brand/10`).
  6. Khách (logout): `/tai-khoan/` không có `[data-bd-checkin]` (chỉ form đăng nhập); `/bang-xep-hang-thanh-vien/` vẫn xem được, có link đăng nhập.
  7. Dọn user test.

- [ ] **Step 6: Commit**
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/page-tai-khoan.php wp/themes/bongda247/src/main.js wp/themes/bongda247/dist/main.js wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): UI tài khoản — điểm danh + nhiệm vụ + huy hiệu + link xếp hạng"
```

---

## Sau khi xong 6 task

- Cập nhật CLAUDE.md: route `/bang-xep-hang-thanh-vien/`; mục Hệ thống điểm thêm SP4 (điểm danh/streak, nhiệm vụ, huy hiệu, leaderboard, `bd_credit_points`/`bd_points_week`); tree thêm `inc/gamify.php` + `template-parts/badge-grid.php` + page template; data model thêm meta gamify. Ghi chú **production phải tạo WP Page slug `bang-xep-hang-thanh-vien`** (như các trang khác).
- Ledger `.superpowers/sdd/gamify/progress.md`.
- Finishing gate: bot `npm test` (không đụng bot — vẫn phải 15/15); curl `/tai-khoan/` + `/bang-xep-hang-thanh-vien/` + `/` = 200, 0 lỗi PHP; secret scan trước push.
- Merge vào main + push. Gửi ảnh preview huy hiệu cho user.

## Global Constraints (nhắc lại cho reviewer)

Xem phần **Global Constraints** đầu file — mọi task chịu ràng buộc đó (bd_credit_points là cửa cộng điểm duy nhất; AJAX nonce+login+no-nopriv; mốc ngày/tuần timezone site; idempotent/lazy-reset; nhiệm vụ thưởng riêng; huy hiệu suy ra; escape đầy đủ).
