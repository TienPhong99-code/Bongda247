# SEO-SP2 E-E-A-T single.php — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thêm author box + ngày cập nhật + bài viết liên quan + mục lục (TOC) vào trang bài viết `single.php` (tăng E-E-A-T + AI-citation).

**Architecture:** 1 hàm thuần `bd_toc()` (sinh TOC + gắn id H2), 2 template-part (`author-box.php`, `related-posts.php`), sửa `single.php` để nối 4 phần, + đặt thông tin user `bot` qua wp-cli.

**Tech Stack:** WordPress classic theme (PHP 8.2), Tailwind v4 (`npm run build:css`). Test hàm PHP qua `./wp/bin/wp eval-file`; verify curl + Playwright.

**Spec:** `docs/superpowers/specs/2026-07-15-single-eeat-design.md`

## Global Constraints

- Escape MỌI dữ liệu: `esc_url` (link), `esc_html` (tên/tiêu đề/ngày/text), `esc_attr` (id/attr). `echo $bd_c['content']` là HTML đã qua filter `the_content` (chuẩn WP, không cần esc lại).
- KHÔNG thêm JS (TOC = anchor `<a href="#id">` thuần) và KHÔNG gọi API ngoài.
- `bd_toc`: id `muc-N` (N tuần tự), H2 đã có `id` → GIỮ nguyên (không đè); `text = trim(wp_strip_all_tags(inner))`; H2 text rỗng → không đưa vào `items`.
- TOC chỉ render hộp khi `count(items) >= 3`.
- Ngày cập nhật chỉ hiện khi `get_the_modified_time('U') > get_the_date('U') + HOUR_IN_SECONDS`.
- Bài liên quan: 3 bài CÙNG category, loại bài hiện tại; 0 bài → ẩn.
- User `bot`: display_name "Ban Biên Tập Bongda247", user_nicename `ban-bien-tap`, description (bio).
- Bám class theme (`bg-card`/`bg-control`/`border-card`, `font-hemi`/`font-oswald`, `text-secondary`/`text-brand`); ảnh `bd_hero`.

---

### Task 1: Hàm `bd_toc()` (inc/toc.php)

**Files:**
- Create: `wp/themes/bongda247/inc/toc.php`
- Modify: `wp/themes/bongda247/functions.php` (require file mới, cạnh require query.php/football-data.php)
- Test (tạm, KHÔNG commit): `/tmp/bd_toc_test.php`

**Interfaces:**
- Produces: `bd_toc($html)` → `['items' => [ ['id'=>string,'text'=>string], ... ], 'content' => string]`. Task 2 dùng.

- [ ] **Step 1: Viết test tạm (thất bại vì hàm chưa có)**

Tạo `/tmp/bd_toc_test.php`:
```php
<?php
$html = '<h2>Bối cảnh</h2><p>a</p><h2 class="z">Phong độ</h2><p>b</p><h2 id="san-co">Dự đoán</h2>';
$r = bd_toc($html);
$fail = [];
if (count($r['items']) !== 3) $fail[] = 'items='.count($r['items']).' (mong 3)';
if (($r['items'][0]['id'] ?? '') !== 'muc-1') $fail[] = 'items[0].id != muc-1';
if (($r['items'][0]['text'] ?? '') !== 'Bối cảnh') $fail[] = 'items[0].text sai';
if (($r['items'][1]['id'] ?? '') !== 'muc-2') $fail[] = 'items[1].id != muc-2 (H2 có class vẫn gắn id)';
if (($r['items'][2]['id'] ?? '') !== 'san-co') $fail[] = 'items[2].id != san-co (H2 có id sẵn phải GIỮ)';
if (strpos($r['content'], 'id="muc-1"') === false) $fail[] = 'content thiếu id="muc-1"';
if (strpos($r['content'], 'class="z"') === false) $fail[] = 'content mất class gốc';
if (strpos($r['content'], 'id="san-co"') === false) $fail[] = 'content mất id sẵn có';
// 1 H2 → items = 1 (single.php sẽ ẩn TOC)
$r2 = bd_toc('<h2>Chỉ một</h2>');
if (count($r2['items']) !== 1) $fail[] = 'case 1 H2: items != 1';
// 0 H2 → items rỗng, content nguyên vẹn
$r3 = bd_toc('<p>không có heading</p>');
if (count($r3['items']) !== 0 || $r3['content'] !== '<p>không có heading</p>') $fail[] = 'case 0 H2 sai';
echo $fail ? ('FAIL: '.implode(' | ',$fail)."\n") : "PASS\n";
```

- [ ] **Step 2: Chạy test — kỳ vọng FATAL (hàm chưa có)**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_toc_test.php`
Expected: FATAL `Call to undefined function bd_toc()`.

- [ ] **Step 3: Tạo `inc/toc.php`**

```php
<?php
defined('ABSPATH') || exit;

/**
 * Sinh mục lục (TOC) từ các <h2> trong HTML nội dung.
 * Gắn id="muc-N" (N tuần tự) vào H2 chưa có id; H2 đã có id → giữ nguyên.
 * @return array ['items' => [ ['id'=>string,'text'=>string], ... ], 'content' => string]
 */
