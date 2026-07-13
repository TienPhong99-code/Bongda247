# WordPress Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thay frontend Astro + CMS Sanity bằng WordPress classic theme, giữ nguyên bot Telegram Node trên Railway (chỉ đổi lớp ghi dữ liệu sang WP REST API).

**Architecture:** Theme PHP `bongda247` + mu-plugin đăng ký CPT `match_insight`. Bot Node giữ nguyên Gemini/RSS/Puppeteer/Telegraf, thay `@sanity/client` bằng adapter `web/lib/wp.js` gọi WP REST API với Application Password. Không migrate dữ liệu cũ.

**Tech Stack:** WordPress 7.0.1, PHP 8.2.29, MySQL 8.4, Tailwind CSS v4 (CLI), Swiper 12 (vendored bundle), Node 20, axios.

## Global Constraints

- **Spec:** `docs/superpowers/specs/2026-07-13-wordpress-migration-design.md` — mọi quyết định đã chốt ở đó.
- **Site local:** `http://bongda247.local` (dùng **http**, KHÔNG dùng https — cert self-signed sẽ làm axios của bot văng `UNABLE_TO_VERIFY_LEAF_SIGNATURE`).
- **WP path:** `/Users/hotienphong/Local Sites/bongda247/app/public`
- **Repo path:** `/Users/hotienphong/Desktop/Personal/Bongda247`
- **Design tokens (giữ nguyên, không đổi):** brand `#0232ff`, accent `#dc2626`, bg light `#f3f3f3` / dark `#0e1217`, card light `#f5f8fc` / dark `#1c1f26`. Font: Inter (body), Oswald (heading), SVN-Hemi Head (branding).
- **Ngôn ngữ nội dung:** tiếng Việt. Comment code tiếng Việt, khớp style hiện có của `bot-press.js`.
- **KHÔNG build:** LiveScoresTicker, LeagueTabs, NewsSection, `/lich-thi-dau`, `/bang-xep-hang` (spec §2 — đã chốt bỏ).
- **KHÔNG đụng vào:** `web/matchPreviewImage.js`, prompt Gemini, RSS pipeline, luồng Telegram, lịch cron. Chỉ đổi lớp ghi dữ liệu.
- **8 category slug bắt buộc:** `ngoai-hang-anh`, `champions-league`, `la-liga`, `bundesliga`, `serie-a`, `ligue-1`, `chuyen-nhuong`, `ngoai-san-co`
- **Commit sau mỗi task.** Không gộp task vào một commit.

---

## File Structure

**Tạo mới:**

| File | Trách nhiệm |
|---|---|
| `wp/bin/wp` | Wrapper bash chạy wp-cli với PHP + MySQL socket của Local |
| `wp/mu-plugins/bongda247-core.php` | Đăng ký CPT `match_insight` + post meta cho REST |
| `wp/themes/bongda247/style.css` | Theme header (bắt buộc của WP) |
| `wp/themes/bongda247/functions.php` | Theme supports, enqueue assets, nav menu, image sizes |
| `wp/themes/bongda247/inc/query.php` | Hàm query dùng chung: `bd_hot_posts()`, `bd_sidebar_posts()`, `bd_insights()`, `bd_insight_is_upcoming()` |
| `wp/themes/bongda247/header.php` | Port `Header.astro` |
| `wp/themes/bongda247/footer.php` | Footer |
| `wp/themes/bongda247/front-page.php` | Trang chủ |
| `wp/themes/bongda247/single.php` | Trang bài viết — port `[category]/[slug].astro` |
| `wp/themes/bongda247/archive.php` | Trang category (MỚI) |
| `wp/themes/bongda247/index.php` | Fallback bắt buộc của WP |
| `wp/themes/bongda247/404.php` | Trang 404 |
| `wp/themes/bongda247/template-parts/theme-toggle.php` | Nút dark/light |
| `wp/themes/bongda247/template-parts/hot-news-slider.php` | Port `HotNewsSlider.astro` |
| `wp/themes/bongda247/template-parts/sidebar-slider.php` | Port `SidebarSlider.astro` |
| `wp/themes/bongda247/template-parts/match-insights.php` | Port `MatchInsights.astro` |
| `wp/themes/bongda247/src/main.css` | Nguồn Tailwind v4 + design tokens |
| `wp/themes/bongda247/src/main.js` | Khởi tạo 3 Swiper + logic theme toggle |
| `wp/themes/bongda247/package.json` | Script build Tailwind + vendor Swiper |
| `web/lib/wp.js` | **Adapter** WP REST API — mọi lời gọi HTTP tới WP nằm ở đây |
| `web/scripts/seed-wp.mjs` | Seed category + bài/insight demo để dựng theme |
| `web/test/wp.test.mjs` | Integration test cho `wp.js`, chạy thật với Local |

**Sửa:**

| File | Thay đổi |
|---|---|
| `web/bot-press.js` | Bỏ `@sanity/client`, dùng `wp.js`. 15 điểm chạm — liệt kê chi tiết ở Task 7. |
| `web/package.json` | Thêm script `test`, `seed`. Gỡ dependency Astro/React/Sanity ở Task 8. |
| `CLAUDE.md` | Cập nhật tech stack, env vars, cấu trúc |

**Xoá (Task 8, sau khi verify xong):** `web/src/`, `web/astro.config.mjs`, `web/tailwind.config.mjs`, `web/postcss.config.mjs`, `web/tsconfig.json`, `web/create-categories.js`

---

## Task 1: Nền tảng WordPress — wp-cli wrapper, CPT, category, user bot

**Files:**
- Create: `wp/bin/wp`
- Create: `wp/mu-plugins/bongda247-core.php`
- Create: `.gitignore` bổ sung (nếu cần)

**Interfaces:**
- Consumes: không có (task đầu)
- Produces:
  - Lệnh `./wp/bin/wp <wp-cli args>` chạy được từ repo root
  - CPT `match_insight`, `rest_base: match_insight`
  - Post meta trên `match_insight`: `home_team`, `away_team`, `match_time`, `match_date`, `prediction` (string), `hot` (integer 0/1), `insights` (array of string)
  - Post meta trên `post`: `source_url`, `source_credit` (string)
  - 8 category đúng slug
  - User `bot` (role `editor`) + Application Password → ghi vào `web/.env`

---

- [ ] **Step 1: Tạo wrapper wp-cli**

Local by Flywheel không đưa `wp` ra PATH. Wrapper này tự tìm PHP binary, `wp-cli.phar`, và MySQL socket **đúng của site bongda247** (đọc site ID từ `sites.json` — máy này còn 2 site khác đang chạy, `find` bừa sẽ lấy nhầm socket).

Tạo `wp/bin/wp`:

```bash
#!/usr/bin/env bash
# Chạy wp-cli với PHP + MySQL socket của Local by Flywheel.
# Dùng: ./wp/bin/wp plugin list
set -euo pipefail

SITE_NAME="${LOCAL_SITE_NAME:-bongda247}"
SITE_PATH="${WP_SITE_PATH:-$HOME/Local Sites/bongda247/app/public}"
SITE_URL="${WP_URL:-http://bongda247.local}"

LOCAL_SUPPORT="$HOME/Library/Application Support/Local"
WP_PHAR="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar"

# PHP binary mới nhất do Local cài
PHP_BIN=$(find "$LOCAL_SUPPORT/lightning-services" -type f -name php -perm -u+x 2>/dev/null | sort | tail -1)

# Site ID → socket. Đọc từ sites.json để không lấy nhầm socket của site khác.
SITE_ID=$(node -e "
  const fs = require('fs');
  const sites = JSON.parse(fs.readFileSync('$LOCAL_SUPPORT/sites.json', 'utf8'));
  const id = Object.keys(sites).find((k) => sites[k].name === '$SITE_NAME');
  process.stdout.write(id || '');
")
SOCK="$LOCAL_SUPPORT/run/$SITE_ID/mysql/mysqld.sock"

[ -x "$PHP_BIN" ] || { echo "❌ Không tìm thấy PHP của Local"; exit 1; }
[ -f "$WP_PHAR" ] || { echo "❌ Không tìm thấy wp-cli.phar"; exit 1; }
[ -n "$SITE_ID" ] || { echo "❌ Không tìm thấy site '$SITE_NAME' trong sites.json"; exit 1; }
[ -S "$SOCK" ]    || { echo "❌ Không thấy MySQL socket — site '$SITE_NAME' đã start trong Local chưa?"; exit 1; }

exec "$PHP_BIN" \
  -d mysqli.default_socket="$SOCK" \
  -d pdo_mysql.default_socket="$SOCK" \
  "$WP_PHAR" --path="$SITE_PATH" --url="$SITE_URL" "$@"
```

```bash
chmod +x wp/bin/wp
```

- [ ] **Step 2: Chạy wrapper để xác nhận kết nối DB**

Run: `./wp/bin/wp core version && ./wp/bin/wp theme list --fields=name,status`
Expected:
```
7.0.1
name              status
twentytwentyfive  active
...
```
Nếu ra `Error establishing a database connection` → site chưa start trong Local, bấm Start site rồi chạy lại.

- [ ] **Step 3: Viết mu-plugin (CPT + meta)**

Tạo `wp/mu-plugins/bongda247-core.php`:

```php
<?php
/**
 * Plugin Name: Bongda247 Core
 * Description: CPT match_insight + post meta để bot đẩy dữ liệu qua REST API.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Quyền ghi meta qua REST. Bot chạy bằng user role editor.
 */
function bd_meta_auth() {
    return current_user_can('edit_posts');
}

add_action('init', 'bd_register_match_insight');
function bd_register_match_insight() {
    register_post_type('match_insight', [
        'labels' => [
            'name'          => 'Nhận định trận',
            'singular_name' => 'Nhận định trận',
            'menu_name'     => 'Nhận định trận',
            'add_new_item'  => 'Thêm nhận định trận',
            'edit_item'     => 'Sửa nhận định trận',
        ],
        'public'       => true,
        'has_archive'  => false,
        'menu_icon'    => 'dashicons-shield-alt',
        'supports'     => ['title', 'custom-fields'],
        'show_in_rest' => true,
        'rest_base'    => 'match_insight',
        'rewrite'      => ['slug' => 'nhan-dinh-tran'],
    ]);
}

add_action('init', 'bd_register_meta');
function bd_register_meta() {
    // Meta chuỗi của match_insight
    foreach (['home_team', 'away_team', 'match_time', 'match_date', 'prediction'] as $key) {
        register_post_meta('match_insight', $key, [
            'type'          => 'string',
            'single'        => true,
            'default'       => '',
            'show_in_rest'  => true,
            'auth_callback' => 'bd_meta_auth',
        ]);
    }

    // hot: dùng integer 0/1 (KHÔNG dùng boolean) để WP_Query orderby meta_value_num
    // sắp xếp tin cậy — boolean false được WP lưu thành chuỗi rỗng, sort không ổn định.
    register_post_meta('match_insight', 'hot', [
        'type'          => 'integer',
        'single'        => true,
        'default'       => 0,
        'show_in_rest'  => true,
        'auth_callback' => 'bd_meta_auth',
    ]);

    // insights: mảng chuỗi. REST CHỈ ghi được nếu khai đủ schema.items.type —
    // thiếu schema thì WP im lặng bỏ qua field này khi POST.
    register_post_meta('match_insight', 'insights', [
        'type'          => 'array',
        'single'        => true,
        'default'       => [],
        'show_in_rest'  => [
            'schema' => [
                'type'  => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
        'auth_callback' => 'bd_meta_auth',
    ]);

    // Nguồn bài RSS trên post thường
    foreach (['source_url', 'source_credit'] as $key) {
        register_post_meta('post', $key, [
            'type'          => 'string',
            'single'        => true,
            'default'       => '',
            'show_in_rest'  => true,
            'auth_callback' => 'bd_meta_auth',
        ]);
    }
}
```

