# SP3 Mở khóa dự đoán bằng điểm — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Khóa dự đoán tỉ số match_insight; user tiêu 5 điểm để mở khóa (mở 1 lần xem mãi); khách phải đăng nhập.

**Architecture:** `inc/points.php` thêm `bd_spend_points`/`bd_is_unlocked`/AJAX `bd_unlock` + helper render `bd_prediction_badge()`; 2 template card gọi helper thay badge cũ; `src/main.js` xử lý click mở khóa (reveal + trừ điểm). Prediction KHÔNG lộ HTML khi khóa (server chỉ trả khi mở).

**Tech Stack:** WordPress (PHP 8.2), Tailwind v4, JS thuần. Test: `wp eval-file` + Playwright.

**Spec:** `docs/superpowers/specs/2026-07-15-unlock-prediction-design.md`

## Global Constraints

- `BD_UNLOCK_COST = 5`. Mở 1 dự đoán trừ 5đ, **mở rồi xem mãi** (user meta `bd_unlocked_insights`), idempotent (mở lại không trừ).
- AJAX `bd_unlock`: `check_ajax_referer('bd_points')` + `is_user_logged_in` + KHÔNG nopriv; kiểm số dư server-side (`bd_spend_points`); thiếu điểm → error `nopoints` (KHÔNG trừ).
- **Prediction KHÔNG xuất hiện trong HTML khi chưa mở** (helper chỉ render giá trị ở nhánh đã-mở; server trả giá trị qua AJAX khi mở). Khách → link `/tai-khoan/`, không tiêu điểm.
- JS reveal dùng `textContent` (chống XSS), không `innerHTML` cho giá trị. Escape PHP đầy đủ.
- Không phá logic points cũ (SP2.1/2.2/2.3).

---

### Task 1: Backend spend/unlock + helper (`inc/points.php`)

**Files:**
- Modify: `wp/themes/bongda247/inc/points.php` (thêm cuối file)
- Test (tạm, KHÔNG commit): `/tmp/bd_unlock_test.php`

**Interfaces:**
- Consumes: `bd_get_points` (SP2.1).
- Produces: `bd_spend_points($uid,$amount):bool`, `bd_is_unlocked($uid,$iid):bool`, AJAX `bd_unlock`, `bd_prediction_badge($iid,$prediction):string`. Task 2 dùng.

- [ ] **Step 1: Viết test (thất bại vì chưa có hàm)**

Tạo `/tmp/bd_unlock_test.php`:
```php
<?php
$uid = wp_insert_user(['user_login'=>'bd_unlock_'.wp_rand(1000,9999),'user_pass'=>'x','role'=>'subscriber']);
update_user_meta($uid, 'bd_points', 8);
$fail = [];
if (bd_spend_points($uid, 5) !== true) $fail[] = 'spend đủ !true';
if (bd_get_points($uid) !== 3) $fail[] = 'sau spend != 3';
if (bd_spend_points($uid, 5) !== false) $fail[] = 'spend thiếu !false';
if (bd_get_points($uid) !== 3) $fail[] = 'thiếu điểm mà vẫn trừ';
if (bd_is_unlocked($uid, 999) !== false) $fail[] = 'chưa mở mà unlocked';
update_user_meta($uid, 'bd_unlocked_insights', [999]);
if (bd_is_unlocked($uid, 999) !== true) $fail[] = 'đã set mà !unlocked';
require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($uid);
echo $fail ? ('FAIL: '.implode(' | ',$fail)."\n") : "PASS\n";
```

- [ ] **Step 2: Chạy — kỳ vọng FATAL (hàm chưa có)**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_unlock_test.php`
Expected: FATAL `Call to undefined function bd_spend_points()`.

- [ ] **Step 3: Thêm vào cuối `inc/points.php`**

```php
const BD_UNLOCK_COST = 5;

/** Trừ điểm nếu đủ. true nếu vừa trừ, false nếu không đủ. */
function bd_spend_points($uid, $amount) {
    $cur = bd_get_points($uid);
    if ($cur < $amount) {
        return false;
    }
    update_user_meta($uid, 'bd_points', $cur - $amount);
    return true;
}

/** Đã mở khóa insight này chưa? */
function bd_is_unlocked($uid, $iid) {
    $unlocked = array_filter((array) get_user_meta($uid, 'bd_unlocked_insights', true));
    return in_array((int) $iid, array_map('intval', $unlocked), true);
}

