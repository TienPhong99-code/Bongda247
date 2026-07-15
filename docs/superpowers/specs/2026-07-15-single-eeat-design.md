# SEO-SP2: E-E-A-T cho bài viết đơn (`single.php`) — Design

> Tăng tín hiệu E-E-A-T + khả năng AI-citation cho trang bài viết `bongda247`: **author box** (tác giả + bio + trang tác giả), **ngày cập nhật**, **bài viết liên quan**, **mục lục (TOC)**. Bỏ breadcrumb schema (RankMath lo).

## Mục tiêu

- Trả lời "Ai viết nội dung này?" (author box + trang tác giả) — Google QRG coi là tín hiệu Trustworthiness cốt lõi cho tin tức.
- Tín hiệu freshness (ngày cập nhật) + giữ chân/topical authority (bài liên quan) + AI Overview dễ trích từng mục (TOC).

## Bối cảnh & ràng buộc

- `single.php` hiện có: breadcrumb UI đơn giản (Trang chủ / Category — GIỮ NGUYÊN), header (h1 + badge category + ngày đăng `time`), featured image, nội dung `.prose-bd` `the_content()`, tags, source attribution (`source_url`/`source_credit`). **Chưa có** author box, ngày cập nhật, bài liên quan, TOC.
- Bài đăng dưới user `bot` (WP_USER=bot, application password). User này chưa đặt display name/bio đẹp.
- Theme: `.container`, `bg-card`/`border-card`, `text-secondary`/`text-brand`, `font-oswald`/`font-hemi`, ảnh size `bd_hero`, card style (tham khảo `category-column.php`/`transfer-list.php`).
- Helpers có: `bd_category_posts($slug,$n)` (query.php). `get_the_category`, `get_avatar`, `get_the_author_meta`, `get_author_posts_url` (WP core).

## Quyết định thiết kế (chốt qua brainstorming)

| # | Quyết định | Chọn |
|---|-----------|------|
| Author | Cách thể hiện | **User thật** "Ban Biên Tập Bongda247" + bio + link trang tác giả `/author/ban-bien-tap/` |
| TOC | Khi nào hiện | Chỉ khi **≥ 3 mục H2**; id gắn tự động; link anchor |
| Ngày cập nhật | Điều kiện | Chỉ khi `modified > published + 1 giờ` |
| Bài liên quan | Nguồn | 3 bài **cùng category**, loại bài hiện tại, mới nhất; rỗng → ẩn |

## Kiến trúc / thành phần

### 1. `inc/toc.php` (MỚI) — hàm sinh mục lục
```php
/**
 * Sinh TOC từ H2 trong HTML nội dung.
 * Trả ['items' => [ ['id'=>'muc-1','text'=>'...'], ... ], 'content' => $html_đã_gắn_id].
 * Gắn id="muc-N" (N tuần tự) vào mỗi <h2> chưa có id. text = strip_tags nội dung h2.
 */
function bd_toc($html) { ... }
```
- Dùng `preg_replace_callback('/<h2\b([^>]*)>(.*?)<\/h2>/is', ...)`: mỗi H2 → tăng bộ đếm → id `muc-N` (bỏ qua nếu H2 đã có `id=`) → chèn `id` vào thuộc tính mở thẻ → thu `['id','text'=>trim(strip_tags($inner))]`. Trả content đã sửa + mảng items.
- Require trong `functions.php` (cạnh query.php / football-data.php).

### 2. `template-parts/author-box.php` (MỚI)
- Đọc author của post hiện tại: `get_the_author()`, `get_the_author_meta('description')`, `get_avatar($author_id, 64)`, `get_author_posts_url($author_id)`.
- Render khối: avatar + tên (font-hemi) + bio (nếu có) + link "Xem tất cả bài của tác giả →". Escape đầu ra.

### 3. `template-parts/related-posts.php` (MỚI)
- `WP_Query(['category__in'=>wp_get_post_categories($id), 'post__not_in'=>[$id], 'posts_per_page'=>3, 'ignore_sticky_posts'=>true, 'no_found_rows'=>true])`.
- `post_count===0` → return (ẩn). Ngược lại: heading "Bài viết liên quan" + lưới 3 card (ảnh `bd_hero` + tiêu đề + ngày, link bài). `wp_reset_postdata()`.

