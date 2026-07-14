# Section Chuyển nhượng + Widget số liệu trang chủ Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thêm vào trang chủ 1 section 2 cột: tin Chuyển nhượng (trái) + widget số liệu compact (BXH/Lịch/Kết quả) có nút chọn giải, đổi giải qua AJAX.

**Architecture:** `fd-widget-body.php` render compact BXH top8 + lịch/KQ 5 trận cho 1 giải; AJAX handler `bd_fd_widget` render lại body theo giải. `fd-widget.php` = dropdown giải + body. `transfer-list.php` = lead+list chuyen-nhuong. `front-page.php` nhúng section 2 cột. `src/main.js` = dropdown→fetch + tab toggle. Tái dùng data SP4.

**Tech Stack:** WordPress classic theme (PHP), WP admin-ajax, JS thuần (fetch), Tailwind v4 CLI, wp-cli.

## Global Constraints

- **Site local:** `http://bongda247.local` (http).
- **Data SP4 (đã có):** `BD_FD_LEAGUES`, `bd_fd_code($slug)`, `bd_fd_standings($code)→rows[]{position,name,crest,played,won,draw,lost,gd,points}`, `bd_fd_fixtures($code)→matches[]{utcDate,status,home,homeCrest,away,awayCrest,sh,sa}`. `bd_category_posts($slug,$n)→WP_Query`.
- **Compact:** BXH **top 8** (Hạng·Đội·Điểm); Lịch **5 trận** status ∈ {SCHEDULED,TIMED}; Kết quả **5 trận** status=FINISHED (mới nhất trước).
- **AJAX:** `admin-ajax.php` action `bd_fd_widget`, `$_GET['league']` qua `sanitize_key`+validate `BD_FD_LEAGUES`, default `ngoai-hang-anh`. Handler render `fd-widget-body` + `wp_die()`.
- **Rate limit:** trang chủ render sẵn giải mặc định = ≤2 call (cache SP4); đổi giải AJAX ≤2 call; đổi tab 0 call. KHÔNG render sẵn 5 giải.
- **Escape:** data ngoài API → `esc_html`/`esc_url`; `$_GET` → sanitize+validate; `selected()` cho option.
- **JS thuần** trong `src/main.js` (event-delegation); giữ nguyên handler cũ (theme-toggle/search/menu/Swiper). Class mới → rebuild `dist/main.css`; sửa src/main.js → rebuild `dist/main.js`.
- Theme: `.container`/`.row`/`.col-8`/`.col-4`, heading `font-hemi ... border-l-4 border-brand`, `bg-card`/`border-card`/`bg-control`/`text-secondary`/`text-brand`.
- **KHÔNG đụng:** các section khác trang chủ (hot-slider, match-insights, lưới giải), inc/query.php, inc/football-data.php, bot.

---

## File Structure

**Tạo mới:**

| File | Trách nhiệm |
|---|---|
| `wp/themes/bongda247/template-parts/fd-widget-body.php` | Phần đổi theo giải: 3 tab + 3 panel compact (BXH/Lịch/KQ) |
| `wp/themes/bongda247/template-parts/fd-widget.php` | Khung widget: heading + dropdown giải + body |
| `wp/themes/bongda247/template-parts/transfer-list.php` | Cột Chuyển nhượng (lead + list) |

**Sửa:**

| File | Thay đổi |
|---|---|
| `wp/themes/bongda247/functions.php` | Đăng ký AJAX handler `bd_fd_widget` |
| `wp/themes/bongda247/front-page.php` | Nhúng section 2 cột (CN + widget) |
| `wp/themes/bongda247/src/main.js` | dropdown→fetch AJAX + tab toggle |
| `wp/themes/bongda247/dist/main.css`, `dist/main.js` | Rebuild |

---

## Task 1: `fd-widget-body.php` + AJAX handler

**Files:**
- Create: `wp/themes/bongda247/template-parts/fd-widget-body.php`
- Modify: `wp/themes/bongda247/functions.php`