- [ ] **Step 4: Symlink mu-plugins vào WP, flush rewrite**

```bash
SITE="$HOME/Local Sites/bongda247/app/public"
ln -sfn "$(pwd)/wp/mu-plugins" "$SITE/wp-content/mu-plugins"
./wp/bin/wp rewrite structure '/%category%/%postname%/' --hard
./wp/bin/wp rewrite flush --hard
```

- [ ] **Step 5: Kiểm chứng CPT + meta đã đăng ký**

Run:
```bash
./wp/bin/wp post-type get match_insight --field=name
curl -s -o /dev/null -w "match_insight REST: %{http_code}\n" http://bongda247.local/wp-json/wp/v2/match_insight
```
Expected:
```
match_insight
match_insight REST: 200
```
Nếu REST trả 404 → symlink mu-plugins sai, kiểm tra `ls -la "$SITE/wp-content/mu-plugins"`.

- [ ] **Step 6: Tạo 8 category**

```bash
./wp/bin/wp term create category "Ngoại hạng Anh"   --slug=ngoai-hang-anh
./wp/bin/wp term create category "Champions League" --slug=champions-league
./wp/bin/wp term create category "La Liga"          --slug=la-liga
./wp/bin/wp term create category "Bundesliga"       --slug=bundesliga
./wp/bin/wp term create category "Serie A"          --slug=serie-a
./wp/bin/wp term create category "Ligue 1"          --slug=ligue-1
./wp/bin/wp term create category "Chuyển nhượng"    --slug=chuyen-nhuong
./wp/bin/wp term create category "Ngoài sân cỏ"     --slug=ngoai-san-co
```

Run: `./wp/bin/wp term list category --fields=slug --format=csv | tail -n +2 | sort`
Expected: 8 slug trên + `uncategorized` (WP tạo sẵn — để nguyên, vô hại).

- [ ] **Step 7: Tạo user `bot` + Application Password**

```bash
./wp/bin/wp user create bot bot@bongda247.local --role=editor --user_pass="$(openssl rand -base64 24)"
./wp/bin/wp user application-password create bot bot-press --porcelain
```
Lệnh cuối in ra password dạng `abcd EFGH ijkl MNOP qrst UVWX`. **Copy nguyên chuỗi kể cả khoảng trắng.**

Thêm vào `web/.env` (tạo file nếu chưa có — file này đã trong `.gitignore`):
```
WP_URL=http://bongda247.local
WP_USER=bot
WP_APP_PASSWORD=<dán chuỗi vừa in ra>
```

- [ ] **Step 8: Kiểm chứng Application Password ghi được**

Run:
```bash
cd web && node -e "
import('dotenv/config').then(async () => {
  const r = await fetch(process.env.WP_URL + '/wp-json/wp/v2/users/me', {
    headers: { Authorization: 'Basic ' + Buffer.from(process.env.WP_USER + ':' + process.env.WP_APP_PASSWORD).toString('base64') },
  });
  const u = await r.json();
  console.log(r.status, u.slug, JSON.stringify(u.capabilities?.publish_posts));
});
"
```
Expected: `200 bot true`
Nếu ra `401` → password dán thiếu khoảng trắng hoặc `WP_URL` dùng `https`.

- [ ] **Step 9: Commit**

```bash
git add wp/bin/wp wp/mu-plugins/bongda247-core.php
git commit -m "feat(wp): wp-cli wrapper + mu-plugin CPT match_insight"
```

---

## Task 2: Adapter `web/lib/wp.js` (TDD)

**Files:**
- Create: `web/lib/wp.js`
- Create: `web/test/wp.test.mjs`
- Modify: `web/package.json` (thêm script `test`)

**Interfaces:**
- Consumes: CPT + meta + user `bot` từ Task 1; env `WP_URL`, `WP_USER`, `WP_APP_PASSWORD`
- Produces — chữ ký chính xác các task sau dựa vào:
  ```js
  uploadMedia(buffer, filename?)                → Promise<{ id: number, url: string }>
  createPost({ title, html, excerpt?, categoryId?, tags?, featuredMedia?,
               sourceUrl?, sourceCredit? })     → Promise<{ id: number, link: string }>
  createInsight({ homeTeam, awayTeam, matchTime, matchDate?, hot?,
                  insights?, prediction? })      → Promise<{ id: number }>
  listPosts(n?)      → Promise<Array<{ id, title, date, categoryIds: number[] }>>
  listInsights(n?)   → Promise<Array<{ id, homeTeam, awayTeam, matchTime, matchDate, hot: boolean }>>
  deleteById(id, type)  // type: "posts" | "match_insight"  → Promise<void>
  fetchCategories()  → Promise<Record<slug, { id: number, title: string }>>
  ensureCategory(slug, name) → Promise<number>   // dùng cho seed
  ```

---

- [ ] **Step 1: Viết test trước (fail)**

Tạo `web/test/wp.test.mjs`:

```js
// Integration test — chạy thật với WordPress ở WP_URL (Local phải đang chạy).
import "dotenv/config";
import { test, after } from "node:test";
import assert from "node:assert/strict";
import * as wp from "../lib/wp.js";

// PNG 1x1 trong suốt — đủ để WP nhận là ảnh hợp lệ
const PNG_1X1 = Buffer.from(
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=",
  "base64"
);

const created = { posts: [], match_insight: [] };

after(async () => {
  for (const id of created.posts) await wp.deleteById(id, "posts");
  for (const id of created.match_insight) await wp.deleteById(id, "match_insight");
});

test("fetchCategories trả map slug → {id, title}", async () => {
  const cats = await wp.fetchCategories();
  assert.ok(cats["ngoai-hang-anh"], "thiếu category ngoai-hang-anh");
  assert.equal(typeof cats["ngoai-hang-anh"].id, "number");
  assert.equal(cats["ngoai-hang-anh"].title, "Ngoại hạng Anh");
});

test("uploadMedia trả {id, url}", async () => {
  const media = await wp.uploadMedia(PNG_1X1, "test-pixel.png");
  assert.equal(typeof media.id, "number");
  assert.match(media.url, /^http.*\.png$/);
});

test("createPost gắn category, featured image, tag và meta nguồn", async () => {
  const cats = await wp.fetchCategories();
  const media = await wp.uploadMedia(PNG_1X1, "test-featured.png");

  const post = await wp.createPost({
    title: "Bài test tự động",
    html: "<h2>Mục một</h2>\n<p>Nội dung một.</p>",
    excerpt: "Mô tả ngắn",
    categoryId: cats["ngoai-hang-anh"].id,
    tags: ["#Arsenal", "Chelsea"],
    featuredMedia: media.id,
    sourceUrl: "https://example.com/bai-goc",
    sourceCredit: "Example",
  });
  created.posts.push(post.id);

  assert.equal(typeof post.id, "number");
  assert.match(post.link, /^http/);

  const res = await fetch(`${process.env.WP_URL}/wp-json/wp/v2/posts/${post.id}`);
  const body = await res.json();
  assert.equal(body.title.rendered, "Bài test tự động");
  assert.deepEqual(body.categories, [cats["ngoai-hang-anh"].id]);
  assert.equal(body.featured_media, media.id);
  assert.equal(body.tags.length, 2);
  assert.equal(body.meta.source_url, "https://example.com/bai-goc");
  assert.ok(body.content.rendered.includes("Mục một"));
});

test("createInsight ghi được meta mảng insights", async () => {
  const insight = await wp.createInsight({
    homeTeam: "Arsenal",
    awayTeam: "Chelsea",
    matchTime: "21:00 - 23/03",
    matchDate: "2026-03-23T14:00:00Z",
    hot: true,
    insights: ["Arsenal thắng 8/10 sân nhà", "Chelsea ghi bàn 6 trận liền", "Đối đầu nghiêng về Arsenal"],
    prediction: "Arsenal thắng 2-1",
  });
  created.match_insight.push(insight.id);

  const res = await fetch(`${process.env.WP_URL}/wp-json/wp/v2/match_insight/${insight.id}`);
  const body = await res.json();
  assert.equal(body.meta.home_team, "Arsenal");
  assert.equal(body.meta.hot, 1);
  assert.equal(body.meta.insights.length, 3);
  assert.equal(body.meta.insights[0], "Arsenal thắng 8/10 sân nhà");
  assert.equal(body.meta.prediction, "Arsenal thắng 2-1");
});

test("listInsights map meta về đúng shape bot cần", async () => {
  const insight = await wp.createInsight({
    homeTeam: "Liverpool",
    awayTeam: "Everton",
    matchTime: "23:30 - 24/03",
    hot: false,
    insights: ["Một ghi chú"],
    prediction: "Hòa 1-1",
  });
  created.match_insight.push(insight.id);

  const list = await wp.listInsights(10);
  const found = list.find((i) => i.id === insight.id);
  assert.ok(found, "không thấy insight vừa tạo trong listInsights");
  assert.equal(found.homeTeam, "Liverpool");
  assert.equal(found.awayTeam, "Everton");
  assert.equal(found.matchTime, "23:30 - 24/03");
  assert.equal(found.hot, false);
});

test("listPosts trả id, title đã decode entity, categoryIds", async () => {
  const cats = await wp.fetchCategories();
  const post = await wp.createPost({
    title: "Tin tức & phân tích",
    html: "<p>Nội dung</p>",
    categoryId: cats["chuyen-nhuong"].id,
  });
  created.posts.push(post.id);

  const list = await wp.listPosts(8);
  const found = list.find((p) => p.id === post.id);
  assert.ok(found, "không thấy bài vừa tạo trong listPosts");
  assert.equal(found.title, "Tin tức & phân tích"); // KHÔNG được là "&amp;"
  assert.deepEqual(found.categoryIds, [cats["chuyen-nhuong"].id]);
});
```

- [ ] **Step 2: Chạy test để xác nhận FAIL**

Thêm vào `web/package.json` mục `scripts`:
```json
"test": "node --test test/"
```

Run: `cd web && npm test`
Expected: FAIL — `Cannot find module '../lib/wp.js'`

- [ ] **Step 3: Viết `web/lib/wp.js`**

