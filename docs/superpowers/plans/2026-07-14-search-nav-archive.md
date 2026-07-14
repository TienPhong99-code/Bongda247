# Search + Mobile Nav + Archive nâng cao Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thêm tìm kiếm (icon header + trang kết quả), mobile nav (hamburger), mô tả giải trên archive, và prefix "Chủ đề:" cho tag vào theme WordPress `bongda247`.

**Architecture:** `header.php` giữ mảng nav 4 giải + nút search/hamburger + 2 panel ẩn (search form, mobile menu); `src/main.js` thêm 2 toggle (event-delegation, khớp style theme-toggle sẵn có); `search.php` mới render kết quả; `archive.php` thêm mô tả term + prefix tag. Build lại `dist/main.js` (copy) + `dist/main.css` (tailwind).

**Tech Stack:** WordPress classic theme (PHP), JS thuần (no dependency), Tailwind v4 CLI. Build: `npm run build:css` + `npm run build:js` (build:js chỉ `cp src/main.js dist/main.js`). wp-cli wrapper `wp/bin/wp`.

## Global Constraints

- **Site local:** `http://bongda247.local` (http, KHÔNG https).
- **Nav 4 giải (đồng bộ trang chủ):** `ngoai-hang-anh`, `la-liga`, `champions-league`, `chuyen-nhuong`.
- **Nút style (khớp `theme-toggle.php`):** `p-2 rounded-full border border-card bg-control cursor-pointer transition-colors hover:text-brand`. Icon: SVG inline (theme chưa dùng thư viện icon).
- **JS trong `src/main.js`** (IIFE, event-delegation `e.target.closest(...)`); giữ nguyên theme-toggle + Swiper. Sau khi sửa **BẮT BUỘC** `npm run build:js` (copy sang dist).
- **Class Tailwind mới** (trong PHP) → **BẮT BUỘC** `npm run build:css`.
- **Search:** form GET `action=home_url name="s"` → WP search → `search.php`.
- **Escape:** `esc_url`/`esc_html`/`esc_attr`; `get_search_query()` cho từ khoá; `wp_kses_post()` cho `term_description()`.
- **Search icon LUÔN hiện** (mọi breakpoint) → mobile search dùng chung icon+dropdown này (không cần form riêng trong mobile menu). Mobile menu chỉ chứa nav giải + link trang tĩnh.
- **KHÔNG thêm** dependency/motion nặng. Comment code tiếng Việt.
- **KHÔNG đụng:** front-page.php, single.php, các template-part hiện có, `inc/query.php`.

---

## File Structure

**Tạo mới:**

| File | Trách nhiệm |
|---|---|
| `wp/themes/bongda247/search.php` | Trang kết quả tìm kiếm |

**Sửa:**

| File | Thay đổi |
|---|---|
| `wp/themes/bongda247/header.php` | Nav 4 giải + nút search/hamburger + panel search + panel mobile menu |
| `wp/themes/bongda247/src/main.js` | Thêm 2 toggle (search, mobile menu) |
| `wp/themes/bongda247/archive.php` | Prefix "Chủ đề:" cho tag + render mô tả term |
| `wp/themes/bongda247/dist/main.js` | Rebuild (copy từ src) |
| `wp/themes/bongda247/dist/main.css` | Rebuild (class mới) |

---

## Task 1: Header (nav + search + mobile menu) + `search.php` + JS

**Files:**
- Modify: `wp/themes/bongda247/header.php`
- Create: `wp/themes/bongda247/search.php`
- Modify: `wp/themes/bongda247/src/main.js`
- Modify: `wp/themes/bongda247/dist/main.js`, `wp/themes/bongda247/dist/main.css` (rebuild)

**Interfaces:**
- Consumes: WP core (`get_term_by`, `get_term_link`, `get_page_by_path`, `get_permalink`, `get_search_query`, `home_url`, `$wp_query->found_posts`, `paginate_links`).
- Produces: `search.php` template; header có `[data-search-toggle]` → toggle `#bd-search`, `[data-menu-toggle]` → toggle `#bd-mobile-menu`.

- [ ] **Step 1: Thay `header.php`**

Thay toàn bộ `wp/themes/bongda247/header.php` bằng:

