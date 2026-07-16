# Dashboard Sidebar Layout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thêm sidebar điều hướng cố định bên trái (desktop) + khối trạng thái tài khoản; giữ nguyên header chính; content/footer dời phải.

**Architecture:** File mới `template-parts/sidebar-nav.php` render `<aside>` cố định trái (chỉ desktop `lg+`). `header.php` include nó + thêm `lg:ml-60` vào `<main>`; `footer.php` offset `lg:ml-60`. Active-state server-side, icon Tabler MIT nhúng thẳng. Không JS mới.

**Tech Stack:** WordPress (PHP 8.2), Tailwind v4, icon Tabler (MIT). Verify: `wp eval` render + curl + Playwright.

**Spec:** `docs/superpowers/specs/2026-07-16-dashboard-sidebar-layout-design.md`

## Global Constraints

- **GIỮ NGUYÊN header chính** (`header.php` phần `<header>`); KHÔNG đụng template nội dung.
- Sidebar **chỉ desktop**: `hidden lg:flex`; mobile ẩn, content full-width, hamburger header lo điều hướng.
- Sidebar cố định `fixed left-0 top-16 bottom-0 w-60 z-40`; `<main>` + `<footer>` offset `lg:ml-60`.
- Active-state qua `is_front_page()` / `is_page('<slug>')` (không JS).
- Icon Tabler MIT **nhúng thẳng path** (không runtime/CDN). Escape đầy đủ `esc_url`/`esc_html`/`esc_attr`.
- Điểm/streak: `bd_get_points($uid)` + user meta `bd_streak`. Span điểm gắn `data-bd-points-balance` (đồng bộ live qua main.js sẵn có).
- WP-CLI qua `./wp/bin/wp` từ gốc `/Users/hotienphong/Desktop/Personal/Bongda247`. Site `http://bongda247.local`.

## File Structure

- `wp/themes/bongda247/template-parts/sidebar-nav.php` (MỚI) — toàn bộ `<aside>` (nav + khối tài khoản).
- `wp/themes/bongda247/header.php` (SỬA) — include sidebar + `<main lg:ml-60>`.
- `wp/themes/bongda247/footer.php` (SỬA) — `<footer lg:ml-60>`.
- Build `dist/main.css`.

---

### Task 1: `template-parts/sidebar-nav.php`

**Files:**
- Create: `wp/themes/bongda247/template-parts/sidebar-nav.php`

**Interfaces:**
- Consumes: `bd_get_points($uid)` (points.php), user meta `bd_streak`, WP `is_front_page()`/`is_page()`/`is_user_logged_in()`/`wp_get_current_user()`/`wp_logout_url()`.
- Produces: `<aside>` markup (included bởi header.php ở Task 2).