```js
// web/lib/wp.js — Adapter ghi/đọc dữ liệu WordPress qua REST API.
// Mọi lời gọi HTTP tới WP nằm ở file này. bot-press.js không gọi thẳng axios tới WP.
import axios from "axios";

const WP_URL = (process.env.WP_URL || "").replace(/\/$/, "");
const WP_USER = process.env.WP_USER;
const WP_APP_PASSWORD = process.env.WP_APP_PASSWORD;

if (!WP_URL || !WP_USER || !WP_APP_PASSWORD) {
  console.warn("⚠️ Thiếu WP_URL / WP_USER / WP_APP_PASSWORD — bot sẽ không ghi được lên WordPress.");
}

const api = axios.create({
  baseURL: `${WP_URL}/wp-json/wp/v2`,
  auth: { username: WP_USER, password: WP_APP_PASSWORD },
  timeout: 30000,
});

// WP trả title/name dưới dạng HTML entity — giải mã để hiển thị trên Telegram.
function decodeEntities(str = "") {
  return str
    .replace(/&#(\d+);/g, (_, d) => String.fromCharCode(Number(d)))
    .replace(/&#x([0-9a-f]+);/gi, (_, h) => String.fromCharCode(parseInt(h, 16)))
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#039;|&apos;/g, "'")
    .replace(/&nbsp;/g, " ");
}

function mimeFromName(filename) {
  const ext = filename.split(".").pop().toLowerCase();
  return {
    jpg: "image/jpeg",
    jpeg: "image/jpeg",
    png: "image/png",
    webp: "image/webp",
    gif: "image/gif",
  }[ext] ?? "image/jpeg";
}

/**
 * Upload ảnh lên Media Library.
 * Dùng raw binary + Content-Disposition (WP hỗ trợ sẵn) → không cần multipart/form-data.
 */
export async function uploadMedia(buffer, filename = `bongda247-${Date.now()}.jpg`) {
  const res = await api.post("/media", buffer, {
    headers: {
      "Content-Disposition": `attachment; filename="${filename}"`,
      "Content-Type": mimeFromName(filename),
    },
    maxBodyLength: Infinity,
    maxContentLength: Infinity,
  });
  return { id: res.data.id, url: res.data.source_url };
}

/**
 * Đổi tên tag (chuỗi) → term ID. Tự tạo tag nếu chưa có.
 */
export async function resolveTags(names = []) {
  const ids = [];
  for (const raw of names) {
    const name = String(raw).replace(/^#/, "").trim();
    if (!name) continue;
    try {
      const found = await api.get("/tags", { params: { search: name, per_page: 1 } });
      if (found.data.length && decodeEntities(found.data[0].name).toLowerCase() === name.toLowerCase()) {
        ids.push(found.data[0].id);
        continue;
      }
      const created = await api.post("/tags", { name });
      ids.push(created.data.id);
    } catch (e) {
      // WP trả term_exists kèm term_id khi tag đã tồn tại nhưng search không khớp
      const existing = e.response?.data?.data?.term_id;
      if (existing) ids.push(existing);
      else console.warn(`⚠️ Bỏ qua tag "${name}": ${e.message}`);
    }
  }
  return ids;
}

export async function createPost({
  title,
  html,
  excerpt = "",
  categoryId = null,
  tags = [],
  featuredMedia = null,
  sourceUrl = "",
  sourceCredit = "",
}) {
  const tagIds = await resolveTags(tags);
  const res = await api.post("/posts", {
    title,
    content: html,
    excerpt,
    status: "publish",
    ...(categoryId && { categories: [categoryId] }),
    ...(tagIds.length && { tags: tagIds }),
    ...(featuredMedia && { featured_media: featuredMedia }),
    meta: { source_url: sourceUrl || "", source_credit: sourceCredit || "" },
  });
  return { id: res.data.id, link: res.data.link };
}

export async function createInsight({
  homeTeam,
  awayTeam,
  matchTime,
  matchDate = null,
  hot = false,
  insights = [],
  prediction = "",
}) {
  const res = await api.post("/match_insight", {
    title: `${homeTeam} vs ${awayTeam}`,
    status: "publish",
    meta: {
      home_team: homeTeam,
      away_team: awayTeam,
      match_time: matchTime,
      match_date: matchDate ?? "",
      hot: hot ? 1 : 0,
      insights,
      prediction,
    },
  });
  return { id: res.data.id };
}

export async function listPosts(n = 8) {
  const res = await api.get("/posts", {
    params: { per_page: n, orderby: "date", order: "desc", _fields: "id,title,date,categories" },
  });
  return res.data.map((p) => ({
    id: p.id,
    title: decodeEntities(p.title?.rendered ?? ""),
    date: p.date,
    categoryIds: p.categories ?? [],
  }));
}

export async function listInsights(n = 10) {
  const res = await api.get("/match_insight", {
    params: { per_page: n, orderby: "date", order: "desc", _fields: "id,meta" },
  });
  return res.data.map((i) => ({
    id: i.id,
    homeTeam: i.meta?.home_team ?? "",
    awayTeam: i.meta?.away_team ?? "",
    matchTime: i.meta?.match_time ?? "",
    matchDate: i.meta?.match_date ?? "",
    hot: Number(i.meta?.hot ?? 0) === 1,
  }));
}

/** type: "posts" | "match_insight" */
export async function deleteById(id, type = "posts") {
  await api.delete(`/${type}/${id}`, { params: { force: true } });
}

export async function fetchCategories() {
  const res = await api.get("/categories", {
    params: { per_page: 100, _fields: "id,slug,name" },
  });
  const map = {};
  res.data.forEach((c) => {
    map[c.slug] = { id: c.id, title: decodeEntities(c.name) };
  });
  return map;
}

/** Tạo category nếu chưa có, trả về id. Dùng cho script seed. */
export async function ensureCategory(slug, name) {
  const cats = await fetchCategories();
  if (cats[slug]) return cats[slug].id;
  const res = await api.post("/categories", { name, slug });
  return res.data.id;
}
```

- [ ] **Step 4: Chạy test để xác nhận PASS**

Run: `cd web && npm test`
Expected: `# pass 6` / `# fail 0`

Nếu test `createInsight` fail ở `body.meta.insights.length` → mu-plugin thiếu `schema.items.type`, quay lại Task 1 Step 3.

- [ ] **Step 5: Commit**

```bash
git add web/lib/wp.js web/test/wp.test.mjs web/package.json
git commit -m "feat(bot): adapter wp.js ghi dữ liệu qua WP REST API + integration test"
```

---

## Task 3: Seed dữ liệu demo

**Files:**
- Create: `web/scripts/seed-wp.mjs`
- Modify: `web/package.json` (thêm script `seed`)

**Interfaces:**
- Consumes: `web/lib/wp.js` (Task 2) — `uploadMedia`, `createPost`, `createInsight`, `fetchCategories`
- Produces: 6 bài viết + 5 insight có ảnh trên WP → Task 4–6 dựng theme trên dữ liệu thật, không phải placeholder

---

- [ ] **Step 1: Viết script seed**

Tạo `web/scripts/seed-wp.mjs`:

```js
// Seed dữ liệu demo lên WordPress để dựng/kiểm tra theme.
// Chạy: npm run seed
import "dotenv/config";
import axios from "axios";
import * as wp from "../lib/wp.js";

const POSTS = [
  { title: "Arsenal đè bẹp Chelsea 3-0 tại Emirates",        cat: "ngoai-hang-anh",  img: "https://picsum.photos/seed/arsenal/1200/675" },
  { title: "Real Madrid chốt xong tương lai của Vinicius",    cat: "la-liga",         img: "https://picsum.photos/seed/real/1200/675" },
  { title: "Bayern Munich thắng ngược Dortmund trong Der Klassiker", cat: "bundesliga", img: "https://picsum.photos/seed/bayern/1200/675" },
  { title: "Inter Milan dẫn đầu Serie A sau vòng 28",         cat: "serie-a",         img: "https://picsum.photos/seed/inter/1200/675" },
  { title: "PSG khủng hoảng lực lượng trước vòng knock-out",  cat: "ligue-1",         img: "https://picsum.photos/seed/psg/1200/675" },
  { title: "Man City nhắm tiền đạo trẻ trong kỳ chuyển nhượng hè", cat: "chuyen-nhuong", img: "https://picsum.photos/seed/mancity/1200/675" },
];

const INSIGHTS = [
  { homeTeam: "Arsenal",   awayTeam: "Chelsea",     matchTime: "21:00 - 20/07", hot: true,
    insights: ["Arsenal thắng 8/10 trận sân nhà gần đây", "Chelsea ghi bàn trong 6 trận liên tiếp", "Hai đội hòa 2 lần gần nhất"],
    prediction: "Arsenal thắng 2-1" },
  { homeTeam: "Liverpool", awayTeam: "Man City",    matchTime: "23:30 - 21/07", hot: true,
    insights: ["Liverpool bất bại 12 trận sân nhà", "Man City ghi trung bình 2.4 bàn/trận"],
    prediction: "Hòa 2-2" },
  { homeTeam: "Real Madrid", awayTeam: "Barcelona", matchTime: "02:00 - 22/07", hot: false,
    insights: ["El Clasico luôn có hơn 2.5 bàn trong 5 lần gần nhất", "Barca thắng 5 trận liên tiếp"],
    prediction: "Barcelona thắng 2-1" },
  { homeTeam: "Bayern",    awayTeam: "Dortmund",    matchTime: "20:30 - 23/07", hot: false,
    insights: ["Bayern thắng 4/5 lần đối đầu gần nhất", "Dortmund thủng lưới ở 7 trận liền"],
    prediction: "Bayern thắng 3-1" },
  { homeTeam: "Inter",     awayTeam: "Juventus",    matchTime: "01:45 - 24/07", hot: false,
    insights: ["Derby d'Italia có tỉ lệ hòa cao nhất Serie A", "Inter giữ sạch lưới 5/8 trận sân nhà"],
    prediction: "Hòa 1-1" },
];

function articleHtml(title) {
  return `<h2>Diễn biến trận đấu</h2>
