# Category News Sections (trang chủ) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thêm các block "tin theo giải/chủ đề" (lead + list) vào trang chủ WordPress `bongda247`, mỗi block là bài mới nhất của một category.

**Architecture:** 1 query helper `bd_category_posts` + 1 template-part `category-section.php` tái dùng (nhận slug qua `get_query_var`) + `front-page.php` giữ mảng slug và loop. Dùng data sẵn có (posts theo category), không API ngoài.

**Tech Stack:** WordPress classic theme (PHP), Tailwind CSS v4 build bằng CLI (`npm run build:css` → `dist/main.css`), wp-cli wrapper `wp/bin/wp`.

## Global Constraints

- **Site local:** `http://bongda247.local` (http, KHÔNG https).
- **Theme grid (từ `src/main.css`):** `.row`=flex flex-wrap -mx-3; `.col`=px-3; `.col-8`=`w-full lg:w-2/3`; `.col-4`=`w-full lg:w-1/3`. **KHÔNG có `col-12`/`col-6`.** Khi block chỉ có 1 bài (không có list) → render lead ở `col-8`, không render cột `col-4`.
- **Heading style sẵn có:** `font-hemi text-2xl uppercase border-l-4 border-brand pl-4`. Màu: `text-secondary`, `text-brand`, `hover:text-brand`; viền `border-card`.
- **Tailwind v4 build quét class trong file PHP** (kể cả `template-parts/`). Thêm class mới → **BẮT BUỘC** `npm run build:css` và commit `dist/main.css`, nếu không class mới không có CSS.
- **Category cấu hình (mặc định 4 block):** `['ngoai-hang-anh', 'la-liga', 'champions-league', 'chuyen-nhuong']`, đặt SAU section match-insights.
- **Empty handling:** category không tồn tại hoặc 0 bài → template-part `return` sớm, KHÔNG render (không heading rỗng, không PHP warning).
- **KHÔNG thêm** JS/dependency/motion. Comment code tiếng Việt.
- **KHÔNG đụng:** các helper hiện có trong `inc/query.php`, template-part khác, `single.php`/`archive.php`. Chỉ THÊM `bd_category_posts` và file/loop mới.

---

## File Structure

**Tạo mới:**

| File | Trách nhiệm |
|---|---|
| `wp/themes/bongda247/template-parts/category-section.php` | Render 1 block tin theo giải (heading + lead + list) cho slug nhận qua `get_query_var('bd_cat_slug')` |

**Sửa:**

| File | Thay đổi |
|---|---|
| `wp/themes/bongda247/inc/query.php` | THÊM `bd_category_posts($slug, $n = 5)` |
| `wp/themes/bongda247/front-page.php` | THÊM loop mảng slug → `get_template_part('template-parts/category-section')` sau section match-insights |
| `wp/themes/bongda247/dist/main.css` | Rebuild (chứa class Tailwind mới) |

---

## Task 1: Block tin theo giải (helper + template-part + front-page + build)

**Files:**
- Modify: `wp/themes/bongda247/inc/query.php`
- Create: `wp/themes/bongda247/template-parts/category-section.php`
- Modify: `wp/themes/bongda247/front-page.php`
- Modify: `wp/themes/bongda247/dist/main.css` (rebuild)

**Interfaces:**
- Consumes: WP core (`WP_Query`, `get_category_by_slug`, `get_category_link`, `get_the_post_thumbnail`, `get_permalink`, `get_the_title`, `get_the_excerpt`, `get_the_date`); size ảnh `bd_hero` (đã đăng ký).
- Produces: helper `bd_category_posts($slug, $n = 5) → WP_Query`; template-part đọc `get_query_var('bd_cat_slug')`.

- [ ] **Step 1: Thêm `bd_category_posts` vào `inc/query.php`**

Thêm vào cuối `wp/themes/bongda247/inc/query.php` (trước dấu đóng file nếu có; file hiện không có `?>` cuối — thêm hàm sau `bd_insight_is_upcoming`):