**Interfaces:**
- Consumes: `bd_fd_code`, `bd_fd_standings`, `bd_fd_fixtures`, `BD_FD_LEAGUES` (SP4). Slug qua `get_query_var('bd_fd_widget_slug')`.
- Produces: template-part `fd-widget-body` (3 tab + 3 panel); AJAX action `bd_fd_widget`.

- [ ] **Step 1: Tạo `template-parts/fd-widget-body.php`**

```php
<?php
defined('ABSPATH') || exit;

$bd_slug = get_query_var('bd_fd_widget_slug') ?: 'ngoai-hang-anh';
$bd_code = bd_fd_code($bd_slug);
$bd_rows = $bd_code ? array_slice(bd_fd_standings($bd_code), 0, 8) : [];

$bd_up = [];   // trận sắp tới
$bd_res = [];  // kết quả
if ($bd_code) {
    foreach (bd_fd_fixtures($bd_code) as $m) {
        if (($m['status'] ?? '') === 'FINISHED') {
            $bd_res[] = $m;
        } elseif (in_array($m['status'] ?? '', ['SCHEDULED', 'TIMED'], true)) {
            $bd_up[] = $m;
        }
    }
    $bd_res = array_slice(array_reverse($bd_res), 0, 5); // mới nhất trước
    $bd_up  = array_slice($bd_up, 0, 5);
}

// Render 1 dòng trận (dùng chung cho Lịch/KQ).
$bd_row = function ($m, $finished) {
    $ts  = strtotime($m['utcDate'] ?? '');
    $mid = $finished ? (($m['sh'] ?? '') . ' - ' . ($m['sa'] ?? '')) : ($ts ? wp_date('H:i', $ts) : '');
    ob_start(); ?>
    <li class="flex items-center justify-between gap-2 py-1.5 text-xs border-t border-card first:border-t-0">
      <span class="flex-1 text-right truncate"><?php echo esc_html($m['home'] ?? ''); ?></span>
      <span class="px-2 font-bold whitespace-nowrap <?php echo $finished ? 'text-brand' : 'text-secondary'; ?>"><?php echo esc_html($mid); ?></span>
      <span class="flex-1 truncate"><?php echo esc_html($m['away'] ?? ''); ?></span>
    </li>
    <?php return ob_get_clean();
};
?>
<div class="flex gap-1 mb-3">
  <button type="button" data-fd-tab="bxh"  class="px-3 py-1.5 text-xs rounded-full border border-card bg-brand text-white" aria-selected="true">BXH</button>
  <button type="button" data-fd-tab="lich" class="px-3 py-1.5 text-xs rounded-full border border-card text-secondary hover:text-brand" aria-selected="false">Lịch</button>
  <button type="button" data-fd-tab="kq"   class="px-3 py-1.5 text-xs rounded-full border border-card text-secondary hover:text-brand" aria-selected="false">Kết quả</button>
</div>

<div data-fd-panel="bxh">
  <?php if ($bd_rows) : ?>
    <table class="w-full text-xs">
      <tbody>
        <?php foreach ($bd_rows as $r) : ?>
          <tr class="border-t border-card first:border-t-0">
            <td class="py-1.5 pr-2 text-secondary"><?php echo esc_html($r['position']); ?></td>
            <td class="py-1.5">
              <span class="flex items-center gap-1.5">
                <?php if (!empty($r['crest'])) : ?><img src="<?php echo esc_url($r['crest']); ?>" alt="" class="w-4 h-4 object-contain" loading="lazy"><?php endif; ?>
                <span class="truncate"><?php echo esc_html($r['name']); ?></span>
              </span>
            </td>
            <td class="py-1.5 text-right font-bold text-brand"><?php echo esc_html($r['points']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <a href="<?php echo esc_url(add_query_arg('league', $bd_slug, home_url('/bang-xep-hang/'))); ?>" class="block mt-3 text-xs text-secondary hover:text-brand">Xem đầy đủ →</a>
  <?php else : ?>
    <p class="text-xs text-secondary">Chưa có dữ liệu bảng xếp hạng.</p>
  <?php endif; ?>
</div>

<div data-fd-panel="lich" hidden>
  <?php if ($bd_up) : ?>
    <ul><?php foreach ($bd_up as $m) echo $bd_row($m, false); ?></ul>
    <a href="<?php echo esc_url(add_query_arg('league', $bd_slug, home_url('/lich-thi-dau/'))); ?>" class="block mt-3 text-xs text-secondary hover:text-brand">Xem đầy đủ →</a>
  <?php else : ?>
    <p class="text-xs text-secondary">Chưa có lịch thi đấu.</p>
  <?php endif; ?>
</div>

<div data-fd-panel="kq" hidden>
  <?php if ($bd_res) : ?>
    <ul><?php foreach ($bd_res as $m) echo $bd_row($m, true); ?></ul>
    <a href="<?php echo esc_url(add_query_arg('league', $bd_slug, home_url('/lich-thi-dau/'))); ?>" class="block mt-3 text-xs text-secondary hover:text-brand">Xem đầy đủ →</a>
  <?php else : ?>
    <p class="text-xs text-secondary">Chưa có kết quả.</p>
  <?php endif; ?>
</div>
```