- [ ] **Step 1: Tạo `template-parts/sidebar-nav.php`** với nội dung:
```php
<?php
defined('ABSPATH') || exit;

// Nav nhanh: [label, url, active, icon-inner-paths (Tabler outline, MIT)]
$bd_items = [
    [
        'label'  => 'Trang chủ',
        'url'    => home_url('/'),
        'active' => is_front_page(),
        'icon'   => '<path d="M5 12l-2 0l9 -9l9 9l-2 0"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7"/><path d="M10 12h4v4h-4z"/>',
    ],
    [
        'label'  => 'Nhận định',
        'url'    => home_url('/nhan-dinh/'),
        'active' => is_page('nhan-dinh'),
        'icon'   => '<path d="M4 19l16 0"/><path d="M4 15l4 -6l4 2l4 -5l4 4"/>',
    ],
    [
        'label'  => 'Kết quả',
        'url'    => home_url('/ket-qua-bong-da/'),
        'active' => is_page('ket-qua-bong-da'),
        'icon'   => '<path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 7l4.76 3.45l-1.76 5.55h-6l-1.76 -5.55z"/><path d="M12 7v-4m3 13l2.5 3m-.74 -8.55l3.74 -1.45m-11.44 7.05l-2.56 2.95m.74 -8.55l-3.74 -1.45"/>',
    ],
    [
        'label'  => 'Lịch thi đấu',
        'url'    => home_url('/lich-thi-dau/'),
        'active' => is_page('lich-thi-dau'),
        'icon'   => '<path d="M4 5m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"/><path d="M16 3l0 4"/><path d="M8 3l0 4"/><path d="M4 11l16 0"/><path d="M8 15h2v2h-2z"/>',
    ],
    [
        'label'  => 'BXH giải',
        'url'    => home_url('/bang-xep-hang/'),
        'active' => is_page('bang-xep-hang'),
        'icon'   => '<path d="M8 21l8 0"/><path d="M12 17l0 4"/><path d="M7 4l10 0"/><path d="M17 4v8a5 5 0 0 1 -10 0v-8"/><path d="M5 9m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M19 9m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>',
    ],
    [
        'label'  => 'Xếp hạng thành viên',
        'url'    => home_url('/bang-xep-hang-thanh-vien/'),
        'active' => is_page('bang-xep-hang-thanh-vien'),
        'icon'   => '<path d="M9 3h6l3 7l-6 2l-6 -2z"/><path d="M12 12l-3 -9"/><path d="M15 11l-3 -8"/><path d="M12 19.5l-3 1.5l.5 -3.5l-2 -2l3 -.5l1.5 -3l1.5 3l3 .5l-2 2l.5 3.5z"/>',
    ],
];
$bd_star  = '<path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z"/>';
$bd_flame = '<path d="M12 10.941c2.333 -3.308 .167 -7.823 -1 -8.941c0 3.395 -2.235 5.299 -3.667 6.706c-1.43 1.408 -2.333 3.621 -2.333 5.588c0 3.704 3.134 6.706 7 6.706s7 -3.002 7 -6.706c0 -1.712 -1.232 -4.403 -2.333 -5.588c-2.084 3.353 -3.257 3.353 -4.667 2.235"/>';
$bd_logout = '<path d="M10 8v-2a2 2 0 0 1 2 -2h7a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-2"/><path d="M15 12h-12l3 -3"/><path d="M6 15l-3 -3"/>';
$bd_login  = '<path d="M9 8v-2a2 2 0 0 1 2 -2h7a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-2"/><path d="M3 12h13l-3 -3"/><path d="M13 15l3 -3"/>';
?>
<aside class="hidden lg:flex flex-col fixed left-0 top-16 bottom-0 w-60 border-r border-card bg-card z-40 overflow-y-auto">
  <div class="px-4 pt-4 pb-2 text-xs font-hemi uppercase tracking-wide text-secondary">Điều hướng</div>
  <nav class="flex-1 px-3 space-y-1">
    <?php foreach ($bd_items as $bd_it) : ?>
      <a href="<?php echo esc_url($bd_it['url']); ?>"
         class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $bd_it['active'] ? 'bg-brand/10 text-brand' : 'text-secondary hover:text-brand hover:bg-control'; ?>"
         <?php echo $bd_it['active'] ? 'aria-current="page"' : ''; ?>>
        <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $bd_it['icon']; ?></svg>
        <span><?php echo esc_html($bd_it['label']); ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="mt-auto border-t border-card p-3">
    <?php if (is_user_logged_in()) : $bd_su = wp_get_current_user(); ?>
      <div class="text-sm font-semibold truncate"><?php echo esc_html($bd_su->display_name); ?></div>
      <div class="flex items-center gap-4 text-xs mt-1">
        <span class="inline-flex items-center gap-1 text-brand" title="Điểm">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?php echo $bd_star; ?></svg>
          <span data-bd-points-balance><?php echo (int) bd_get_points($bd_su->ID); ?></span>
        </span>
        <span class="inline-flex items-center gap-1 text-secondary" title="Chuỗi điểm danh">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $bd_flame; ?></svg>
          <?php echo (int) get_user_meta($bd_su->ID, 'bd_streak', true); ?>
        </span>
      </div>
      <div class="flex items-center gap-2 mt-3">
        <a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="flex-1 text-center text-xs rounded-lg border border-card py-1.5 text-secondary hover:border-brand hover:text-brand transition-colors">Tài khoản</a>
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="rounded-lg border border-card p-1.5 text-secondary hover:border-brand hover:text-brand transition-colors" aria-label="Đăng xuất" title="Đăng xuất">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $bd_logout; ?></svg>
        </a>
      </div>
    <?php else : ?>
      <a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="flex items-center justify-center gap-2 rounded-lg bg-brand text-white py-2 text-sm font-medium hover:opacity-90 transition-opacity">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $bd_login; ?></svg>
        Đăng nhập / Đăng ký
      </a>
    <?php endif; ?>
  </div>
</aside>
```

- [ ] **Step 2: Verify template render (không fatal, có markup mong đợi)**

Run:
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
./wp/bin/wp eval 'ob_start(); get_template_part("template-parts/sidebar-nav"); $h = ob_get_clean(); echo (strpos($h,"<aside")!==false && strpos($h,"Trang chủ")!==false && strpos($h,"Xếp hạng thành viên")!==false && substr_count($h,"<a ")>=6) ? "OK\n" : "FAIL\n";'
```
Expected: `OK` (render `<aside>`, có mục đầu/cuối, ≥6 link). *(Trong `wp eval` không ở query trang nào → active toàn false, bình thường.)*

- [ ] **Step 3: Commit**
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/template-parts/sidebar-nav.php
git commit -m "feat(theme): sidebar-nav dashboard (nav nhanh + khối tài khoản, icon Tabler)"
```