function bd_toc($html) {
    $items = [];
    $i = 0;
    $content = preg_replace_callback('/<h2\b([^>]*)>(.*?)<\/h2>/is', function ($m) use (&$items, &$i) {
        $attrs = $m[1];
        $inner = $m[2];
        $text  = trim(wp_strip_all_tags($inner));
        // H2 đã có id → giữ nguyên, dùng id sẵn có cho anchor.
        if (preg_match('/\bid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $idm)) {
            if ($text !== '') $items[] = ['id' => $idm[1], 'text' => $text];
            return $m[0];
        }
        $i++;
        $id = 'muc-' . $i;
        if ($text !== '') $items[] = ['id' => $id, 'text' => $text];
        return '<h2 id="' . $id . '"' . $attrs . '>' . $inner . '</h2>';
    }, $html);

    return ['items' => $items, 'content' => $content];
}
```

- [ ] **Step 4: Require trong `functions.php`**

Thêm dòng require cạnh các require `inc/query.php` / `inc/football-data.php` (đầu file, sau khối mở `<?php`):
```php
require_once get_stylesheet_directory() . '/inc/toc.php';
```

- [ ] **Step 5: Chạy lại test — kỳ vọng PASS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_toc_test.php`
Expected: `PASS`

- [ ] **Step 6: Xoá test tạm**

Run: `rm -f /tmp/bd_toc_test.php`

- [ ] **Step 7: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/inc/toc.php wp/themes/bongda247/functions.php
git commit -m "feat(theme): bd_toc — sinh mục lục + gắn id H2 cho bài viết"
```

---

### Task 2: author-box + related-posts + tích hợp single.php + set user

**Files:**
- Create: `wp/themes/bongda247/template-parts/author-box.php`
- Create: `wp/themes/bongda247/template-parts/related-posts.php`
- Modify: `wp/themes/bongda247/single.php`
- Build: `wp/themes/bongda247/dist/main.css`

**Interfaces:**
- Consumes: `bd_toc($html)` (Task 1).

- [ ] **Step 1: Tạo `template-parts/author-box.php`**

```php
<?php
defined('ABSPATH') || exit;
$bd_author_id = (int) get_the_author_meta('ID');
$bd_name      = get_the_author();
$bd_bio       = get_the_author_meta('description');
$bd_url       = get_author_posts_url($bd_author_id);
?>
<div class="mt-10 pt-6 border-t border-card flex items-start gap-4">
  <?php echo get_avatar($bd_author_id, 56, '', esc_attr($bd_name), ['class' => 'rounded-full shrink-0']); ?>
  <div class="min-w-0">
    <div class="font-hemi text-lg"><?php echo esc_html($bd_name); ?></div>
    <?php if ($bd_bio) : ?>
      <p class="text-sm text-secondary mt-1 leading-relaxed"><?php echo esc_html($bd_bio); ?></p>
    <?php endif; ?>
    <a href="<?php echo esc_url($bd_url); ?>" class="inline-block mt-2 text-sm text-brand hover:underline">Xem tất cả bài của tác giả →</a>
  </div>
</div>
```

- [ ] **Step 2: Tạo `template-parts/related-posts.php`**

```php
<?php
defined('ABSPATH') || exit;
$bd_id   = get_the_ID();
$bd_cats = wp_get_post_categories($bd_id);
if (!$bd_cats) return;
$bd_rel = new WP_Query([
    'category__in'        => $bd_cats,
    'post__not_in'        => [$bd_id],
    'posts_per_page'      => 3,
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
]);
if (!$bd_rel->post_count) return;
?>
<section class="max-w-4xl mx-auto mt-12">
  <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4 mb-6">Bài viết liên quan</h2>
  <div class="grid sm:grid-cols-3 gap-6">
    <?php while ($bd_rel->have_posts()) : $bd_rel->the_post(); ?>
      <a href="<?php echo esc_url(get_permalink()); ?>" class="block group">
        <?php if (has_post_thumbnail()) : ?>
          <div class="rounded-2xl overflow-hidden border border-card mb-3 aspect-video">
            <?php the_post_thumbnail('bd_hero', ['class' => 'w-full h-full object-cover transition-transform group-hover:scale-105', 'alt' => the_title_attribute(['echo' => false])]); ?>
          </div>
        <?php endif; ?>
        <h3 class="font-oswald text-base font-bold leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php the_title(); ?></h3>
        <time class="text-xs text-secondary mt-1 block"><?php echo esc_html(get_the_date('d/m/Y')); ?></time>
      </a>
    <?php endwhile; wp_reset_postdata(); ?>
  </div>
</section>
```

- [ ] **Step 3: Sửa `single.php` — ngày cập nhật (header)**

Thay (khối `time` ngày đăng + đóng div meta):
```php
          <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('d/m/Y')); ?></time>
        </div>