<p>${title}. Trận đấu diễn ra với thế trận áp đảo ngay từ những phút đầu tiên, khi đội chủ nhà liên tục tạo sức ép lên khung thành đối phương.</p>
<h2>Phân tích chiến thuật</h2>
<p>Sơ đồ 4-3-3 phát huy hiệu quả rõ rệt, đặc biệt ở khu vực giữa sân nơi các tiền vệ kiểm soát bóng vượt trội.</p>
<h2>Nhận định</h2>
<p>Kết quả này tác động lớn tới cuộc đua vô địch trong giai đoạn còn lại của mùa giải.</p>`;
}

async function uploadFromUrl(url, filename) {
  const res = await axios.get(url, { responseType: "arraybuffer", timeout: 20000 });
  return wp.uploadMedia(Buffer.from(res.data), filename);
}

async function main() {
  const cats = await wp.fetchCategories();
  console.log(`📁 ${Object.keys(cats).length} danh mục có sẵn`);

  for (const p of POSTS) {
    const media = await uploadFromUrl(p.img, `seed-${p.cat}-${Date.now()}.jpg`);
    const post = await wp.createPost({
      title: p.title,
      html: articleHtml(p.title),
      excerpt: `${p.title} — cập nhật chi tiết diễn biến, phân tích chiến thuật và nhận định chuyên sâu.`,
      categoryId: cats[p.cat]?.id ?? null,
      tags: ["Bóng đá", "Phân tích"],
      featuredMedia: media.id,
    });
    console.log(`📰 ${post.id} — ${p.title}`);
  }

  for (const i of INSIGHTS) {
    const insight = await wp.createInsight(i);
    console.log(`⚽ ${insight.id} — ${i.homeTeam} vs ${i.awayTeam}${i.hot ? " 🔥" : ""}`);
  }

  console.log("\n✅ Seed xong. Mở http://bongda247.local");
}

main().catch((e) => {
  console.error("❌ Seed lỗi:", e.response?.data ?? e.message);
  process.exit(1);
});
```

**Lưu ý về `matchTime`:** `MatchInsights` lọc bỏ insight có ngày đã qua. Các ngày trong `INSIGHTS` ở trên (20–24/07) là **tương lai so với 13/07/2026**. Nếu chạy plan này ở thời điểm khác, sửa cho khớp — nếu không carousel sẽ rỗng và bạn sẽ tưởng theme hỏng.

- [ ] **Step 2: Thêm script vào `web/package.json`**

```json
"seed": "node scripts/seed-wp.mjs"
```

- [ ] **Step 3: Chạy seed**

Run: `cd web && npm run seed`
Expected:
```
📁 9 danh mục có sẵn
📰 <id> — Arsenal đè bẹp Chelsea 3-0 tại Emirates
... (6 bài)
⚽ <id> — Arsenal vs Chelsea 🔥
... (5 insight)
✅ Seed xong.
```

- [ ] **Step 4: Kiểm chứng qua wp-cli**

Run:
```bash
cd .. && ./wp/bin/wp post list --post_type=post --format=count
./wp/bin/wp post list --post_type=match_insight --format=count
```
Expected: `6` và `5`

- [ ] **Step 5: Commit**

```bash
git add web/scripts/seed-wp.mjs web/package.json
git commit -m "feat(wp): script seed dữ liệu demo qua REST"
```

---

## Task 4: Theme skeleton — build assets, header, footer, theme toggle

**Files:**
- Create: `wp/themes/bongda247/style.css`
- Create: `wp/themes/bongda247/functions.php`
- Create: `wp/themes/bongda247/index.php`
- Create: `wp/themes/bongda247/header.php`
- Create: `wp/themes/bongda247/footer.php`
- Create: `wp/themes/bongda247/template-parts/theme-toggle.php`
- Create: `wp/themes/bongda247/src/main.css`
- Create: `wp/themes/bongda247/src/main.js`
- Create: `wp/themes/bongda247/package.json`
- Copy: font + `flame.png` từ `web/public` / `web/src/assets`

**Interfaces:**
- Consumes: dữ liệu seed từ Task 3
- Produces:
  - Theme `bongda247` active, `dist/main.css` + `dist/main.js` được enqueue
  - Biến CSS Tailwind: `--color-brand`, `--color-accent`, `--color-bg`, `--color-card`, `--color-secondary`
  - Class helper dùng lại ở task sau: `.container`, `.bg-card`, `.border-card`, `.text-secondary`, `.font-hemi`, `.bg-control`, `.bg-prediction`
  - Global JS: `window.Swiper` (từ bundle vendor)

---

- [ ] **Step 1: Copy assets tĩnh**

```bash
mkdir -p wp/themes/bongda247/assets/fonts wp/themes/bongda247/assets/images wp/themes/bongda247/assets/vendor
cp web/public/font/SVN-Hemi/SVN-HemiHead.woff2 wp/themes/bongda247/assets/fonts/
cp web/src/assets/images/flame.png wp/themes/bongda247/assets/images/
cp web/public/logo/logo-svg.svg wp/themes/bongda247/assets/images/ 2>/dev/null || true
```

- [ ] **Step 2: Tạo `package.json` của theme + vendor Swiper**

`wp/themes/bongda247/package.json`:
```json
{
  "name": "bongda247-theme",
  "private": true,
  "scripts": {
    "vendor": "mkdir -p assets/vendor && cp node_modules/swiper/swiper-bundle.min.js node_modules/swiper/swiper-bundle.min.css assets/vendor/",
    "build:css": "tailwindcss -i src/main.css -o dist/main.css --minify",
    "build:js": "mkdir -p dist && cp src/main.js dist/main.js",
    "build": "npm run vendor && npm run build:css && npm run build:js",
    "watch": "tailwindcss -i src/main.css -o dist/main.css --watch"
  },
  "devDependencies": {
    "@tailwindcss/cli": "^4.2.1",
    "tailwindcss": "^4.2.1",
    "swiper": "^12.1.2"
  }
}
```

Swiper được **vendor** (copy `swiper-bundle.min.js/.css` vào `assets/vendor/`) thay vì bundle — đã xác nhận Swiper 12.1.2 có sẵn 2 file này. Không cần Vite/esbuild, và deploy lên hosting không cần chạy Node.

```bash
cd wp/themes/bongda247 && npm install && npm run vendor
```

- [ ] **Step 3: Viết `src/main.css` (Tailwind v4 + design tokens)**

```css
@import "tailwindcss";

@source "../**/*.php";

/* BẮT BUỘC: Tailwind v4 mặc định gắn dark: với prefers-color-scheme, KHÔNG phải class .dark.
   Thiếu dòng này thì nút toggle bấm không có tác dụng (dark:block / dark:hidden im lìm). */
@custom-variant dark (&:where(.dark, .dark *));

@theme {
  --color-brand: #0232ff;
  --color-accent: #dc2626;

  --font-inter: "Inter", system-ui, sans-serif;
  --font-oswald: "Oswald", system-ui, sans-serif;
  --font-hemi: "SVN-Hemi Head", "Oswald", system-ui, sans-serif;
}

@font-face {
  font-family: "SVN-Hemi Head";
  src: url("../assets/fonts/SVN-HemiHead.woff2") format("woff2");
  font-display: swap;
}

/* Token đổi theo theme sáng/tối */
:root {
  --bd-bg: #f3f3f3;
  --bd-card: #f5f8fc;
  --bd-text: #0e1217;
  --bd-secondary: #5b6472;
  --bd-border: #e2e8f0;
  --bd-control: #ffffff;
}

.dark {
  --bd-bg: #0e1217;
  --bd-card: #1c1f26;
  --bd-text: #f3f3f3;
  --bd-secondary: #94a3b8;
  --bd-border: #2a2f3a;
  --bd-control: #1c1f26;
}

@layer base {
  body {
    background-color: var(--bd-bg);
    color: var(--bd-text);
    font-family: var(--font-inter);
  }
  h1, h2, h3 { font-family: var(--font-oswald); }
}

@layer components {
  .container      { @apply mx-auto w-full max-w-[1200px] px-4; }
  .row            { @apply flex flex-wrap -mx-3; }
  .col            { @apply px-3; }
  .col-8          { @apply w-full lg:w-2/3; }
  .col-4          { @apply w-full lg:w-1/3; }
  .font-hemi      { font-family: var(--font-hemi); }
  .bg-card        { background-color: var(--bd-card); }
  .bg-control     { background-color: var(--bd-control); }
  .border-card    { border-color: var(--bd-border); }
  .text-secondary { color: var(--bd-secondary); }
  .bg-prediction  { @apply bg-brand text-white; }
  .header         { background-color: var(--bd-bg); @apply border-b border-card; }
}
```

**KHÔNG** tự định nghĩa `.bg-brand`, `.text-brand`, `.border-brand` — `@theme { --color-brand }` đã tự sinh sẵn 3 utility này. Khai lại chỉ gây trùng và dễ xung đột thứ tự.

- [ ] **Step 4: Viết `src/main.js`**

```js
// Khởi tạo 3 Swiper + theme toggle. Swiper nạp từ assets/vendor (biến toàn cục window.Swiper).
(function () {
  "use strict";

  // --- Theme toggle ---
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-theme-toggle]");
    if (!btn) return;
    var isDark = document.documentElement.classList.toggle("dark");
    localStorage.setItem("theme", isDark ? "dark" : "light");
  });

  // --- Swiper ---
  document.addEventListener("DOMContentLoaded", function () {
    if (typeof Swiper === "undefined") return;

    if (document.querySelector(".hotSwiper")) {
      new Swiper(".hotSwiper", {
        autoplay: { delay: 5000, disableOnInteraction: false },
        pagination: { el: ".swiper-pagination", clickable: true },
        speed: 800,
      });
    }

    if (document.querySelector(".sidebarSwiper")) {
      new Swiper(".sidebarSwiper", {
        direction: "vertical",
        slidesPerView: "auto",
        spaceBetween: 10,
        mousewheel: true,
        autoplay: { delay: 5000, disableOnInteraction: false },
      });
    }

    if (document.querySelector(".insightSwiper")) {
      new Swiper(".insightSwiper", {
        slidesPerView: 1,
        spaceBetween: 12,
        navigation: { nextEl: ".insight-next", prevEl: ".insight-prev" },
        breakpoints: {
          640: { slidesPerView: 2, spaceBetween: 12 },
          768: { slidesPerView: 3, spaceBetween: 12 },
        },
      });
    }
  });
})();
```

- [ ] **Step 5: Viết `style.css` (theme header) và `functions.php`**

`wp/themes/bongda247/style.css`:
```css
/*
Theme Name: Bongda247
Description: Theme tin tức và nhận định bóng đá — port từ Astro.
Version: 1.0.0
Text Domain: bongda247
*/
```

`wp/themes/bongda247/functions.php`:
```php
<?php
defined('ABSPATH') || exit;

require_once get_stylesheet_directory() . '/inc/query.php';

add_action('after_setup_theme', function () {
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('html5', ['gallery', 'caption', 'style', 'script']);
    add_image_size('bd_hero', 1200, 675, true);
    add_image_size('bd_thumb', 200, 200, true);
    register_nav_menus(['primary' => 'Menu chính']);
});

add_action('wp_enqueue_scripts', function () {
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();

    $ver = fn($rel) => file_exists($dir . $rel) ? (string) filemtime($dir . $rel) : '1.0.0';

    wp_enqueue_style('swiper', $uri . '/assets/vendor/swiper-bundle.min.css', [], $ver('/assets/vendor/swiper-bundle.min.css'));
    wp_enqueue_style('bongda247', $uri . '/dist/main.css', ['swiper'], $ver('/dist/main.css'));

    wp_enqueue_script('swiper', $uri . '/assets/vendor/swiper-bundle.min.js', [], $ver('/assets/vendor/swiper-bundle.min.js'), true);
    wp_enqueue_script('bongda247', $uri . '/dist/main.js', ['swiper'], $ver('/dist/main.js'), true);
});

// Google Fonts: Inter + Oswald
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'bongda247-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@400;500;600;700&display=swap',
        [],
        null
    );
});
```