```php
<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>
    // Chạy trước khi render để không nháy màu khi reload
    (function () {
      var saved = localStorage.getItem('theme');
      var dark = saved ? saved === 'dark'
                       : window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (dark) document.documentElement.classList.add('dark');
    })();
  </script>
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
// Nav giải — đồng bộ 4 giải với trang chủ. Dùng cho cả nav desktop lẫn mobile.
$bd_nav = [
    ['name' => 'Ngoại hạng Anh',  'slug' => 'ngoai-hang-anh'],
    ['name' => 'La Liga',         'slug' => 'la-liga'],
    ['name' => 'Champions League','slug' => 'champions-league'],
    ['name' => 'Chuyển nhượng',   'slug' => 'chuyen-nhuong'],
];
// Trang tĩnh cho mobile menu (khớp footer).
$bd_menu_pages = ['gioi-thieu' => 'Giới thiệu', 'lien-he' => 'Liên hệ'];
?>

<header class="fixed top-0 w-full z-50 header">
  <div class="container mx-auto">
    <nav>
      <div class="flex items-center justify-between h-16">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="flex items-center space-x-2 group">
          <span class="font-hemi text-2xl font-bold uppercase">BONGDA<span class="text-brand">247</span></span>
        </a>

        <ul class="hidden lg:flex space-x-8">
          <?php foreach ($bd_nav as $item) :
              $term = get_term_by('slug', $item['slug'], 'category');
              if (!$term) continue; ?>
            <li>
              <a href="<?php echo esc_url(get_term_link($term)); ?>"
                 class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">
                <?php echo esc_html($item['name']); ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="flex items-center space-x-3">
          <button data-search-toggle aria-label="Tìm kiếm"
                  class="p-2 rounded-full border border-card bg-control cursor-pointer transition-colors hover:text-brand">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
          </button>

          <?php get_template_part('template-parts/theme-toggle'); ?>

          <button data-menu-toggle aria-label="Menu"
                  class="lg:hidden p-2 rounded-full border border-card bg-control cursor-pointer transition-colors hover:text-brand">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M3 12h18M3 18h18"></path></svg>
          </button>
        </div>
      </div>

      <!-- Ô tìm kiếm (ẩn; icon xổ bằng JS) -->
      <div id="bd-search" class="hidden pb-4">
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="relative">
          <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Tìm bài viết..."
                 class="w-full rounded-lg bg-card border border-card px-4 py-3 pr-11 text-sm focus:outline-none focus:border-brand">
          <button type="submit" aria-label="Tìm" class="absolute right-3 top-1/2 -translate-y-1/2 text-secondary hover:text-brand">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
          </button>
        </form>
      </div>

      <!-- Menu mobile (ẩn; hamburger xổ bằng JS) -->
      <div id="bd-mobile-menu" class="hidden lg:hidden pb-4">
        <ul class="flex flex-col gap-1">
          <?php foreach ($bd_nav as $item) :
              $term = get_term_by('slug', $item['slug'], 'category');
              if (!$term) continue; ?>
            <li><a href="<?php echo esc_url(get_term_link($term)); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand"><?php echo esc_html($item['name']); ?></a></li>
          <?php endforeach; ?>
          <li class="border-t border-card mt-2 pt-2"></li>
          <?php foreach ($bd_menu_pages as $bd_slug => $bd_label) :
              $bd_page = get_page_by_path($bd_slug);
              if (!$bd_page) continue; ?>
            <li><a href="<?php echo esc_url(get_permalink($bd_page)); ?>" class="block py-2 text-sm text-secondary hover:text-brand"><?php echo esc_html($bd_label); ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </nav>
  </div>
</header>

<main class="pt-24 pb-16">
```

- [ ] **Step 2: Thêm 2 toggle vào `src/main.js`**

Trong `wp/themes/bongda247/src/main.js`, thêm 2 khối sau NGAY SAU khối `// --- Theme toggle ---` (trước khối `// --- Swiper ---`):

```js
  // --- Search toggle (icon xổ ô nhập) ---
  document.addEventListener("click", function (e) {
    if (!e.target.closest("[data-search-toggle]")) return;
    var box = document.getElementById("bd-search");
    if (!box) return;
    box.classList.toggle("hidden");
    if (!box.classList.contains("hidden")) {
      var input = box.querySelector('input[type="search"]');
      if (input) input.focus();
    }
  });

  // --- Mobile menu toggle (hamburger) ---
  document.addEventListener("click", function (e) {
    if (!e.target.closest("[data-menu-toggle]")) return;
    var menu = document.getElementById("bd-mobile-menu");
    if (menu) menu.classList.toggle("hidden");
  });
```