- [ ] **Step 2: AJAX handler trong `functions.php`**

Thêm vào CUỐI `wp/themes/bongda247/functions.php`:
```php
// AJAX: render lại widget số liệu theo giải (đổi giải không reload trang).
add_action('wp_ajax_bd_fd_widget', 'bd_fd_widget_ajax');
add_action('wp_ajax_nopriv_bd_fd_widget', 'bd_fd_widget_ajax');
function bd_fd_widget_ajax() {
    $req  = isset($_GET['league']) ? sanitize_key(wp_unslash($_GET['league'])) : '';
    $slug = array_key_exists($req, BD_FD_LEAGUES) ? $req : 'ngoai-hang-anh';
    set_query_var('bd_fd_widget_slug', $slug);
    get_template_part('template-parts/fd-widget-body');
    wp_die();
}
```

- [ ] **Step 3: Kiểm chứng AJAX**

Run (từ repo root):
```bash
curl -s "http://bongda247.local/wp-admin/admin-ajax.php?action=bd_fd_widget&league=ngoai-hang-anh" -o /tmp/w.html -w "ajax NHA: HTTP %{http_code}\n"
echo "tab: $(grep -c 'data-fd-tab' /tmp/w.html)"
echo "panel: $(grep -c 'data-fd-panel' /tmp/w.html)"
echo "BXH có đội (≤8): $(grep -c '<tr' /tmp/w.html)"
curl -s "http://bongda247.local/wp-admin/admin-ajax.php?action=bd_fd_widget&league=la-liga" -o /tmp/w2.html -w "ajax La Liga: HTTP %{http_code}\n"
curl -s "http://bongda247.local/wp-admin/admin-ajax.php?action=bd_fd_widget&league=zzz-bao" -o /tmp/w3.html -w "ajax slug sai (default NHA): HTTP %{http_code}\n"
echo "loi PHP: $(grep -ci 'fatal error\|warning:\|notice:\|deprecated' /tmp/w.html)"
```
Expected: `ajax NHA: HTTP 200`; `data-fd-tab` = 3; `data-fd-panel` = 3; `<tr` từ 1 đến 8 (BXH top 8; off-season vẫn có bảng 20 đội → 8 hàng); `ajax La Liga: HTTP 200`; slug sai vẫn 200 (rơi về NHA); `loi PHP` = 0.

- [ ] **Step 4: Commit**

```bash
git add wp/themes/bongda247/template-parts/fd-widget-body.php wp/themes/bongda247/functions.php
git commit -m "feat(theme): widget-body số liệu compact + AJAX handler bd_fd_widget"
```

---

## Task 2: `fd-widget.php` + `transfer-list.php` + front-page + JS

**Files:**
- Create: `wp/themes/bongda247/template-parts/fd-widget.php`, `wp/themes/bongda247/template-parts/transfer-list.php`
- Modify: `wp/themes/bongda247/front-page.php`, `wp/themes/bongda247/src/main.js`, `wp/themes/bongda247/dist/main.css`, `wp/themes/bongda247/dist/main.js`