Tạo `wp/themes/bongda247/inc/query.php` (rỗng ở task này, điền ở Task 5 — nhưng phải tồn tại vì `functions.php` require):
```php
<?php
defined('ABSPATH') || exit;
// Hàm query dùng chung — bổ sung ở Task 5.
```

- [ ] **Step 6: Viết `header.php`, `footer.php`, `index.php`, `theme-toggle.php`**

`wp/themes/bongda247/header.php` — port `Header.astro`. Script chống nháy màu (FOUC) đặt **inline trong `<head>`, trước CSS**:

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

<header class="fixed top-0 w-full z-50 header">
  <div class="container mx-auto">
    <nav>
      <div class="flex items-center justify-between h-16">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="flex items-center space-x-2 group">
          <span class="font-hemi text-2xl font-bold uppercase">
            BONGDA<span class="text-brand">247</span>
          </span>
        </a>

        <ul class="hidden lg:flex space-x-8">
          <?php
          $nav = [
              ['name' => 'Ngoại hạng Anh', 'slug' => 'ngoai-hang-anh'],
              ['name' => 'Chuyển nhượng',  'slug' => 'chuyen-nhuong'],
              ['name' => 'La Liga',        'slug' => 'la-liga'],
          ];
          foreach ($nav as $item) :
              $term = get_term_by('slug', $item['slug'], 'category');
              if (!$term) continue;
          ?>
            <li>
              <a href="<?php echo esc_url(get_term_link($term)); ?>"
                 class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">
                <?php echo esc_html($item['name']); ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="flex items-center space-x-4">
          <?php get_template_part('template-parts/theme-toggle'); ?>
        </div>
      </div>
    </nav>
  </div>
</header>

<main class="pt-24 pb-16">
```

Nav cũ trỏ tới `/nhan-dinh`, `/lich-thi-dau`, `/bang-xep-hang` — cả 3 đều 404 (spec §3). Thay bằng 3 category có thật.

`wp/themes/bongda247/footer.php`:
```php
<?php defined('ABSPATH') || exit; ?>
</main>

<footer class="border-t border-card py-10">
  <div class="container">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
      <span class="font-hemi text-xl uppercase">
        BONGDA<span class="text-brand">247</span>
      </span>
      <p class="text-sm text-secondary">
        © <?php echo esc_html(date('Y')); ?> Bongda247. Tin tức và nhận định bóng đá.
      </p>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
```

`wp/themes/bongda247/template-parts/theme-toggle.php`:
```php
<?php defined('ABSPATH') || exit; ?>
<button data-theme-toggle
        aria-label="Đổi giao diện sáng/tối"
        class="p-2 rounded-full border border-card bg-control cursor-pointer transition-colors hover:text-brand">
  <svg class="w-5 h-5 hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
       stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="4"></circle>
    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>
  </svg>
  <svg class="w-5 h-5 block dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
       stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path>
  </svg>
</button>
```

`wp/themes/bongda247/index.php` (fallback — WP bắt buộc phải có):
```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-8">
    <?php echo esc_html(get_the_archive_title() ?: 'Tin tức'); ?>
  </h1>

  <?php if (have_posts()) : ?>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <?php while (have_posts()) : the_post(); ?>
        <article class="rounded-lg bg-card border border-card overflow-hidden">
          <a href="<?php the_permalink(); ?>" class="block">
            <?php if (has_post_thumbnail()) : ?>
              <?php the_post_thumbnail('bd_hero', ['class' => 'w-full aspect-video object-cover']); ?>
            <?php endif; ?>
            <div class="p-4">
              <h2 class="text-lg leading-snug hover:text-brand transition-colors"><?php the_title(); ?></h2>
              <p class="text-sm text-secondary mt-2 line-clamp-2"><?php echo esc_html(get_the_excerpt()); ?></p>
            </div>
          </a>
        </article>
      <?php endwhile; ?>
    </div>
  <?php else : ?>
    <p class="text-secondary">Chưa có bài viết nào.</p>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 7: Build CSS + symlink theme + activate**

```bash
cd wp/themes/bongda247 && npm run build && cd ../../..
SITE="$HOME/Local Sites/bongda247/app/public"
ln -sfn "$(pwd)/wp/themes/bongda247" "$SITE/wp-content/themes/bongda247"
./wp/bin/wp theme activate bongda247
```

- [ ] **Step 8: Kiểm chứng theme chạy**

Run:
```bash
./wp/bin/wp theme list --status=active --field=name
curl -s http://bongda247.local/ -o /tmp/bd-home.html -w "status %{http_code}\n"
grep -c "dist/main.css" /tmp/bd-home.html
grep -c "swiper-bundle.min.js" /tmp/bd-home.html
grep -c "BONGDA" /tmp/bd-home.html
grep -c "classList.add('dark')" /tmp/bd-home.html
```
Expected:
```
bongda247
status 200
1
1
(≥1)
1
```

Kiểm tra CSS đã compile (không rỗng):
```bash
wc -c wp/themes/bongda247/dist/main.css
```
Expected: > 10000 bytes. Nếu ~0 → `@source "../**/*.php"` sai đường dẫn, Tailwind không quét được class trong PHP.

- [ ] **Step 9: Commit**

```bash
git add wp/themes/bongda247
git commit -m "feat(theme): skeleton bongda247 — build Tailwind, header, footer, theme toggle"
```

---

## Task 5: Trang chủ — hot news slider, sidebar, match insights

**Files:**
- Modify: `wp/themes/bongda247/inc/query.php`
- Create: `wp/themes/bongda247/front-page.php`
- Create: `wp/themes/bongda247/template-parts/hot-news-slider.php`
- Create: `wp/themes/bongda247/template-parts/sidebar-slider.php`
- Create: `wp/themes/bongda247/template-parts/match-insights.php`

**Interfaces:**
- Consumes: theme skeleton + `window.Swiper` (Task 4); 6 bài + 5 insight seed (Task 3); meta `home_team`/`away_team`/`match_time`/`hot`/`insights`/`prediction` (Task 1)
- Produces:
  - `bd_hot_posts(int $n = 5): WP_Query`
  - `bd_sidebar_posts(int $n = 10): WP_Query`
  - `bd_insights(int $n = 15): WP_Query`
  - `bd_insight_is_upcoming(string $match_time, string $match_date = ''): bool`

---

- [ ] **Step 1: Viết `inc/query.php`**

```php
<?php
defined('ABSPATH') || exit;

/** 5 bài mới nhất — carousel tin hot trang chủ. */
function bd_hot_posts($n = 5) {
    return new WP_Query([
        'post_type'           => 'post',
        'posts_per_page'      => $n,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ]);
}

/** 10 bài mới nhất — sidebar "Tin mới nhận". */
function bd_sidebar_posts($n = 10) {
    return new WP_Query([
        'post_type'           => 'post',
        'posts_per_page'      => $n,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ]);
}

/**
 * Nhận định trận — hot lên trước, rồi tới mới nhất.
 * hot lưu dạng integer 0/1 nên meta_value_num sắp xếp tin cậy.
 */
function bd_insights($n = 15) {
    return new WP_Query([
        'post_type'      => 'match_insight',
        'posts_per_page' => $n,
        'no_found_rows'  => true,
        'meta_key'       => 'hot',
        'orderby'        => ['meta_value_num' => 'DESC', 'date' => 'DESC'],
    ]);
}

/**
 * Insight còn nên hiển thị không?
 *
 * Ưu tiên $match_date (ISO UTC đầy đủ, do bot lấy từ football-data.org) — chính xác
 * và đúng cả khi bắc cầu qua năm mới.
 *
 * Chỉ khi không có $match_date (insight nhập tay qua Telegram) mới rơi về parse chuỗi
 * "HH:mm - DD/MM". Nhánh fallback này so ngày/tháng mà không so năm nên sai quanh
 * giao thừa — chấp nhận được vì nó chỉ áp dụng cho insight thủ công.
 */
function bd_insight_is_upcoming($match_time, $match_date = '') {
    // Nhánh chính: có datetime đầy đủ.
    if ($match_date) {
        $ts = strtotime($match_date);
        if ($ts) {
            // Cho hiển thị tới 3 tiếng sau giờ bóng lăn — khớp ngưỡng cron dọn dẹp của bot.
            return $ts > (time() - 3 * HOUR_IN_SECONDS);
        }
    }

    // Fallback: chỉ có chuỗi "HH:mm - DD/MM".
    if (!$match_time) {
        return false;
    }

    $parts    = explode(' - ', $match_time);
    $date_str = count($parts) > 1 ? $parts[1] : $parts[0];

    if (!str_contains($date_str, '/')) {
        return true;
    }

    $bits  = explode('/', $date_str);
    $day   = (int) ($bits[0] ?? 0);
    $month = (int) ($bits[1] ?? 0);

    $now_day   = (int) current_time('j');
    $now_month = (int) current_time('n');

    if ($month > $now_month) {
        return true;
    }

    return $month === $now_month && $day >= $now_day;
}
```

- [ ] **Step 2: Viết `template-parts/hot-news-slider.php`** (port `HotNewsSlider.astro`)

```php
<?php
defined('ABSPATH') || exit;
$hot = bd_hot_posts(5);
?>
<div class="relative group">
  <div class="swiper hotSwiper rounded-2xl overflow-hidden border border-card shadow-2xl">
    <div class="swiper-wrapper">
      <?php while ($hot->have_posts()) : $hot->the_post();
          $cats = get_the_category();
          $cat  = $cats[0] ?? null;
      ?>
        <div class="swiper-slide">
          <div class="relative aspect-video">
            <a href="<?php the_permalink(); ?>" class="block absolute inset-0 z-30"></a>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent z-10"></div>

            <?php if (has_post_thumbnail()) : ?>
              <span class="absolute inset-0">
                <?php the_post_thumbnail('bd_hero', ['class' => 'w-full h-full object-cover']); ?>
              </span>
            <?php else : ?>
              <div class="w-full absolute h-full bg-slate-900 flex items-center justify-center">
                <span class="font-hemi text-slate-700 italic text-4xl">BONGDA247</span>
              </div>
            <?php endif; ?>

            <div class="absolute bottom-0 left-0 p-6 md:p-10 z-20 w-full">
              <?php if ($cat) : ?>
                <span class="inline-block px-3 py-1 rounded-md mb-3 text-[10px] font-bold uppercase font-hemi tracking-wider bg-brand text-white">
                  <?php echo esc_html($cat->name); ?>
                </span>
              <?php endif; ?>
              <h2 class="text-2xl md:text-4xl font-hemi text-white leading-tight mb-3 line-clamp-2 drop-shadow-md">
                <?php the_title(); ?>
              </h2>
              <p class="text-slate-300 text-sm md:text-base line-clamp-2 hidden md:block opacity-90">
                <?php echo esc_html(get_the_excerpt()); ?>
              </p>
            </div>
          </div>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <div class="swiper-pagination !bottom-4"></div>
  </div>
</div>
```