- [ ] **Step 3: Tạo `search.php`**

Create `wp/themes/bongda247/search.php`:

```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-2">
    Kết quả tìm kiếm cho: "<?php echo esc_html(get_search_query()); ?>"
  </h1>
  <p class="text-secondary text-sm mb-8"><?php echo esc_html($wp_query->found_posts); ?> kết quả</p>

  <?php if (have_posts()) : ?>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <?php while (have_posts()) : the_post(); ?>
        <article class="rounded-lg bg-card border border-card overflow-hidden group">
          <a href="<?php the_permalink(); ?>" class="block">
            <?php if (has_post_thumbnail()) : ?>
              <div class="overflow-hidden aspect-video">
                <?php the_post_thumbnail('bd_hero', ['class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500']); ?>
              </div>
            <?php endif; ?>
            <div class="p-4">
              <h2 class="text-lg leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php the_title(); ?></h2>
              <p class="text-sm text-secondary mt-2 line-clamp-2"><?php echo esc_html(get_the_excerpt()); ?></p>
              <time class="text-xs text-secondary mt-3 block"><?php echo esc_html(get_the_date('d/m/Y')); ?></time>
            </div>
          </a>
        </article>
      <?php endwhile; ?>
    </div>

    <div class="mt-10 flex justify-center gap-2">
      <?php echo paginate_links(['prev_text' => '‹', 'next_text' => '›']); ?>
    </div>
  <?php else : ?>
    <p class="text-secondary mb-6">Không tìm thấy bài viết nào cho "<?php echo esc_html(get_search_query()); ?>".</p>
    <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="relative max-w-md">
      <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Thử từ khoá khác..."
             class="w-full rounded-lg bg-card border border-card px-4 py-3 pr-11 text-sm focus:outline-none focus:border-brand">
      <button type="submit" aria-label="Tìm" class="absolute right-3 top-1/2 -translate-y-1/2 text-secondary hover:text-brand">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
      </button>
    </form>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 4: Rebuild JS + CSS**

Run:
```bash
cd wp/themes/bongda247 && npm run build:js && npm run build:css
```
Expected: không lỗi. Kiểm tra:
```bash
grep -c "data-search-toggle" dist/main.js
grep -c "bd-mobile-menu" dist/main.js
grep -c "focus:border-brand\|border-brand" dist/main.css
```
Expected: cả ba ≥ 1 (JS đã copy toggle mới; CSS đã có class mới).

- [ ] **Step 5: Kiểm chứng search + markup header (curl)**

Run (từ repo root). Seed có bài "Arsenal đè bẹp Chelsea 3-0" nên tìm "Arsenal" phải ra:
```bash
curl -s "http://bongda247.local/?s=Arsenal" -o /tmp/bd-search.html -w "search: HTTP %{http_code}\n"
echo "tiêu đề KQ: $(grep -c 'Kết quả tìm kiếm cho' /tmp/bd-search.html)"
echo "có bài Arsenal: $(grep -c 'Arsenal' /tmp/bd-search.html)"

curl -s "http://bongda247.local/?s=zzzkhongcogi123" -o /tmp/bd-search0.html -w "search rỗng: HTTP %{http_code}\n"
echo "thông báo rỗng: $(grep -c 'Không tìm thấy' /tmp/bd-search0.html)"