// AJAX: mở khóa dự đoán 1 match_insight (trừ 5đ, idempotent).
add_action('wp_ajax_bd_unlock', 'bd_ajax_unlock');
function bd_ajax_unlock() {
    check_ajax_referer('bd_points');
    if (!is_user_logged_in()) {
        wp_send_json_error('auth', 403);
    }
    $iid = (int) ($_POST['insight_id'] ?? 0);
    $p   = get_post($iid);
    if (!$p || $p->post_type !== 'match_insight') {
        wp_send_json_error('invalid', 400);
    }
    $uid  = get_current_user_id();
    $pred = (string) get_post_meta($iid, 'prediction', true);

    if (bd_is_unlocked($uid, $iid)) {
        wp_send_json_success(['points' => bd_get_points($uid), 'prediction' => $pred]);
    }
    if (!bd_spend_points($uid, BD_UNLOCK_COST)) {
        wp_send_json_error('nopoints', 402);
    }
    $unlocked   = array_filter((array) get_user_meta($uid, 'bd_unlocked_insights', true));
    $unlocked[] = $iid;
    update_user_meta($uid, 'bd_unlocked_insights', array_values(array_unique(array_map('intval', $unlocked))));
    wp_send_json_success(['points' => bd_get_points($uid), 'prediction' => $pred]);
}

/** Badge dự đoán: khóa / đã mở / khách. Dùng chung carousel + hub. */
function bd_prediction_badge($iid, $prediction) {
    $prediction = (string) $prediction;
    if ($prediction === '') {
        return '';
    }
    $badge_cls = 'inline-block mt-auto w-fit ml-auto text-sm transition-all p-2 px-4 rounded-full font-hemi bg-prediction';

    if (is_user_logged_in() && bd_is_unlocked(get_current_user_id(), $iid)) {
        return '<div data-bd-pred-gate class="mt-auto ml-auto w-fit"><div class="' . $badge_cls . '">' . esc_html($prediction) . '</div></div>';
    }

    $out = '<div data-bd-pred-gate class="mt-auto ml-auto w-fit">';
    if (!is_user_logged_in()) {
        $out .= '<a href="' . esc_url(home_url('/tai-khoan/')) . '" class="inline-flex items-center gap-1 text-xs rounded-full border border-card px-3 py-1.5 text-secondary hover:text-brand hover:border-brand transition-colors">🔒 Đăng nhập để xem dự đoán</a>';
    } else {
        $out .= '<button type="button" data-bd-unlock data-bd-insight="' . esc_attr($iid) . '" data-bd-ajax="' . esc_url(admin_url('admin-ajax.php')) . '" data-bd-nonce="' . esc_attr(wp_create_nonce('bd_points')) . '" class="inline-flex items-center gap-1 text-xs rounded-full border border-brand px-3 py-1.5 text-brand hover:bg-brand hover:text-white transition-colors cursor-pointer">🔒 Mở khóa (' . (int) BD_UNLOCK_COST . ' điểm)</button>';
    }
    $out .= '</div>';
    return $out;
}
```

- [ ] **Step 4: Chạy lại — kỳ vọng PASS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_unlock_test.php`
Expected: `PASS`

- [ ] **Step 5: Xoá test + verify hook + commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
rm -f /tmp/bd_unlock_test.php
./wp/bin/wp eval 'echo has_action("wp_ajax_bd_unlock")?"unlock OK\n":"MISSING\n"; echo has_action("wp_ajax_nopriv_bd_unlock")?"NOPRIV LEAK\n":"no-nopriv OK\n";'
git add wp/themes/bongda247/inc/points.php
git commit -m "feat(theme): bd_spend_points/bd_unlock + bd_prediction_badge (mở khóa dự đoán 5đ)"
```
Expected: `unlock OK`, `no-nopriv OK`.

---

### Task 2: Khóa 2 render spot + JS mở khóa + build

**Files:**
- Modify: `wp/themes/bongda247/template-parts/insight-card.php`
- Modify: `wp/themes/bongda247/template-parts/match-insights.php`
- Modify: `wp/themes/bongda247/src/main.js`
- Build: `wp/themes/bongda247/dist/main.js` + `dist/main.css`

**Interfaces:**
- Consumes: `bd_prediction_badge` + AJAX `bd_unlock` (Task 1).

- [ ] **Step 1: `insight-card.php` — thay badge.** Thay:
```php
  <?php if ($bd_pred) : ?>
    <div class="inline-block mt-auto w-fit ml-auto text-sm transition-all p-2 px-4 rounded-full font-hemi bg-prediction"><?php echo esc_html($bd_pred); ?></div>
  <?php endif; ?>
```
bằng:
```php
  <?php echo bd_prediction_badge($bd_id, $bd_pred); ?>
```

- [ ] **Step 2: `match-insights.php` — thay badge.** Thay:
```php
            <?php if ($prediction) : ?>
              <div class="inline-block mt-auto w-fit ml-auto text-sm transition-all p-2 px-4 rounded-full font-hemi bg-prediction">
                <?php echo esc_html($prediction); ?>
              </div>
            <?php endif; ?>
