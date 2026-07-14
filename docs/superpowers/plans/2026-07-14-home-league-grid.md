# Gộp tin theo giải → lưới 3 cột trang chủ Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gộp 4 section "tin theo giải" (SP2) thành 1 section "Tin theo giải đấu" dạng lưới 3 cột (NHA · La Liga · Champions League) trên trang chủ WordPress `bongda247`.

**Architecture:** `front-page.php` thay khối loop-4-section bằng 1 `<section>` chứa `grid md:grid-cols-3`, mỗi cột render `template-parts/category-column.php` (mới: tên giải + 1 ảnh+tiêu đề + 2 bullet). Xoá `category-section.php` cũ. Rebuild CSS.

**Tech Stack:** WordPress classic theme (PHP), Tailwind v4 CLI, wp-cli `wp/bin/wp`.

## Global Constraints

- **Site local:** `http://bongda247.local` (http, KHÔNG https).
- **3 giải:** `['ngoai-hang-anh', 'la-liga', 'champions-league']` (bỏ chuyen-nhuong khỏi section này).
- **Section:** 1 tiêu đề "Tin theo giải đấu" (style `font-hemi text-2xl uppercase border-l-4 border-brand pl-4`) + `grid grid-cols-1 md:grid-cols-3 gap-8`.
- **Cột:** tên giải (link archive, `text-brand` uppercase nhỏ) + bài lead (ảnh `bd_hero` + tiêu đề `font-oswald`) + tối đa 2 tin phụ (bullet `▪`, link). Dùng `bd_category_posts($slug, 3)`. Cột 0 bài → return (ẩn).
- **Reuse:** `bd_category_posts` (inc/query.php) — không sửa. Slug qua `get_query_var('bd_cat_slug')`.
- **Xoá:** `template-parts/category-section.php` (chỉ front-page dùng, nay thay bằng category-column).
- **Class Tailwind mới** → rebuild `dist/main.css`. Escape đầu ra (`esc_url`/`esc_html`). Comment tiếng Việt.
- **KHÔNG đụng:** hot-news-slider, sidebar-slider, match-insights (2 section đầu trang chủ giữ nguyên), archive.php, inc/query.php.

---

## Task 1: Section lưới 3 cột + xoá category-section

**Files:**
- Create: `wp/themes/bongda247/template-parts/category-column.php`
- Modify: `wp/themes/bongda247/front-page.php`
- Delete: `wp/themes/bongda247/template-parts/category-section.php`
- Modify: `wp/themes/bongda247/dist/main.css` (rebuild)

**Interfaces:**
- Consumes: `bd_category_posts($slug, $n)`, WP core (`get_category_by_slug`, `get_category_link`, `get_the_post_thumbnail`, `get_permalink`, `get_the_title`).
- Produces: template-part `category-column` (đọc `get_query_var('bd_cat_slug')`).

- [ ] **Step 1: Tạo `template-parts/category-column.php`**

```php
<?php
defined('ABSPATH') || exit;

// Slug truyền từ front-page qua set_query_var('bd_cat_slug', ...).
$bd_slug = get_query_var('bd_cat_slug');
$bd_term = $bd_slug ? get_category_by_slug($bd_slug) : null;
if (!$bd_term) {
    return; // category không tồn tại → ẩn cột
}

$bd_q = bd_category_posts($bd_slug, 3);
if (empty($bd_q->posts)) {
    return; // 0 bài → ẩn cột
}

$bd_posts = $bd_q->posts;
$bd_lead  = $bd_posts[0];
$bd_rest  = array_slice($bd_posts, 1); // tối đa 2 tin phụ
?>

<div>
  <a href="<?php echo esc_url(get_category_link($bd_term)); ?>"
     class="block text-sm font-bold uppercase tracking-wide text-brand mb-4 hover:underline">
    <?php echo esc_html($bd_term->name); ?>
  </a>

  <a href="<?php echo esc_url(get_permalink($bd_lead)); ?>" class="block group mb-4">
    <?php if (has_post_thumbnail($bd_lead)) : ?>
      <div class="rounded-lg overflow-hidden border border-card mb-3 aspect-video">
        <?php echo get_the_post_thumbnail($bd_lead, 'bd_hero', ['class' => 'w-full h-full object-cover transition-transform group-hover:scale-105']); ?>
      </div>
    <?php endif; ?>
    <h3 class="font-oswald text-lg font-bold leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php echo esc_html(get_the_title($bd_lead)); ?></h3>
  </a>

  <?php if ($bd_rest) : ?>
    <ul class="space-y-2">
      <?php foreach ($bd_rest as $bd_p) : ?>
        <li class="flex gap-2 text-sm">
          <span class="text-brand leading-6">▪</span>
          <a href="<?php echo esc_url(get_permalink($bd_p)); ?>" class="text-secondary hover:text-brand transition-colors line-clamp-2"><?php echo esc_html(get_the_title($bd_p)); ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
```

- [ ] **Step 2: Sửa `front-page.php` — thay khối loop bằng 1 section lưới**