```php
/** N bài mới nhất của 1 category (theo slug) — dùng cho block tin theo giải trên trang chủ. */
function bd_category_posts($slug, $n = 5) {
    return new WP_Query([
        'post_type'           => 'post',
        'category_name'       => $slug,
        'posts_per_page'      => $n,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ]);
}
```

- [ ] **Step 2: Tạo `template-parts/category-section.php`**

Create `wp/themes/bongda247/template-parts/category-section.php`. Dùng mảng `$bd_q->posts` + template tag có tham số `$post` (KHÔNG dùng `the_post()`) nên không đụng global `$post`, không cần `wp_reset_postdata()`:

```php
<?php
defined('ABSPATH') || exit;

// Slug truyền từ front-page qua set_query_var('bd_cat_slug', ...).
$bd_slug = get_query_var('bd_cat_slug');
$bd_term = $bd_slug ? get_category_by_slug($bd_slug) : null;
if (!$bd_term) {
    return; // category không tồn tại → ẩn block
}

$bd_q = bd_category_posts($bd_slug, 5);
if (empty($bd_q->posts)) {
    return; // 0 bài → ẩn block
}

$bd_posts = $bd_q->posts;
$bd_lead  = $bd_posts[0];
$bd_rest  = array_slice($bd_posts, 1); // tối đa 4 tin phụ
?>

<section class="mb-12">
  <div class="container">
    <div class="flex items-center justify-between mb-6">
      <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4"><?php echo esc_html($bd_term->name); ?></h2>
      <a href="<?php echo esc_url(get_category_link($bd_term)); ?>" class="text-sm text-secondary hover:text-brand whitespace-nowrap ml-4">Xem tất cả ›</a>
    </div>

    <div class="row">
      <div class="col col-8">
        <a href="<?php echo esc_url(get_permalink($bd_lead)); ?>" class="block group">
          <?php if (has_post_thumbnail($bd_lead)) : ?>
            <div class="rounded-2xl overflow-hidden border border-card mb-4">
              <?php echo get_the_post_thumbnail($bd_lead, 'bd_hero', ['class' => 'w-full h-auto object-cover transition-transform group-hover:scale-105']); ?>
            </div>
          <?php endif; ?>
          <h3 class="font-oswald text-2xl font-bold leading-tight mb-2 group-hover:text-brand transition-colors"><?php echo esc_html(get_the_title($bd_lead)); ?></h3>
          <p class="text-secondary text-sm mb-2"><?php echo esc_html(get_the_excerpt($bd_lead)); ?></p>
          <time class="text-xs text-secondary"><?php echo esc_html(get_the_date('d/m/Y', $bd_lead)); ?></time>
        </a>
      </div>

      <?php if ($bd_rest) : ?>
        <div class="col col-4">
          <ul>
            <?php foreach ($bd_rest as $bd_p) : ?>
              <li class="py-3 border-t border-card first:border-t-0 first:pt-0">
                <a href="<?php echo esc_url(get_permalink($bd_p)); ?>" class="block group">
                  <h4 class="font-medium leading-snug group-hover:text-brand transition-colors"><?php echo esc_html(get_the_title($bd_p)); ?></h4>
                  <time class="text-xs text-secondary"><?php echo esc_html(get_the_date('d/m/Y', $bd_p)); ?></time>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
```

- [ ] **Step 3: Wire loop vào `front-page.php`**

Trong `wp/themes/bongda247/front-page.php`, chèn khối sau GIỮA section match-insights (`</section>` của nó) và `<?php get_footer(); ?>`:

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

File `front-page.php` sau khi sửa (tham chiếu đầy đủ):
```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<section>
  <div class="container">
    <div class="row">
      <div class="col col-8">
        <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4 mb-6">Tin mới cập nhật</h2>
        <?php get_template_part('template-parts/hot-news-slider'); ?>
      </div>
      <div class="col col-4">
        <?php get_template_part('template-parts/sidebar-slider'); ?>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="container">
    <?php get_template_part('template-parts/match-insights'); ?>
  </div>
</section>

<?php
// Block tin theo giải/chủ đề — sửa mảng này để thêm/bớt block.
$bd_home_categories = ['ngoai-hang-anh', 'la-liga', 'champions-league', 'chuyen-nhuong'];
foreach ($bd_home_categories as $bd_cat_slug) :
    set_query_var('bd_cat_slug', $bd_cat_slug);
    get_template_part('template-parts/category-section');
endforeach;
?>

<?php get_footer(); ?>
```