- [ ] **Step 3: Viết `template-parts/sidebar-slider.php`** (port `SidebarSlider.astro`)

```php
<?php
defined('ABSPATH') || exit;
$side = bd_sidebar_posts(10);
?>
<aside class="flex h-full flex-col">
  <h2 class="font-hemi text-lg uppercase mb-4 text-brand flex items-center gap-2">
    <span class="w-2 h-2 bg-brand rounded-full animate-pulse"></span>
    Tin mới nhận
  </h2>
  <div class="flex-1 relative overflow-hidden min-h-[400px]">
    <div class="inset-0 absolute">
      <div class="swiper sidebarSwiper h-full">
        <div class="swiper-wrapper">
          <?php while ($side->have_posts()) : $side->the_post();
              $cats = get_the_category();
              $cat  = $cats[0] ?? null;
          ?>
            <div class="swiper-slide !h-auto">
              <article class="group relative flex gap-4 rounded-lg bg-card p-3 border border-card">
                <a href="<?php the_permalink(); ?>" class="absolute inset-0 z-20"></a>
                <div class="flex gap-3">
                  <div class="w-20 h-20 shrink-0 overflow-hidden rounded-lg bg-slate-800">
                    <?php if (has_post_thumbnail()) : ?>
                      <?php the_post_thumbnail('bd_thumb', [
                          'class' => 'w-full h-full object-cover group-hover:scale-110 transition-transform duration-500',
                      ]); ?>
                    <?php else : ?>
                      <div class="w-full h-full flex items-center justify-center bg-slate-900">
                        <span class="text-[10px] font-hemi text-white">BONGDA247</span>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="flex flex-col justify-center">
                    <?php if ($cat) : ?>
                      <span class="text-[9px] font-bold text-brand uppercase mb-1"><?php echo esc_html($cat->name); ?></span>
                    <?php endif; ?>
                    <h3 class="text-base leading-snug group-hover:text-brand transition-colors line-clamp-2">
                      <?php the_title(); ?>
                    </h3>
                  </div>
                </div>
              </article>
            </div>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>
      </div>
    </div>
  </div>
</aside>
```

- [ ] **Step 4: Viết `template-parts/match-insights.php`** (port `MatchInsights.astro`)

```php
<?php
defined('ABSPATH') || exit;
$insights = bd_insights(15);
$flame    = get_stylesheet_directory_uri() . '/assets/images/flame.png';
?>
<section class="py-8">
  <div class="flex items-center justify-between mb-6">
    <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4">Số liệu chuyên sâu</h2>
    <div class="flex gap-2">
      <button class="insight-prev p-2 rounded-full border border-card hover:bg-brand cursor-pointer transition-colors bg-control" aria-label="Trước">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m15 18-6-6 6-6"></path>
        </svg>
      </button>
      <button class="insight-next p-2 rounded-full border border-card hover:bg-brand cursor-pointer transition-colors bg-control" aria-label="Sau">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m9 18 6-6-6-6"></path>
        </svg>
      </button>
    </div>
  </div>

  <div class="swiper insightSwiper">
    <div class="swiper-wrapper">
      <?php while ($insights->have_posts()) : $insights->the_post();
          $id         = get_the_ID();
          $match_time = (string) get_post_meta($id, 'match_time', true);
          $match_date = (string) get_post_meta($id, 'match_date', true);

          if (!bd_insight_is_upcoming($match_time, $match_date)) {
              continue;
          }

          $home       = (string) get_post_meta($id, 'home_team', true);
          $away       = (string) get_post_meta($id, 'away_team', true);
          $hot        = (int) get_post_meta($id, 'hot', true) === 1;
          $lines      = (array) get_post_meta($id, 'insights', true);
          $prediction = (string) get_post_meta($id, 'prediction', true);
      ?>
        <div class="swiper-slide !h-auto">
          <div class="group flex gap-4 rounded-lg bg-card p-3 border border-card flex-col h-full">
            <div>
              <div class="flex justify-between items-center mb-4">
                <span class="text-secondary text-sm font-medium"><?php echo esc_html($match_time); ?></span>
                <?php if ($hot) : ?>
                  <img src="<?php echo esc_url($flame); ?>" alt="Hot" width="20" height="20">
                <?php endif; ?>
              </div>

              <div class="mb-4">
                <h3 class="font-hemi text-xl flex items-center gap-3">
                  <?php echo esc_html($home); ?> <span>VS</span> <?php echo esc_html($away); ?>
                </h3>
              </div>

              <?php if ($lines) : ?>
                <ul class="space-y-4 mb-2">
                  <?php foreach ($lines as $line) : ?>
                    <li class="text-sm text-secondary flex items-start gap-3 leading-relaxed">
                      <span class="w-1.5 h-1.5 rounded-full bg-brand mt-1.5 shrink-0"></span>
                      <?php echo esc_html($line); ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>

            <?php if ($prediction) : ?>
              <div class="inline-block mt-auto w-fit ml-auto text-sm transition-all p-2 px-4 rounded-full font-hemi bg-prediction">
                <?php echo esc_html($prediction); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
```

- [ ] **Step 5: Viết `front-page.php`** (port `index.astro`)

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

<?php get_footer(); ?>
```

- [ ] **Step 6: Rebuild CSS và kiểm chứng trang chủ**

```bash
cd wp/themes/bongda247 && npm run build:css && cd ../../..
curl -s http://bongda247.local/ -o /tmp/bd-home.html -w "status %{http_code}\n"

echo "-- hot news slider --"
grep -c "hotSwiper" /tmp/bd-home.html
grep -c "Arsenal đè bẹp Chelsea" /tmp/bd-home.html

echo "-- sidebar --"
grep -c "sidebarSwiper" /tmp/bd-home.html
grep -c "Tin mới nhận" /tmp/bd-home.html

echo "-- insights --"
grep -c "insightSwiper" /tmp/bd-home.html
grep -c "Arsenal thắng 8/10 trận sân nhà gần đây" /tmp/bd-home.html
grep -c "Arsenal thắng 2-1" /tmp/bd-home.html

echo "-- số slide insight (kỳ vọng 5) --"
grep -o 'class="swiper-slide !h-auto"' /tmp/bd-home.html | wc -l
```
Expected: `status 200`, mọi `grep -c` ≥ 1, và số slide = 15 (5 insight + 10 sidebar dùng chung class).

Nếu `Arsenal thắng 8/10...` không thấy → meta `insights` (mảng) không đọc được → kiểm tra lại `register_post_meta` ở Task 1.
Nếu carousel insight rỗng → `matchTime` seed đã quá hạn, sửa ngày trong `seed-wp.mjs` rồi seed lại.

- [ ] **Step 7: Xem bằng mắt**

Mở `http://bongda247.local` trên trình duyệt. Kiểm tra:
- Slider tin hot tự chạy, có pagination
- Sidebar trượt dọc
- Carousel insight có nút ‹ › hoạt động, badge 🔥 hiện trên 2 trận hot
- Bấm nút toggle → chuyển sáng/tối; reload → **không nháy màu**

- [ ] **Step 8: Commit**

```bash
git add wp/themes/bongda247
git commit -m "feat(theme): trang chủ — hot news slider, sidebar, match insights"
```

---

## Task 6: Trang bài viết, category archive, 404

**Files:**
- Create: `wp/themes/bongda247/single.php`
- Create: `wp/themes/bongda247/archive.php`
- Create: `wp/themes/bongda247/404.php`

**Interfaces:**
- Consumes: theme skeleton (Task 4), dữ liệu seed (Task 3)
- Produces: URL `/{category}/{slug}/` và `/category/{slug}/` render 200

---

- [ ] **Step 1: Viết `single.php`** (port `[category]/[slug].astro`)

```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <?php while (have_posts()) : the_post();
      $cats = get_the_category();
      $cat  = $cats[0] ?? null;
      $tags = get_the_tags();
  ?>
    <article class="max-w-4xl mx-auto">
      <nav class="flex text-sm mb-8 gap-2 font-medium text-secondary">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="transition-colors hover:text-brand">Trang chủ</a>
        <?php if ($cat) : ?>
          <span>/</span>
          <a href="<?php echo esc_url(get_category_link($cat)); ?>" class="hover:underline text-brand">
            <?php echo esc_html($cat->name); ?>
          </a>
        <?php endif; ?>
      </nav>

      <header class="mb-10">
        <h1 class="text-4xl md:text-5xl font-bold font-oswald leading-tight mb-6"><?php the_title(); ?></h1>

        <div class="flex items-center gap-4 text-sm pb-6 text-secondary border-b border-card">
          <?php if ($cat) : ?>
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-brand/15 text-brand">
              <?php echo esc_html($cat->name); ?>
            </span>
          <?php endif; ?>
          <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('d/m/Y')); ?></time>
        </div>
      </header>

      <?php if (has_post_thumbnail()) : ?>
        <div class="mb-12 rounded-2xl overflow-hidden shadow-2xl border border-card">
          <?php the_post_thumbnail('bd_hero', ['class' => 'w-full h-auto object-cover']); ?>
        </div>
      <?php endif; ?>

      <div class="p-6 md:p-10 rounded-3xl border border-card shadow-inner bg-card">
        <div class="prose-bd">
          <?php the_content(); ?>
        </div>

        <?php if ($tags) : ?>
          <div class="mt-10 pt-6 flex flex-wrap gap-2 border-t border-card">
            <?php foreach ($tags as $tag) : ?>
              <a href="<?php echo esc_url(get_tag_link($tag)); ?>"
                 class="text-sm transition-colors text-secondary hover:text-brand">
                #<?php echo esc_html($tag->name); ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php
        $source_url    = get_post_meta(get_the_ID(), 'source_url', true);
        $source_credit = get_post_meta(get_the_ID(), 'source_credit', true);
        if ($source_url) : ?>
          <p class="mt-6 text-xs text-secondary">
            Nguồn:
            <a href="<?php echo esc_url($source_url); ?>" rel="nofollow noopener" target="_blank" class="hover:text-brand">
              <?php echo esc_html($source_credit ?: $source_url); ?>
            </a>
          </p>
        <?php endif; ?>
      </div>
    </article>
  <?php endwhile; ?>
</div>

<?php get_footer(); ?>
```

Thêm style cho nội dung bài vào `src/main.css`, trong `@layer components`:
```css
  .prose-bd h2       { @apply font-hemi text-2xl uppercase mt-8 mb-4; }
  .prose-bd p        { @apply mb-4 leading-relaxed text-secondary; }
  .prose-bd figure   { @apply my-8; }
  .prose-bd img      { @apply w-full h-auto rounded-xl; }
  .prose-bd figcaption { @apply text-xs text-secondary text-center mt-2; }
```

- [ ] **Step 2: Viết `archive.php`**