---

### Task 2: Wire vào header/footer + build + verify

**Files:**
- Modify: `wp/themes/bongda247/header.php`
- Modify: `wp/themes/bongda247/footer.php`
- Build: `wp/themes/bongda247/dist/main.css`

**Interfaces:**
- Consumes: `template-parts/sidebar-nav.php` (Task 1).

- [ ] **Step 1: `header.php` — include sidebar + offset main.** Tìm đoạn cuối file:
```php
    </nav>
  </div>
</header>

<main class="pt-24 pb-16">
```
Đổi thành:
```php
    </nav>
  </div>
</header>

<?php get_template_part('template-parts/sidebar-nav'); ?>

<main class="pt-24 pb-16 lg:ml-60">
```

- [ ] **Step 2: `footer.php` — offset footer.** Đổi:
```php
<footer class="border-t border-card py-10">
```
thành:
```php
<footer class="border-t border-card py-10 lg:ml-60">
```

- [ ] **Step 3: Build CSS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247 && npm run build:css`
Expected: build không lỗi.

- [ ] **Step 4: Verify curl (khách) — sidebar + offset + active + 0 lỗi PHP**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
curl -s "http://bongda247.local/?x=$RANDOM" -o /tmp/home.html
echo "home: <aside>=$(grep -c '<aside' /tmp/home.html) | main lg:ml-60=$(grep -c 'pt-24 pb-16 lg:ml-60' /tmp/home.html) | khối khách 'Đăng nhập / Đăng ký'=$(grep -c 'Đăng nhập / Đăng ký' /tmp/home.html) | lỗi PHP=$(grep -ci 'fatal error\|parse error\|warning:\|notice:' /tmp/home.html)"
echo "home: header chính còn nguyên (BONGDA247 logo)=$(grep -c 'BONGDA<span class=\"text-brand\">247' /tmp/home.html)"
curl -s "http://bongda247.local/nhan-dinh/?x=$RANDOM" -o /tmp/nd.html
echo "nhan-dinh: mục active 'bg-brand/10' quanh link Nhận định=$(grep -o 'bg-brand/10[^>]*>[^<]*<[^>]*>[^<]*Nhận định' /tmp/nd.html | head -1 | grep -c 'Nhận định')"
echo "footer offset lg:ml-60=$(grep -c 'border-t border-card py-10 lg:ml-60' /tmp/home.html)"
```
Expected: home `<aside>`=1, `pt-24 pb-16 lg:ml-60`=1, khối khách=1, lỗi PHP=0, logo header=1, footer offset=1; nhan-dinh mục Nhận định active=1. *(Nếu active grep không khớp do thứ tự thuộc tính, kiểm tay: mục Nhận định phải có class `bg-brand/10 text-brand`.)*

- [ ] **Step 5: Commit**
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/header.php wp/themes/bongda247/footer.php wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): gắn sidebar vào layout — main/footer offset lg:ml-60, giữ header chính"
```

- [ ] **Step 6: E2E Playwright (controller thực hiện)** — kịch bản:
  1. Desktop (viewport ≥1024, VD 1280×800): mở `/` → `<aside>` hiện (visible), `<main>` có margin trái (offset), mục "Trang chủ" active. Chụp ảnh gửi user duyệt.
  2. Bấm sidebar "Nhận định" → sang `/nhan-dinh/`, mục Nhận định active.
  3. Đăng nhập (user test có điểm+streak) → khối đáy sidebar hiện tên + điểm + streak + Tài khoản/Đăng xuất. Điểm khớp.
  4. Mobile (viewport 375×700): `<aside>` **KHÔNG visible** (hidden lg), content full-width, header + hamburger menu bấm mở được.
  5. Dọn user test.

---

## Sau khi xong 2 task

- Cập nhật CLAUDE.md: mô tả layout dashboard (giữ header + sidebar trái desktop + content offset); tree thêm `template-parts/sidebar-nav.php`; ghi Tabler MIT.
- Ledger `.superpowers/sdd/sidebar/progress.md`.
- Finishing gate: bot `npm test` 15/15 (không đụng bot); curl `/`, `/nhan-dinh/`, `/bang-xep-hang-thanh-vien/`, single bài = 200, 0 lỗi PHP; secret scan trước push.
- Merge main + push. Gửi ảnh desktop layout cho user.

## Global Constraints (nhắc lại cho reviewer)

Xem phần **Global Constraints** đầu file — giữ header chính, sidebar desktop-only, offset lg:ml-60, active-state server-side, icon Tabler nhúng thẳng, escape đầy đủ, không đụng template nội dung.