```
bằng:
```php
            <?php echo bd_prediction_badge($id, $prediction); ?>
```

- [ ] **Step 3: `src/main.js` — handler mở khóa.** Trong `DOMContentLoaded`, SAU khối `if (bdPts) { ... }` (trước `if (typeof Swiper...`), thêm:
```js
    // --- Mở khóa dự đoán ---
    document.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-bd-unlock]') : null;
      if (!btn) return;
      var ajax = btn.getAttribute('data-bd-ajax');
      var nonce = btn.getAttribute('data-bd-nonce');
      var iid = btn.getAttribute('data-bd-insight');
      btn.disabled = true;
      fetch(ajax, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'bd_unlock', insight_id: iid, _wpnonce: nonce }),
      }).then(function (r) { return r.json(); }).then(function (res) {
        if (res && res.success) {
          var gate = btn.closest('[data-bd-pred-gate]');
          if (gate) {
            gate.innerHTML = '<div class="inline-block mt-auto w-fit ml-auto text-sm transition-all p-2 px-4 rounded-full font-hemi bg-prediction"></div>';
            gate.querySelector('div').textContent = res.data.prediction;
          }
          var bal = document.querySelector('[data-bd-points-balance]');
          if (bal) bal.textContent = res.data.points;
        } else {
          btn.textContent = 'Không đủ điểm';
          btn.disabled = false;
        }
      }).catch(function () { btn.disabled = false; });
    });
```

- [ ] **Step 4: Build JS + CSS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247 && npm run build:js && npm run build:css`
Expected: build không lỗi.

- [ ] **Step 5: Verify (khách — prediction KHÔNG lộ)**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
# seed 1 match_insight có prediction rõ ràng để kiểm
IID=$(./wp/bin/wp post create --post_type=match_insight --post_status=publish --post_title="UnlockTest A vs B" --porcelain)
./wp/bin/wp post meta update "$IID" home_team "TeamA" >/dev/null
./wp/bin/wp post meta update "$IID" away_team "TeamB" >/dev/null
./wp/bin/wp post meta update "$IID" match_time "23:00 - 25/07" >/dev/null
./wp/bin/wp post meta update "$IID" prediction "SECRETPRED TeamA thắng 9-0" >/dev/null
curl -s "http://bongda247.local/nhan-dinh/?x=$RANDOM" -o /tmp/u.html
echo "khách: badge khóa 'Đăng nhập để xem dự đoán': $(grep -c 'Đăng nhập để xem dự đoán' /tmp/u.html) (>=1)"
echo "giá trị prediction LỘ ra? (phải 0): $(grep -c 'SECRETPRED' /tmp/u.html)"
echo "nút data-bd-unlock (khách không có): $(grep -c 'data-bd-unlock' /tmp/u.html) | lỗi PHP: $(grep -ci 'fatal error\|warning:\|notice:' /tmp/u.html)"
echo "JS build có bd_unlock: $(grep -c "'bd_unlock'" wp/themes/bongda247/dist/main.js)"
./wp/bin/wp post delete "$IID" --force >/dev/null
```
Expected: badge khóa ≥1; **`SECRETPRED`=0** (giá trị KHÔNG lộ HTML cho khách); `data-bd-unlock`=0 (khách chỉ có link); lỗi PHP=0; dist/main.js chứa `'bd_unlock'`=1. Nếu `SECRETPRED`>0 → LỖI PAYWALL, điều tra helper. *(E2E đăng nhập → mở khóa → controller Playwright sau.)*

- [ ] **Step 6: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/template-parts/insight-card.php wp/themes/bongda247/template-parts/match-insights.php wp/themes/bongda247/src/main.js wp/themes/bongda247/dist/main.js wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): khóa dự đoán match_insight + nút mở khóa (5đ) ở carousel + hub"
```

---

## Sau khi xong 2 task

- Controller Playwright E2E: nạp điểm user test → /nhan-dinh/ badge khóa → click mở khóa → dự đoán hiện + điểm −5; user 0 điểm → "Không đủ điểm". Dọn user + insight test.
- Cập nhật CLAUDE.md (mở khóa dự đoán 5đ; `bd_prediction_badge`/`bd_spend_points`/`bd_unlock`) + data model (bd_unlocked_insights).
- Ledger `.superpowers/sdd/unlock-prediction/progress.md`.
- Finishing gate (bot `npm test` 15/15; curl trang 200) → merge + push.
- **HOÀN TẤT Giai đoạn 1** (tài khoản + tích điểm đọc/like/comment/share + mở khóa dự đoán). Giai đoạn 2 = nạp tiền (cần pháp lý).