**Interfaces:**
- Consumes: `fd-widget-body` (Task 1), AJAX action `bd_fd_widget` (Task 1); `bd_category_posts`, `BD_FD_LEAGUES`.
- Produces: section trang chủ; `[data-fd-league]` (dropdown), `[data-fd-ajax]` (url), `[data-fd-body]` (container), `[data-fd-tab]`/`[data-fd-panel]`.

- [ ] **Step 1: Tạo `template-parts/fd-widget.php`**

```php
<?php
defined('ABSPATH') || exit;
$bd_slug = get_query_var('bd_fd_widget_slug') ?: 'ngoai-hang-anh';
?>
<div class="rounded-2xl border border-card bg-card p-4" data-fd-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
  <div class="flex items-center justify-between gap-2 mb-3">
    <h3 class="font-hemi text-lg uppercase">Số liệu</h3>
    <select data-fd-league class="text-xs bg-control border border-card rounded px-2 py-1 cursor-pointer">
      <?php foreach (BD_FD_LEAGUES as $slug => $lg) : ?>
        <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug, $bd_slug); ?>><?php echo esc_html($lg['name']); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div data-fd-body>
    <?php get_template_part('template-parts/fd-widget-body'); ?>
  </div>
</div>
```

- [ ] **Step 2: Tạo `template-parts/transfer-list.php`**

```php
<?php
defined('ABSPATH') || exit;

$bd_slug = get_query_var('bd_cat_slug');
$bd_term = $bd_slug ? get_category_by_slug($bd_slug) : null;
if (!$bd_term) return;

$bd_q = bd_category_posts($bd_slug, 6);
if (empty($bd_q->posts)) return;

$bd_posts = $bd_q->posts;
$bd_lead  = $bd_posts[0];
$bd_rest  = array_slice($bd_posts, 1); // tối đa 5 tin phụ
?>
<div class="flex items-center justify-between mb-6">
  <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4"><?php echo esc_html($bd_term->name); ?></h2>
  <a href="<?php echo esc_url(get_category_link($bd_term)); ?>" class="text-sm text-secondary hover:text-brand whitespace-nowrap ml-4">Xem tất cả →</a>
</div>

<a href="<?php echo esc_url(get_permalink($bd_lead)); ?>" class="block group mb-5">
  <?php if (has_post_thumbnail($bd_lead)) : ?>
    <div class="rounded-2xl overflow-hidden border border-card mb-3 aspect-video">
      <?php echo get_the_post_thumbnail($bd_lead, 'bd_hero', ['class' => 'w-full h-full object-cover transition-transform group-hover:scale-105', 'alt' => esc_attr(get_the_title($bd_lead))]); ?>
    </div>
  <?php endif; ?>
  <h3 class="font-oswald text-xl font-bold leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php echo esc_html(get_the_title($bd_lead)); ?></h3>
  <p class="text-secondary text-sm mt-2 line-clamp-2"><?php echo esc_html(get_the_excerpt($bd_lead)); ?></p>
</a>

<?php if ($bd_rest) : ?>
  <ul class="grid sm:grid-cols-2 gap-x-6">
    <?php foreach ($bd_rest as $bd_p) : ?>
      <li class="py-2 border-t border-card">
        <a href="<?php echo esc_url(get_permalink($bd_p)); ?>" class="block group">
          <h4 class="text-sm font-medium leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php echo esc_html(get_the_title($bd_p)); ?></h4>
          <time class="text-xs text-secondary"><?php echo esc_html(get_the_date('d/m/Y', $bd_p)); ?></time>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
```

- [ ] **Step 3: Nhúng section vào `front-page.php`**

Trong `wp/themes/bongda247/front-page.php`, chèn NGAY TRƯỚC `<?php get_footer(); ?>` (SAU lưới "TIN THEO GIẢI ĐẤU"):
```php
<section>
  <div class="container">
    <div class="row">
      <div class="col col-8">
        <?php set_query_var('bd_cat_slug', 'chuyen-nhuong'); get_template_part('template-parts/transfer-list'); ?>
      </div>
      <div class="col col-4">
        <?php set_query_var('bd_fd_widget_slug', 'ngoai-hang-anh'); get_template_part('template-parts/fd-widget'); ?>
      </div>
    </div>
  </div>
</section>
```