```
bằng:
```php
          <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('d/m/Y')); ?></time>
          <?php if (get_the_modified_time('U') > get_the_date('U') + HOUR_IN_SECONDS) : ?>
            <time datetime="<?php echo esc_attr(get_the_modified_date('c')); ?>" class="text-xs">Cập nhật: <?php echo esc_html(get_the_modified_date('d/m/Y')); ?></time>
          <?php endif; ?>
        </div>
```

- [ ] **Step 4: Sửa `single.php` — TOC + nội dung**

Thay:
```php
        <div class="prose-bd">
          <?php the_content(); ?>
        </div>
```
bằng:
```php
        <?php $bd_c = bd_toc(apply_filters('the_content', get_the_content())); ?>
        <?php if (count($bd_c['items']) >= 3) : ?>
          <nav class="mb-8 p-5 rounded-2xl border border-card bg-control" aria-label="Mục lục">
            <div class="font-hemi text-sm uppercase text-secondary mb-3">Mục lục</div>
            <ol class="space-y-2 list-decimal list-inside">
              <?php foreach ($bd_c['items'] as $bd_item) : ?>
                <li><a href="#<?php echo esc_attr($bd_item['id']); ?>" class="text-sm text-secondary hover:text-brand"><?php echo esc_html($bd_item['text']); ?></a></li>
              <?php endforeach; ?>
            </ol>
          </nav>
        <?php endif; ?>
        <div class="prose-bd">
          <?php echo $bd_c['content']; ?>
        </div>
```

- [ ] **Step 5: Sửa `single.php` — author box + bài liên quan**

Thay (cuối bài: đóng source block → đóng card → đóng article → endwhile):
```php
        <?php endif; ?>
      </div>
    </article>
  <?php endwhile; ?>
```
bằng:
```php
        <?php endif; ?>

        <?php get_template_part('template-parts/author-box'); ?>
      </div>
    </article>

    <?php get_template_part('template-parts/related-posts'); ?>
  <?php endwhile; ?>
```

- [ ] **Step 6: Đặt thông tin user `bot` (wp-cli)**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
./wp/bin/wp user update bot --display_name="Ban Biên Tập Bongda247" --user_nicename="ban-bien-tap" --description="Nội dung do đội ngũ Bongda247 biên tập với hỗ trợ AI: thu thập nguồn, tóm tắt và viết lại tiếng Việt, kiểm duyệt trước khi đăng."
```
Expected: `Success: Updated user 1.` (hoặc user id của bot). Kiểm: `curl -s -o /dev/null -w "%{http_code}\n" http://bongda247.local/author/ban-bien-tap/` → 200.

- [ ] **Step 7: Build CSS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247 && npm run build:css`
Expected: `Done in ...ms`.

- [ ] **Step 8: Verify — seed 1 bài có 3 H2**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
CAT=$(./wp/bin/wp term list category --slug=ngoai-hang-anh --field=term_id)
CONTENT='<h2>Bối cảnh trận đấu</h2><p>Nội dung một.</p><h2>Phong độ hai đội</h2><p>Nội dung hai.</p><h2>Nhận định &amp; Dự đoán</h2><p>Nội dung ba.</p>'
PID=$(./wp/bin/wp post create --post_type=post --post_status=publish --post_author=$(./wp/bin/wp user get bot --field=ID) --post_title="Test E-E-A-T single" --post_content="$CONTENT" --post_category="$CAT" --porcelain)
URL=$(./wp/bin/wp post get "$PID" --field=url)
curl -s "$URL" -o /tmp/single.html -w "HTTP %{http_code}\n"
echo "TOC (Mục lục): $(grep -c 'Mục lục' /tmp/single.html) | anchor muc-1: $(grep -c 'id=.muc-1' /tmp/single.html) | số mục (dòng href #muc-): $(grep -c 'href=.#muc-' /tmp/single.html)"
echo "author box: $(grep -c 'Ban Biên Tập Bongda247' /tmp/single.html) | link tác giả: $(grep -c 'author/ban-bien-tap' /tmp/single.html)"
echo "lỗi PHP: $(grep -ci 'fatal error\|warning:\|notice:' /tmp/single.html)"
# Dọn
./wp/bin/wp post delete "$PID" --force
```
Expected: HTTP 200; Mục lục=1; id muc-1 có; 3 anchor `#muc-`; author box "Ban Biên Tập Bongda247"=1 (≥1); link `author/ban-bien-tap`≥1; lỗi PHP=0. Nếu lệch → điều tra template, đừng sửa kỳ vọng.

- [ ] **Step 9: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/template-parts/author-box.php wp/themes/bongda247/template-parts/related-posts.php wp/themes/bongda247/single.php wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): E-E-A-T single — author box, ngày cập nhật, bài liên quan, TOC"
```

---

## Sau khi xong 2 task

- Cập nhật CLAUDE.md (single.php nêu author box/TOC/related; `inc/toc.php`; template-parts mới).
- Ledger `.superpowers/sdd/single-eeat/progress.md`.
- Finishing gate (bot `npm test` 9/9; curl 1 bài 200) → merge + push.
- Còn lại đợt SEO: RankMath (breadcrumb schema + meta), SportsEvent schema, bot tag `nhan-dinh`.