```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-8">
    <?php echo esc_html(single_term_title('', false) ?: get_the_archive_title()); ?>
  </h1>

  <?php if (have_posts()) : ?>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <?php while (have_posts()) : the_post(); ?>
        <article class="rounded-lg bg-card border border-card overflow-hidden group">
          <a href="<?php the_permalink(); ?>" class="block">
            <?php if (has_post_thumbnail()) : ?>
              <div class="overflow-hidden aspect-video">
                <?php the_post_thumbnail('bd_hero', [
                    'class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500',
                ]); ?>
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
    <p class="text-secondary">Chưa có bài viết nào trong mục này.</p>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 3: Viết `404.php`**

```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container text-center py-20">
  <h1 class="font-hemi text-6xl uppercase mb-4">404</h1>
  <p class="text-secondary mb-8">Không tìm thấy trang bạn cần.</p>
  <a href="<?php echo esc_url(home_url('/')); ?>"
     class="inline-block px-6 py-3 rounded-full bg-brand text-white font-hemi uppercase text-sm">
    Về trang chủ
  </a>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 4: Rebuild + kiểm chứng**

```bash
cd wp/themes/bongda247 && npm run build:css && cd ../../..

POST_URL=$(./wp/bin/wp post list --post_type=post --posts_per_page=1 --field=url)
echo "Bài viết: $POST_URL"
curl -s "$POST_URL" -o /tmp/bd-single.html -w "single: %{http_code}\n"
grep -c "Trang chủ" /tmp/bd-single.html
grep -c "prose-bd" /tmp/bd-single.html
grep -c "Diễn biến trận đấu" /tmp/bd-single.html

curl -s http://bongda247.local/ngoai-hang-anh/ -o /tmp/bd-archive.html -w "archive: %{http_code}\n"
grep -c "Ngoại hạng Anh" /tmp/bd-archive.html

curl -s -o /dev/null -w "404: %{http_code}\n" http://bongda247.local/khong-ton-tai-abc/
```
Expected:
```
single: 200
(≥1 cho cả 3 grep)
archive: 200
(≥1)
404: 404
```

URL bài viết phải có dạng `http://bongda247.local/ngoai-hang-anh/arsenal-de-bep-chelsea-3-0-tai-emirates/` — nếu thiếu phần category thì permalink chưa đúng, chạy lại:
`./wp/bin/wp rewrite structure '/%category%/%postname%/' --hard && ./wp/bin/wp rewrite flush --hard`

- [ ] **Step 5: Commit**

```bash
git add wp/themes/bongda247
git commit -m "feat(theme): trang bài viết, category archive, 404"
```

---

## Task 7: Chuyển `bot-press.js` từ Sanity sang WordPress

**Files:**
- Modify: `web/bot-press.js` (15 điểm chạm)

**Interfaces:**
- Consumes: toàn bộ API của `web/lib/wp.js` (Task 2)
- Produces: bot ghi thẳng lên WordPress; không còn `import { createClient } from "@sanity/client"` trong repo

**KHÔNG đổi:** prompt Gemini, RSS pipeline, `matchPreviewImage.js`, luồng Telegram, lịch cron, `draftStore`/`pendingPosts`/`processedUrls`.

---

- [ ] **Step 1: Đổi import và bỏ Sanity client**

Trong `web/bot-press.js`, xoá dòng 6 và khối `createClient` (dòng 28–34), thay bằng:

```js
import * as wp from "./lib/wp.js";
```

- [ ] **Step 2: Thay `buildPortableText` bằng `buildHtml`**

Xoá hàm `buildPortableText` (~dòng 260–298). Thay bằng:

```js
// Escape cho attribute HTML (alt, title) — escapeHtml hiện có chỉ escape &, <, >
function escapeAttr(str) {
  return String(str ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

// images[] có thể là: null | { id, url, caption, credit }
// images[0] bỏ trống (dành cho mainImage), images[i+1] chèn sau section i.
function buildHtml(sections, images = [], defaultAlt = "") {
  return sections
    .map((section, i) => {
      const heading = stripHtml(section.heading);

      const paragraphs =
        Array.isArray(section.paragraphs) && section.paragraphs.length
          ? section.paragraphs
          : [section.text ?? ""];

      let html = `<h2>${escapeHtml(heading)}</h2>\n`;
      html += paragraphs
        .map((p) => `<p>${escapeHtml(stripHtml(p))}</p>`)
        .join("\n");

      const img = normalizeImage(images[i + 1]);
      if (img?.url) {
        const alt = escapeAttr(defaultAlt || heading);
        const caption = img.caption ? escapeHtml(img.caption) : "";
        const credit = img.credit ? ` <cite>(${escapeHtml(img.credit)})</cite>` : "";
        html += `\n<figure class="wp-block-image size-large">`;
        html += `<img src="${escapeAttr(img.url)}" alt="${alt}" class="wp-image-${img.id}" />`;
        if (caption || credit) {
          html += `<figcaption>${caption}${credit}</figcaption>`;
        }
        html += `</figure>`;
      }

      return html;
    })
    .join("\n\n");
}
```

Sửa `normalizeImage` để mang theo `url` (WP cần URL để chèn vào HTML, khác Sanity chỉ cần asset ref):

```js
// v: null | { id, url, caption?, credit? }
function normalizeImage(v) {
  if (!v) return null;
  return { caption: null, credit: null, ...v };
}
```

- [ ] **Step 3: Thay 2 hàm upload ảnh**

`uploadPhotos` (~dòng 300) — nay trả mảng `{id, url}`:

```js
async function uploadPhotos(ctx, photos) {
  return Promise.all(
    photos.map(async (fileId) => {
      const link = await ctx.telegram.getFileLink(fileId);
      const res = await axios.get(link.href, { responseType: "arraybuffer" });
      return wp.uploadMedia(
        Buffer.from(res.data),
        `telegram-${Date.now()}-${Math.random().toString(36).slice(2, 8)}.jpg`
      );
    })
  );
}
```

`uploadImageFromUrl` (~dòng 1414) — nay trả `{id, url}` hoặc `null`:

```js
async function uploadImageFromUrl(imageUrl, filename = "") {
  try {
    const res = await axios.get(imageUrl, {
      responseType: "arraybuffer",
      timeout: 10000,
      headers: { "User-Agent": "Mozilla/5.0 (compatible; Bongda247Bot/1.0)" },
    });
    const ext = imageUrl.split("?")[0].match(/\.(jpg|jpeg|png|webp|gif)$/i)?.[1] || "jpg";
    const safeFilename = filename
      ? `${createSlug(filename).slice(0, 80)}.${ext}`
      : `bongda247-${Date.now()}.${ext}`;
    return await wp.uploadMedia(Buffer.from(res.data), safeFilename);
  } catch {
    return null;
  }
}
```

- [ ] **Step 4: Thay `loadCategories`**

```js
async function loadCategories() {
  try {
    CATEGORIES = await wp.fetchCategories();
    console.log(
      `✅ Đã tải ${Object.keys(CATEGORIES).length} danh mục:`,
      Object.keys(CATEGORIES).join(", ")
    );
  } catch (e) {
    console.error("❌ Không tải được danh mục:", e.message);
  }
}
```

`getCategoryId(slug)` giữ nguyên — `fetchCategories()` trả đúng shape `{ slug: { id, title } }`, chỉ khác `id` giờ là số thay vì chuỗi Sanity.

- [ ] **Step 5: Thay 2 lệnh `/list` và `/posts`**

`bot.command("list")` — đổi phần fetch:
```js
    const insights = await wp.listInsights(10);
    if (!insights.length) return ctx.reply("📭 Chưa có insight nào.");

    await ctx.reply(`📋 *${insights.length} Insight gần nhất:*`, { parse_mode: "Markdown" });
    for (const item of insights) {
      const badge = item.hot ? "🔥" : "⚽";
      await ctx.reply(
        `${badge} *${item.homeTeam}* vs *${item.awayTeam}*  ⏰ ${item.matchTime}`,
        {
          parse_mode: "Markdown",
          reply_markup: {
            inline_keyboard: [[{ text: "🗑 Xóa", callback_data: `delete_insight_${item.id}` }]],
          },
        }
      );
    }
```

`bot.command("posts")` — đổi phần fetch. WP trả `categoryIds`, cần map ngược sang tên qua `CATEGORIES`:
```js
    const posts = await wp.listPosts(8);
    if (!posts.length) return ctx.reply("📭 Chưa có bài viết nào.");

    // id danh mục → tên (CATEGORIES là map slug → {id, title})
    const catNameById = {};
    Object.values(CATEGORIES).forEach((c) => { catNameById[c.id] = c.title; });

    await ctx.reply(`📋 *${posts.length} bài viết gần nhất:*`, { parse_mode: "Markdown" });
    for (const post of posts) {
      const date = new Date(post.date).toLocaleDateString("vi-VN");
      const catName = catNameById[post.categoryIds[0]] ?? "—";
      await ctx.reply(
        `📰 *${post.title}*\n🏆 ${catName}  📅 ${date}`,
        {
          parse_mode: "Markdown",
          reply_markup: {
            inline_keyboard: [[{ text: "🗑 Xóa", callback_data: `delete_post_${post.id}` }]],
          },
        }
      );
    }
```

- [ ] **Step 6: Thay 2 chỗ tạo insight**

`bot.action("confirm_insight")` (~dòng 833):
```js
    await wp.createInsight({
      homeTeam: data.homeTeam,
      awayTeam: data.awayTeam,
      matchTime: data.matchTime,
      matchDate: data.matchDate ?? null,
      hot: !!data.hot,
      insights: data.insights,
      prediction: data.prediction,
    });
```

Callback `dapprove_` (~dòng 928) — y hệt, đổi `data` thành `draft`:
```js
      await wp.createInsight({
        homeTeam: draft.homeTeam,
        awayTeam: draft.awayTeam,
        matchTime: draft.matchTime,
        matchDate: draft.matchDate ?? null,
        hot: !!draft.hot,
        insights: draft.insights,
        prediction: draft.prediction,
      });
```

- [ ] **Step 7: Thay 3 chỗ tạo bài viết**

`bot.action("confirm_post")` (~dòng 862) — `assetIds` nay là mảng `{id, url}`:
```js
    const assets = data.photos.length > 0 ? await uploadPhotos(ctx, data.photos) : [];
    await wp.createPost({
      title: data.title,
      html: buildHtml(data.sections || [], assets),
      excerpt: data.excerpt,
      categoryId: data.categoryId ?? null,
      featuredMedia: assets[0]?.id ?? null,
    });
```