- [ ] **Step 4: JS trong `src/main.js`**

Thêm 2 khối sau khối `// --- Mobile menu toggle ---` (TRƯỚC khối `// --- Swiper ---`); giữ nguyên các handler cũ:
```js
  // --- Widget số liệu: đổi giải qua AJAX ---
  document.addEventListener("change", function (e) {
    var sel = e.target.closest("[data-fd-league]");
    if (!sel) return;
    var widget = sel.closest("[data-fd-ajax]");
    var body = widget && widget.querySelector("[data-fd-body]");
    if (!widget || !body) return;
    var url = widget.getAttribute("data-fd-ajax") + "?action=bd_fd_widget&league=" + encodeURIComponent(sel.value);
    fetch(url)
      .then(function (r) { return r.text(); })
      .then(function (html) { body.innerHTML = html; })
      .catch(function () {});
  });

  // --- Widget số liệu: đổi tab BXH/Lịch/Kết quả (client) ---
  document.addEventListener("click", function (e) {
    var tab = e.target.closest("[data-fd-tab]");
    if (!tab) return;
    var body = tab.closest("[data-fd-body]");
    if (!body) return;
    var name = tab.getAttribute("data-fd-tab");
    body.querySelectorAll("[data-fd-tab]").forEach(function (b) {
      var active = b === tab;
      b.classList.toggle("bg-brand", active);
      b.classList.toggle("text-white", active);
      b.classList.toggle("text-secondary", !active);
      b.setAttribute("aria-selected", String(active));
    });
    body.querySelectorAll("[data-fd-panel]").forEach(function (p) {
      p.hidden = p.getAttribute("data-fd-panel") !== name;
    });
  });
```

- [ ] **Step 5: Rebuild**

```bash
cd wp/themes/bongda247 && npm run build:css && npm run build:js
grep -c "data-fd-league\|data-fd-body" dist/main.js
grep -c "aspect-video" dist/main.css
```
Expected: build OK; grep JS ≥ 1; grep CSS ≥ 1.

- [ ] **Step 6: Kiểm chứng trang chủ**

Run (từ repo root):
```bash
curl -s "http://bongda247.local/" -o /tmp/h.html -w "home: HTTP %{http_code}\n"
echo "section Chuyển nhượng: $(grep -c 'Chuyển nhượng' /tmp/h.html)"
echo "widget Số liệu: $(grep -c 'data-fd-body' /tmp/h.html)"
echo "dropdown giải: $(grep -c 'data-fd-league' /tmp/h.html)"
echo "3 tab: $(grep -c 'data-fd-tab' /tmp/h.html)"
echo "loi PHP: $(grep -ci 'fatal error\|warning:\|notice:\|deprecated' /tmp/h.html)"
```
Expected: `home: HTTP 200`; `Chuyển nhượng` ≥1; `data-fd-body` = 1; `data-fd-league` ≥1; `data-fd-tab` = 3; `loi PHP` = 0.
(Tương tác dropdown→AJAX + tab do controller kiểm bằng trình duyệt.)

- [ ] **Step 7: Commit**

```bash
git add wp/themes/bongda247/template-parts/fd-widget.php wp/themes/bongda247/template-parts/transfer-list.php \
        wp/themes/bongda247/front-page.php wp/themes/bongda247/src/main.js \
        wp/themes/bongda247/dist/main.css wp/themes/bongda247/dist/main.js
git commit -m "feat(theme): section Chuyển nhượng + widget số liệu (dropdown giải AJAX + tab)"
```

---

## Ghi chú
- Đổi giải mặc định widget: sửa `set_query_var('bd_fd_widget_slug', ...)` trong front-page.php.
- Tab/AJAX tương tác cần controller/human kiểm bằng trình duyệt (curl chỉ xác minh markup + AJAX endpoint).