curl -s "http://bongda247.local/" -o /tmp/bd-home.html
echo "search toggle btn: $(grep -c 'data-search-toggle' /tmp/bd-home.html)"
echo "menu toggle btn: $(grep -c 'data-menu-toggle' /tmp/bd-home.html)"
echo "mobile menu panel: $(grep -c 'id=\"bd-mobile-menu\"' /tmp/bd-home.html)"
echo "nav Champions League: $(grep -c 'Champions League' /tmp/bd-home.html)"
echo "loi PHP home+search: $(grep -ci 'fatal error\|warning:\|notice:\|deprecated' /tmp/bd-home.html /tmp/bd-search.html)"
```
Expected:
```
search: HTTP 200
tiêu đề KQ: 1 (≥1)
có bài Arsenal: ≥1
search rỗng: HTTP 200
thông báo rỗng: 1 (≥1)
search toggle btn: ≥1
menu toggle btn: ≥1
mobile menu panel: 1 (≥1)
nav Champions League: ≥1
loi PHP home+search: 0
```
Lưu ý: tương tác JS (bấm icon xổ ô, bấm hamburger xổ menu) sẽ được controller kiểm bằng trình duyệt ở bước review — bước curl này chỉ xác minh markup + JS đã build + trang search hoạt động.

- [ ] **Step 6: Commit**

```bash
git add wp/themes/bongda247/header.php \
        wp/themes/bongda247/search.php \
        wp/themes/bongda247/src/main.js \
        wp/themes/bongda247/dist/main.js \
        wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): tìm kiếm + mobile nav (hamburger) + nav 4 giải"
```

---

## Task 2: Archive nâng cao — mô tả giải + prefix tag

**Files:**
- Modify: `wp/themes/bongda247/archive.php`

**Interfaces:**
- Consumes: WP core (`is_tag`, `single_term_title`, `get_the_archive_title`, `term_description`).
- Produces: không có (thay đổi hiển thị archive).

- [ ] **Step 1: Sửa phần tiêu đề `archive.php`**

Trong `wp/themes/bongda247/archive.php`, thay khối `<h1>...</h1>` hiện tại (dòng 4–6) bằng:

```php
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-4">
    <?php
    if (is_tag()) {
        echo 'Chủ đề: ' . esc_html(single_term_title('', false));
    } else {
        echo esc_html(single_term_title('', false) ?: get_the_archive_title());
    }
    ?>
  </h1>

  <?php $bd_desc = term_description(); if ($bd_desc) : ?>
    <div class="prose-bd text-secondary max-w-3xl mb-8"><?php echo wp_kses_post($bd_desc); ?></div>
  <?php endif; ?>
```

(Phần grid bài + phân trang + empty phía dưới GIỮ NGUYÊN.)

- [ ] **Step 2: Kiểm chứng tag prefix**

Seed post có tag "Bóng đá"/"Phân tích". Lấy 1 URL tag và curl:
```bash
TAG_URL=$(./wp/bin/wp term list post_tag --number=1 --field=url 2>/dev/null)
echo "Tag URL: $TAG_URL"
curl -s "$TAG_URL" -o /tmp/bd-tag.html -w "tag: HTTP %{http_code}\n"
echo "prefix Chủ đề: $(grep -c 'Chủ đề:' /tmp/bd-tag.html)"
```
Expected: `tag: HTTP 200`; `prefix Chủ đề: 1` (≥1).

- [ ] **Step 3: Kiểm chứng mô tả category**

Đặt mô tả tạm cho `ngoai-hang-anh`, curl archive, rồi xoá mô tả (giữ sạch state):
```bash
CATID=$(./wp/bin/wp term list category --slug=ngoai-hang-anh --field=term_id)
./wp/bin/wp term update category "$CATID" --description="Tin tức và nhận định Ngoại hạng Anh."
curl -s "http://bongda247.local/ngoai-hang-anh/" -o /tmp/bd-cat.html -w "cat: HTTP %{http_code}\n"
echo "mô tả hiện: $(grep -c 'Tin tức và nhận định Ngoại hạng Anh' /tmp/bd-cat.html)"
echo "loi PHP: $(grep -ci 'fatal error\|warning:\|notice:\|deprecated' /tmp/bd-cat.html /tmp/bd-tag.html)"
./wp/bin/wp term update category "$CATID" --description=""
```
Expected: `cat: HTTP 200`; `mô tả hiện: 1` (≥1); `loi PHP: 0`.

- [ ] **Step 4: Commit**

```bash
git add wp/themes/bongda247/archive.php
git commit -m "feat(theme): mô tả giải trên archive + prefix 'Chủ đề:' cho tag"
```

---

## Ghi chú (ngoài phạm vi plan)

- Mô tả giải: nhập trong WP admin (Posts → Categories → Description). Bot không tự set.
- Search nâng cao (lọc theo giải, live-search), BXH/lịch (SP4) — phase sau.
- Deploy prod: `dist/main.js` + `dist/main.css` build sẵn commit → upload theme là chạy.