`maarticle_approve_` (~dòng 1027 + 1077) — ảnh preview Puppeteer + ảnh TheSportsDB:
```js
      // 1. Ảnh preview Puppeteer → featured image
      let mainImage = null;
      try {
        const previewBuffer = await generateMatchPreviewImage({
          homeTeam: data.homeTeam,
          awayTeam: data.awayTeam,
          matchTime: data.matchTime,
          matchDate: data.matchDate || null,
          leagueName: data.leagueName || "Premier League",
          leagueCode: data.leagueCode || null,
          homePlayer: data.mainPlayer || null,
          awayPlayer: null,
        });
        mainImage = await wp.uploadMedia(
          previewBuffer,
          `preview-${createSlug(data.homeTeam)}-vs-${createSlug(data.awayTeam)}-${Date.now()}.jpg`
        );
        console.log("✅ Preview image uploaded:", mainImage.id);
      } catch (e) {
        console.warn("⚠️ Không tạo được preview image:", e.message);
      }

      // 2. Ảnh TheSportsDB (giữ nguyên logic fallback 4 tầng hiện có) → sportsDbImageUrl, sportsDbLabel
      //    ... (không đổi)

      const sportsDbImage = sportsDbImageUrl
        ? await uploadImageFromUrl(sportsDbImageUrl, sportsDbLabel)
        : null;
      if (!mainImage) mainImage = sportsDbImage;

      // caption giữ nguyên logic hiện có → sportsDbCaption
      const contentImages = sportsDbImage
        ? [null, { ...sportsDbImage, caption: sportsDbCaption, credit: "TheSportsDB" }]
        : [];

      await wp.createPost({
        title: data.title,
        html: buildHtml(data.sections || [], contentImages, sportsDbCaption),
        excerpt: data.excerpt,
        categoryId: categoryId ?? null,
        tags: data.hashtags ?? [],
        featuredMedia: mainImage?.id ?? null,
      });
```

**Lưu ý:** `matchPreviewImage.js` trả buffer **JPEG** (spec §Match Preview Image). Tên file cũ là `.png` — sai MIME. Đổi sang `.jpg` như trên.

`ndapprove_` (RSS, ~dòng 1172):
```js
      const mainImage = article.imageUrl
        ? await uploadImageFromUrl(article.imageUrl, generatedPost.title)
        : null;

      const sportsDbLabel = generatedPost.mainPlayer || generatedPost.mainTeam || generatedPost.title;
      const sportsDbImage = article.sportsDbImageUrl
        ? await uploadImageFromUrl(article.sportsDbImageUrl, sportsDbLabel)
        : null;

      // sportsDbCaption giữ nguyên logic hiện có

      const contentImages = sportsDbImage
        ? [null, { ...sportsDbImage, caption: sportsDbCaption, credit: "TheSportsDB" }]
        : [];

      await wp.createPost({
        title: generatedPost.title,
        html: buildHtml(generatedPost.sections || [], contentImages, sportsDbLabel),
        excerpt: generatedPost.excerpt,
        categoryId: categoryId ?? null,
        tags: generatedPost.hashtags ?? [],
        featuredMedia: mainImage?.id ?? null,
        sourceUrl: article.url,
        sourceCredit: article.source,
      });
```

- [ ] **Step 8: Thay 2 chỗ xoá**

`delete_insight_` (~dòng 1115): `await sanity.delete(id)` → `await wp.deleteById(id, "match_insight");`
`delete_post_` (~dòng 1128):    `await sanity.delete(id)` → `await wp.deleteById(id, "posts");`

- [ ] **Step 9: Thay cron dọn dẹp 07:55**

WP REST không query được meta tuỳ ý, nên lấy hết insight rồi lọc trong JS (số lượng luôn nhỏ, < 50):

```js
cron.schedule(
  "55 7 * * *",
  async () => {
    console.log("🧹 Cron: Dọn dẹp matchInsight cũ...");
    try {
      // Xoá insight có matchDate đã qua hơn 3 tiếng (trận chắc chắn kết thúc)
      const cutoffMs = Date.now() - 3 * 60 * 60 * 1000;
      const all = await wp.listInsights(100);
      const stale = all.filter(
        (i) => i.matchDate && new Date(i.matchDate).getTime() < cutoffMs
      );

      if (!stale.length) {
        console.log("✅ Không có insight nào cần xóa.");
        return;
      }

      for (const item of stale) {
        await wp.deleteById(item.id, "match_insight");
      }

      const list = stale.map((i) => `• ${i.homeTeam} vs ${i.awayTeam}`).join("\n");
      console.log(`🧹 Đã xóa ${stale.length} insight cũ.`);
      if (OWNER_CHAT_ID) {
        await bot.telegram.sendMessage(
          OWNER_CHAT_ID,
          `🧹 *Đã dọn ${stale.length} insight quá hạn:*\n${list}`,
          { parse_mode: "Markdown" }
        );
      }
    } catch (e) {
      console.error("❌ Lỗi dọn dẹp:", e.message);
    }
  },
  { timezone: "Asia/Ho_Chi_Minh" }
);
```

- [ ] **Step 10: Kiểm chứng không còn dấu vết Sanity**

Run:
```bash
cd web
grep -n "sanity\|Sanity\|SANITY\|PortableText\|portableText" bot-press.js || echo "✅ Sạch Sanity"
node --check bot-press.js && echo "✅ Cú pháp hợp lệ"
```
Expected: `✅ Sạch Sanity` và `✅ Cú pháp hợp lệ`

- [ ] **Step 11: Chạy bot thật, kiểm chứng end-to-end**

```bash
cd web && node bot-press.js
```
Expected log:
```
✅ Đã tải 9 danh mục: uncategorized, ngoai-hang-anh, ...
```

Trong Telegram, chạy lần lượt và xác nhận **từng cái**:

| Test | Cách làm | Kỳ vọng |
|---|---|---|
| Insight thủ công | Gửi `INSIGHT Arsenal vs Chelsea, 21:00 ngày 25/07` + 2 dòng thống kê + `Dự đoán: Arsenal thắng 2-1` | Preview → bấm Đăng → insight xuất hiện trên carousel trang chủ |
| Bài viết thủ công | Gửi 1 đoạn tin có ảnh | Bài đăng, có featured image, xem được ở `/{category}/{slug}` |
| `/posts` | Gõ `/posts` | Liệt kê bài, tên danh mục đúng (không phải `—`) |
| `/list` | Gõ `/list` | Liệt kê insight, bấm 🗑 xoá được |
| Bài nhận định | `/preview` → bấm `📝 Tạo bài nhận định` → Đăng | Bài có ảnh preview Puppeteer làm featured image |
| RSS | `/fetchnews` | Bài RSS đăng được, cuối bài có link nguồn |

Dừng bot bằng `Ctrl+C` sau khi xong.

- [ ] **Step 12: Commit**

```bash
git add web/bot-press.js
git commit -m "feat(bot): chuyển bot-press.js từ Sanity sang WordPress REST API"
```

---

## Task 8: Dọn dẹp Astro + cập nhật tài liệu

**Files:**
- Delete: `web/src/`, `web/astro.config.mjs`, `web/tailwind.config.mjs`, `web/postcss.config.mjs`, `web/tsconfig.json`, `web/create-categories.js`
- Modify: `web/package.json`
- Modify: `CLAUDE.md`
- Modify: memory files trong `.claude/projects/.../memory/`

**Interfaces:**
- Consumes: xác nhận Task 7 đã verify end-to-end
- Produces: repo sạch, tài liệu khớp thực tế

**Chỉ chạy task này SAU KHI Task 7 Step 11 đã pass hết.** Xoá trước khi verify = mất đường lùi.

---

- [ ] **Step 1: Xoá code Astro**

```bash
git rm -r web/src
git rm web/astro.config.mjs web/tailwind.config.mjs web/postcss.config.mjs web/tsconfig.json web/create-categories.js
git rm web/test-preview-image.js 2>/dev/null || true
```

- [ ] **Step 2: Gỡ dependency không dùng**

```bash
cd web
npm uninstall astro @astrojs/react @astrojs/tailwind react react-dom \
  @sanity/client @sanity/image-url @tanstack/react-query swiper \
  tailwindcss @tailwindcss/vite
```

Giữ lại: `telegraf`, `@google/generative-ai`, `axios`, `node-cron`, `rss-parser`, `puppeteer`, `dotenv`.

Xoá script `dev` / `build` / `preview` của Astro trong `web/package.json`; giữ `start`, `test`, `seed`.

- [ ] **Step 3: Kiểm chứng bot vẫn chạy sau khi gỡ dep**

Run:
```bash
cd web && node --check bot-press.js && node -e "import('./lib/wp.js').then(() => console.log('✅ wp.js nạp được'))"
npm test
```
Expected: `✅ wp.js nạp được`, test `# fail 0`

Nếu bot văng `Cannot find module` → gỡ nhầm dep, cài lại đúng gói đó.

- [ ] **Step 4: Cập nhật `CLAUDE.md`**

Theo quy tắc bắt buộc trong `CLAUDE.md`. Cụ thể:
- **Tổng quan dự án:** `web/` giờ là bot Node, không còn frontend Astro. Thêm `wp/` là WordPress theme + mu-plugin.
- **Tech Stack:** bỏ bảng Frontend Astro/React/Sanity; thêm bảng WordPress (WP 7.0.1, PHP 8.2, Tailwind v4, Swiper 12 vendored).
- **Cấu trúc thư mục:** thay cây `web/src/` bằng cây `wp/`.
- **Routes & API:** bỏ `/api/live-matches`; thêm `/{category}/{slug}/`, `/{category}/`.
- **Tích hợp bên ngoài:** bỏ dòng Sanity.io; thêm WordPress REST API.
- **Environment Variables:** bỏ `SANITY_*` và `PUBLIC_SANITY_*`; thêm `WP_URL`, `WP_USER`, `WP_APP_PASSWORD`.
- **Schemas Sanity** → đổi tiêu đề thành **Data model WordPress**, mô tả CPT `match_insight` + post meta.
- **Chạy dự án:** bỏ lệnh Astro; thêm `./wp/bin/wp`, `npm run seed`, build theme.
- **Deployment:** thêm mục WordPress hosting; Railway chỉ còn env WP.

- [ ] **Step 5: Cập nhật memory**

Sửa `.claude/projects/-Users-hotienphong-Desktop-Personal-Bongda247/memory/`:
- `project_overview.md` — stack mới (WordPress thay Astro+Sanity), cấu trúc `wp/`
- `bot_architecture.md` — `bot-press.js` ghi qua `lib/wp.js` → WP REST; 6 luồng giữ nguyên
- `deployment.md` — env `WP_URL`/`WP_USER`/`WP_APP_PASSWORD` thay `SANITY_*`

- [ ] **Step 6: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add -A
git commit -m "chore: gỡ Astro + Sanity, cập nhật CLAUDE.md và memory"
```

---

## Ghi chú deploy production (ngoài phạm vi plan)

Khi lên hosting thật:
1. Upload `wp/themes/bongda247/` (kèm `dist/` và `assets/vendor/` đã build sẵn) + `wp/mu-plugins/`
2. Cài RankMath, đặt permalink `/%category%/%postname%/`
3. Tạo user `bot` role editor + Application Password
4. Railway: đổi `WP_URL` sang domain thật, bỏ `SANITY_*`