- [ ] **Step 4: Rebuild CSS (class Tailwind mới)**

Run:
```bash
cd wp/themes/bongda247 && npm run build:css
```
Expected: build thành công, không lỗi "Cannot apply unknown utility class". Kiểm tra class mới có trong build:
```bash
grep -c "whitespace-nowrap" dist/main.css
grep -c "group-hover" dist/main.css
```
Expected: cả hai ≥ 1.

- [ ] **Step 5: Tạo dữ liệu test (để thấy đủ lead + list) rồi kiểm chứng**

`ngoai-hang-anh` hiện chỉ có 1 bài seed → tạo thêm 4 bài tạm để block hiện đủ lead + 4 tin phụ. `champions-league` không có bài seed → block đó phải tự ẩn.

Run (từ repo root):
```bash
CAT=$(./wp/bin/wp term list category --slug=ngoai-hang-anh --field=term_id)
IDS=""
for i in 1 2 3 4; do
  ID=$(./wp/bin/wp post create --post_type=post --post_status=publish \
    --post_title="ZZZ NHA test $i" --post_content="<p>Nội dung test $i.</p>" \
    --post_category="$CAT" --porcelain)
  IDS="$IDS $ID"
done
echo "Tạo posts tạm:$IDS"

curl -s http://bongda247.local/ -o /tmp/bd-home.html -w "HTTP %{http_code}\n"
echo "heading NHA: $(grep -c 'Ngoại hạng Anh' /tmp/bd-home.html)"
echo "link Xem tất cả NHA: $(grep -c '/ngoai-hang-anh/' /tmp/bd-home.html)"
echo "bài test NHA hiện: $(grep -c 'ZZZ NHA test' /tmp/bd-home.html)"
echo "heading Champions League (phải = 0, block ẩn vì 0 bài): $(grep -c 'Champions League' /tmp/bd-home.html)"
echo "loi PHP: $(grep -ci 'fatal error\|warning:\|notice:\|deprecated' /tmp/bd-home.html)"
```
Expected:
```
HTTP 200
heading NHA: 1        (≥1)
link Xem tất cả NHA: ≥1
bài test NHA hiện: 4  (4 tin phụ; bài seed thứ 5 làm lead)
heading Champions League (phải = 0 ...): 0   (block CL ẩn vì chưa có bài)
loi PHP: 0
```
Lưu ý: nếu `bài test NHA hiện` = 4 nghĩa là 4 bài tạm nằm ở cột list (tin phụ) và bài mới nhất (một trong các bài tạm) làm lead — con số có thể là 4 hoặc 5 tuỳ thứ tự; miễn ≥4 và heading + link + không lỗi PHP là đạt. `Champions League` phải = 0.

- [ ] **Step 6: Dọn dữ liệu test**

Run:
```bash
for ID in $IDS; do ./wp/bin/wp post delete "$ID" --force; done
echo "Đã xoá posts tạm"
```
Expected: mỗi ID `Success: Deleted post <ID>.`

- [ ] **Step 7: Commit**

```bash
git add wp/themes/bongda247/inc/query.php \
        wp/themes/bongda247/template-parts/category-section.php \
        wp/themes/bongda247/front-page.php \
        wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): block tin theo giải/chủ đề trên trang chủ"
```

---

## Ghi chú (ngoài phạm vi plan)

- Thêm/bớt giải: sửa mảng `$bd_home_categories` trong `front-page.php`.
- "Xem nhiều nhất" (cần view counter), video, tab lọc JS, phân trang — sub-project/phase sau.
- Deploy prod: `dist/main.css` đã build sẵn commit vào repo nên upload theme là chạy, không cần build trên host.