Trong `wp/themes/bongda247/front-page.php`, thay khối hiện tại:
```php
<?php
// Block tin theo giải/chủ đề — sửa mảng này để thêm/bớt block.
$bd_home_categories = ['ngoai-hang-anh', 'la-liga', 'champions-league', 'chuyen-nhuong'];
foreach ($bd_home_categories as $bd_cat_slug) :
    set_query_var('bd_cat_slug', $bd_cat_slug);
    get_template_part('template-parts/category-section');
endforeach;
?>
```
bằng:
```php
<section>
  <div class="container">
    <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4 mb-8">Tin theo giải đấu</h2>
    <?php
    // 3 giải mỗi cột — sửa mảng để thêm/bớt cột.
    $bd_home_categories = ['ngoai-hang-anh', 'la-liga', 'champions-league'];
    ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <?php foreach ($bd_home_categories as $bd_cat_slug) :
          set_query_var('bd_cat_slug', $bd_cat_slug);
          get_template_part('template-parts/category-column');
      endforeach; ?>
    </div>
  </div>
</section>
```
(2 section trên — hot-slider+sidebar và match-insights — GIỮ NGUYÊN.)

- [ ] **Step 3: Xoá `category-section.php`**

```bash
git rm wp/themes/bongda247/template-parts/category-section.php
grep -rn "category-section" wp/themes/bongda247/ --include="*.php" || echo "✅ không còn tham chiếu category-section"
```
Expected: `✅ không còn tham chiếu category-section`.

- [ ] **Step 4: Rebuild CSS**

```bash
cd wp/themes/bongda247 && npm run build:css
grep -c "md:grid-cols-3\|aspect-video" dist/main.css
```
Expected: build OK; grep ≥ 1.

- [ ] **Step 5: Tạo 2 bài tạm (để thấy đủ 1 lead + 2 bullet) rồi kiểm chứng**

`ngoai-hang-anh` hiện chỉ 1 bài seed → tạo thêm 2 bài tạm để cột NHA có lead + 2 bullet.

Run (từ repo root):
```bash
CAT=$(./wp/bin/wp term list category --slug=ngoai-hang-anh --field=term_id)
IDS=""
for i in 1 2; do
  ID=$(./wp/bin/wp post create --post_type=post --post_status=publish \
    --post_title="ZZZ NHA cột $i" --post_content="<p>Nội dung $i.</p>" \
    --post_category="$CAT" --porcelain)
  IDS="$IDS $ID"
done
echo "posts tạm:$IDS"

curl -s http://bongda247.local/ -o /tmp/home.html -w "home: HTTP %{http_code}\n"
echo "tiêu đề section 'Tin theo giải đấu': $(grep -c 'Tin theo giải đấu' /tmp/home.html)"
echo "grid 3 cột: $(grep -c 'md:grid-cols-3' /tmp/home.html)"
echo "tên giải NHA: $(grep -c 'Ngoại hạng Anh' /tmp/home.html)"
echo "bullet ▪: $(grep -c '▪' /tmp/home.html)"
echo "bài tạm NHA hiện: $(grep -c 'ZZZ NHA cột' /tmp/home.html)"
echo "Champions League (0 bài → ẩn, phải 0): $(grep -c 'Champions League' /tmp/home.html)"
echo "loi PHP: $(grep -ci 'fatal error\|warning:\|notice:\|deprecated' /tmp/home.html)"
```
Expected:
```
home: HTTP 200
tiêu đề section 'Tin theo giải đấu': 1
grid 3 cột: 1 (≥1)
tên giải NHA: ≥1
bullet ▪: ≥1 (cột NHA có 2 tin phụ)
bài tạm NHA hiện: ≥1
Champions League (0 bài → ẩn, phải 0): 0
loi PHP: 0
```
Lưu ý: cột Champions League ẩn vì chưa có bài — đúng. Nếu `Tin theo giải đấu`=0 hoặc grid=0 → DỪNG BLOCKED.

- [ ] **Step 6: Dọn bài tạm**

```bash
for ID in $IDS; do ./wp/bin/wp post delete "$ID" --force; done
echo "đã xoá bài tạm"
```

- [ ] **Step 7: Commit**

Việc xoá `category-section.php` đã được stage ở Step 3 (`git rm`). Chỉ cần stage 3 file còn lại rồi commit — commit sẽ gộp cả phần xoá đã stage:
```bash
git add wp/themes/bongda247/template-parts/category-column.php \
        wp/themes/bongda247/front-page.php \
        wp/themes/bongda247/dist/main.css
git status --short wp/themes/bongda247   # kỳ vọng: D category-section.php · A category-column.php · M front-page.php · M dist/main.css
git commit -m "feat(theme): gộp tin theo giải thành 1 section lưới 3 cột"
```

---

## Ghi chú
- Thêm/bớt cột: sửa mảng `$bd_home_categories` trong front-page.php.
- Chuyển nhượng vẫn truy cập qua nav + archive.