### 4. `single.php` (SỬA)
- **Ngày cập nhật:** trong header meta, sau `time` ngày đăng, thêm — chỉ khi `get_the_modified_time('U') > get_the_date('U') + HOUR_IN_SECONDS`:
  `<time datetime="{modified c}">Cập nhật: {modified d/m/Y}</time>`.
- **TOC + nội dung:** thay `the_content()` bằng:
  ```php
  $bd_c = bd_toc(apply_filters('the_content', get_the_content()));
  // nếu count($bd_c['items']) >= 3 → render hộp TOC (danh sách <a href="#id">text</a>)
  echo $bd_c['content'];
  ```
- **Author box:** `get_template_part('template-parts/author-box')` sau khối tags/source (trong hộp `.bg-card`).
- **Bài liên quan:** `get_template_part('template-parts/related-posts')` sau `</article>`, trước `endwhile`.

### 5. Set user `bot` (bước triển khai, wp-cli)
```bash
./wp/bin/wp user update bot --display_name="Ban Biên Tập Bongda247" --user_nicename="ban-bien-tap" \
  --description="Nội dung do đội ngũ Bongda247 biên tập với hỗ trợ AI: thu thập nguồn, tóm tắt và viết lại tiếng Việt, kiểm duyệt trước khi đăng."
```
- Trang tác giả `/author/ban-bien-tap/` dùng `archive.php` sẵn có.

## Data flow

`single.php` (trong The Loop) → header (ngày đăng + ngày cập nhật có điều kiện) → `bd_toc()` xử lý content → render TOC (nếu ≥3) + content-đã-id → author-box (author của post) → tags/source (giữ) → related-posts (query cùng category). Không gọi API ngoài.

## Error handling

- `bd_toc`: 0/1/2 H2 → `items` < 3 → single.php không render hộp TOC (vẫn echo content). H2 rỗng text → vẫn gắn id, text rỗng bỏ khỏi items. H2 đã có `id` → giữ nguyên, không trùng.
- author-box: bio rỗng → chỉ hiện tên + link. Avatar tắt ở site → `get_avatar` trả rỗng, layout không vỡ.
- related-posts: <3 hoặc 0 bài cùng category → hiện những gì có / ẩn khi 0.
- Escape: `esc_url` (link author/bài/anchor id qua esc_attr), `esc_html` (tên/tiêu đề/ngày/text TOC). `echo $bd_c['content']` là HTML đã lọc qua `the_content` filter (chuẩn WP).

## Kiểm thử (thủ công + wp-cli)

- [ ] **bd_toc()** (test wp-cli): input HTML 3 H2 → `items` có 3 phần tử id `muc-1..3` + text đúng; content chứa `id="muc-1"`; input 1 H2 → items=1 (single.php sẽ ẩn TOC); H2 có sẵn id → không đè.
- [ ] Set user bot qua wp-cli → `/author/ban-bien-tap/` HTTP 200.
- [ ] Seed 1 post (content 3 `<h2>`, có category, author=bot) → mở bài: TOC hiện 3 mục click nhảy đúng; author box hiện "Ban Biên Tập Bongda247" + bio + link; ngày cập nhật ẩn (bài mới); related ẩn/hiện tùy số bài category. Post content 0 H2 → không TOC. Playwright chụp. Xoá seed.
- [ ] `curl` bài bất kỳ HTTP 200, 0 lỗi PHP.

## Tiêu chí thành công

- [ ] Bài viết có: author box (tên+bio+link tác giả), ngày cập nhật (khi có), bài liên quan (khi đủ), TOC (khi ≥3 H2).
- [ ] `/author/ban-bien-tap/` liệt kê bài tác giả (HTTP 200).
- [ ] TOC anchor nhảy đúng mục; không vỡ khi <3 H2.
- [ ] Escape đầy đủ; không thêm JS (TOC anchor thuần HTML); không API ngoài.

## Ngoài phạm vi

- Breadcrumb schema (RankMath); author.php template riêng; TOC cho H3; sửa bot; noindex author archive (config RankMath).
