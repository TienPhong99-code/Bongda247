# Chuyển Bongda247 từ Astro + Sanity sang WordPress

**Ngày:** 2026-07-13
**Trạng thái:** Design đã chốt, chờ implementation plan

---

## 1. Mục tiêu

Thay thế frontend Astro (`web/src/`) và CMS Sanity bằng một site WordPress tự host, giữ nguyên con bot Telegram + AI đang chạy trên Railway.

**Lý do chuyển (theo thứ tự user nêu):**
- Quản trị nội dung dễ hơn (admin WP quen thuộc, editor WYSIWYG)
- Hệ sinh thái plugin SEO (RankMath/Yoast, schema, sitemap)
- Hosting PHP rẻ / có sẵn
- User quen WordPress hơn Astro

**Không phải mục tiêu:** headless WordPress. Site sẽ là WordPress classic theme render PHP.

---

## 2. Quyết định đã chốt

| Vấn đề | Quyết định | Lý do |
|---|---|---|
| Kiến trúc frontend | WordPress classic theme (PHP + Tailwind v4) | Cả 4 động cơ của user đều đòi WP "thật", không phải headless |
| Con bot `bot-press.js` | Giữ nguyên Node trên Railway, đổi lớp ghi Sanity → WP REST API | Gemini, RSS, Puppeteer, Telegraf đều là Node. Viết lại bằng PHP = rất tốn công, không lợi gì |
| Dữ liệu Sanity cũ | **Bỏ hết**, không migrate | User chọn làm lại từ đầu |
| Giao diện | Port 1:1 giao diện Astro hiện tại | Giữ brand `#0232ff`, font Oswald/SVN-Hemi, dark/light |
| Tính năng đang comment out | **Bỏ** (LiveScoresTicker, LeagueTabs, NewsSection, `/lich-thi-dau`, `/bang-xep-hang`) | YAGNI — chưa từng chạy trên production |
| Môi trường dev | Local by Flywheel — site `bongda247.local` đã dựng (PHP 8.2.29, MySQL 8.4, WP 7.0.1, nginx) | User tự cài |
| Vị trí code | Theme/plugin sống trong repo `Bongda247/wp/`, symlink sang Local | Git-track được, deploy = rsync thư mục |
| Xác thực bot → WP | Application Password (WP core, HTTPS Basic Auth) | Không cần plugin JWT |
| Auto-cleanup insight hết hạn | Giữ ở bot (cron 07:55 → gọi DELETE REST) | WP-Cron chỉ chạy khi có traffic, không đáng tin |
| Tailwind | Build bằng CLI ra `dist/main.css` | Site hướng SEO, không dùng Tailwind CDN trên production |

---

## 3. Phạm vi thực tế (đã khảo sát code)

Đọc `web/src/` cho thấy site đang chạy **nhỏ hơn** cấu trúc file gợi ý:

**Đang chạy:**
- `Header.astro` — logo, 3 nav link, ThemeToggle
- `index.astro` — HotNewsSlider (5 bài mới) + SidebarSlider (10 bài) + MatchInsights (carousel 15 insight)
- `[category]/[slug].astro` — trang bài viết

**Code chết (comment out):**
- `LiveScoresTicker.jsx`, `LeagueTabs.jsx` — bị comment trong `Header.astro`
- `NewsSection.astro` — bị comment trong `index.astro`
- `api/live-matches.ts` — không component nào gọi

**Đang 404 (nav link trỏ tới trang không tồn tại):**
- `/nhan-dinh`, `/lich-thi-dau`, `/bang-xep-hang`
- Mọi link category (`/ngoai-hang-anh`, ...) — vì chỉ có `[category]/[slug]`, không có `[category]/index`

→ WordPress cho không trang category archive. Nav sẽ bỏ `/lich-thi-dau` và `/bang-xep-hang`.

---

## 4. Kiến trúc

```
┌─────────────────┐   Telegram    ┌──────────────────┐
│  bot-press.js   │ ◄───────────► │   Owner          │
│  (Railway,Node) │               └──────────────────┘
│  Gemini + RSS   │
│  + Puppeteer    │  WP REST API (Application Password)
└────────┬────────┘
         │  POST /wp-json/wp/v2/media
         │  POST /wp-json/wp/v2/posts
         │  POST /wp-json/wp/v2/match_insight
         │  DELETE /wp-json/wp/v2/{type}/{id}?force=true
         ▼
┌──────────────────────────────────────────┐
│  WordPress (Local → hosting PHP)         │
│  • theme bongda247 (PHP + Tailwind)      │
│  • mu-plugin: CPT match_insight + meta   │
│  • RankMath (SEO)                        │
└──────────────────────────────────────────┘
```

---

## 5. Data model

| Sanity | WordPress |
|---|---|
| `post` | Post gốc — `title`, `content` (HTML), `excerpt`, featured image |
| `category` (reference) | Category taxonomy gốc — giữ nguyên slug |
| `hashtags[]` | Tag taxonomy gốc |
| `matchInsight` | CPT `match_insight` |
| `sourceUrl`, `sourceCredit` | post meta trên `post` |

**Category cần tạo** (từ `web/create-categories.js`):
`ngoai-hang-anh`, `champions-league`, `la-liga`, `bundesliga`, `serie-a`, `ligue-1`, `chuyen-nhuong`, `ngoai-san-co`

**CPT `match_insight`** — `public: true`, `show_in_rest: true`, `rest_base: match_insight`, supports `title`.

Post meta (tất cả đều `show_in_rest: true`, `single: true` — kể cả `insights`, xem lưu ý bên dưới):

| Meta key | Type | Ghi chú |
|---|---|---|
| `home_team` | string | |
| `away_team` | string | |
| `match_time` | string | Format `"HH:mm - DD/MM"` — hiển thị |
| `match_date` | string (ISO datetime) | UTC thật; **rỗng** với insight thủ công |
| `hot` | boolean | Trận nổi bật |
| `insights` | array of string | `show_in_rest` cần khai `schema.items.type = string` |
| `prediction` | string | |

**Lưu ý REST + meta array:** WP REST chỉ chấp nhận meta kiểu array khi khai báo `type => 'array'` kèm `show_in_rest => ['schema' => ['items' => ['type' => 'string']]]` và `single => true`. Nếu không khai schema, REST sẽ từ chối ghi.

---

## 6. Theme `bongda247`

```
wp/
├── themes/bongda247/
│   ├── style.css                    # theme header
│   ├── functions.php                # enqueue, theme supports, nav menu, image sizes
│   ├── header.php                   # port Header.astro
│   ├── footer.php
│   ├── front-page.php               # HotNews + Sidebar + MatchInsights
│   ├── single.php                   # port [category]/[slug].astro
│   ├── archive.php                  # category archive (MỚI)
│   ├── index.php                    # fallback
│   ├── 404.php
│   ├── template-parts/
│   │   ├── hot-news-slider.php
│   │   ├── sidebar-slider.php
│   │   ├── match-insights.php
│   │   └── theme-toggle.php
│   ├── src/
│   │   ├── main.css                 # Tailwind v4 + design tokens
│   │   └── main.js                  # Swiper init (hot/sidebar/insight)
│   ├── dist/                        # build output — COMMIT vào git, để deploy lên hosting không cần chạy Node
│   ├── assets/
│   │   ├── fonts/                   # SVN-HemiHead, Oswald, Inter
│   │   └── images/flame.png
│   └── package.json                 # tailwindcss + swiper, npm run build/watch
└── mu-plugins/
    └── bongda247-core.php           # CPT + meta + REST
```

Symlink:
```
~/Local Sites/bongda247/app/public/wp-content/themes/bongda247   → <repo>/wp/themes/bongda247
~/Local Sites/bongda247/app/public/wp-content/mu-plugins         → <repo>/wp/mu-plugins
```

### Map query

| Astro (GROQ) | WordPress |
|---|---|
| `*[_type=="post"] \| order(publishedAt desc)[0...5]` | `WP_Query(['posts_per_page'=>5])` |
| `*[_type=="post"] \| order(publishedAt desc)[0...10]` | `WP_Query(['posts_per_page'=>10])` |
| `urlFor(mainImage).width(1200)` | `the_post_thumbnail('large')` — WP tự sinh srcset |
| `*[_type=="matchInsight"] \| order(hot desc, publishedAt desc)[0...15]` | `WP_Query(['post_type'=>'match_insight','posts_per_page'=>15,'meta_key'=>'hot','orderby'=>['meta_value_num'=>'DESC','date'=>'DESC']])` |
| `PortableText` component | Bỏ — content lưu thẳng HTML |

### Lọc insight quá hạn (giữ nguyên logic Astro)

`MatchInsights.astro` lọc theo `match_time` chuỗi `"HH:mm - DD/MM"`: bỏ item có `month < currentMonth`, hoặc cùng tháng và `day < currentDay`. Port nguyên si sang PHP trong `match-insights.php`. (Logic này sai qua giao thừa năm — chấp nhận, giữ nguyên hành vi hiện tại; bot đã có cron 07:55 xoá insight cũ nên vấn đề không tích tụ.)

### Dark/light

Giữ cơ chế `localStorage` + class trên `<html>`. Script khởi tạo inline trong `<head>` (trước CSS) để tránh nháy màu.

### Design tokens (giữ nguyên)

- brand `#0232ff`, accent `#dc2626`
- bg light `#f3f3f3` / dark `#0e1217`
- card light `#f5f8fc` / dark `#1c1f26`
- Font: Inter (body), Oswald (heading), SVN-Hemi Head (branding)

---

## 7. Bot adapter `web/lib/wp.js`

Module mới, cùng "hình dạng" với Sanity client để `bot-press.js` sửa tối thiểu (~20 call site).

```js
uploadMedia(buffer, filename, mime) → POST /wp-json/wp/v2/media          → mediaId
createPost({ title, html, excerpt, categorySlug, tags, featuredMedia,
             sourceUrl, sourceCredit })
                                    → POST /wp-json/wp/v2/posts          → { id, link }
createInsight({ homeTeam, awayTeam, matchTime, matchDate, hot,
                insights, prediction })
                                    → POST /wp-json/wp/v2/match_insight  → { id }
listPosts(n)                        → GET  /wp-json/wp/v2/posts?per_page=n
listInsights(n)                     → GET  /wp-json/wp/v2/match_insight?per_page=n
deleteById(id, type)                → DELETE /wp-json/wp/v2/{type}/{id}?force=true
fetchCategories()                   → GET  /wp-json/wp/v2/categories?per_page=100 → Map slug → id
```

### Thay đổi trong `bot-press.js`

| Hiện tại | Sau |
|---|---|
| `import { createClient } from "@sanity/client"` | `import * as wp from "./lib/wp.js"` |
| `sanity.assets.upload("image", buffer)` → `asset._id` | `wp.uploadMedia(buffer, name)` → `mediaId` |
| `sanity.create({_type:"post", ...})` | `wp.createPost({...})` |
| `sanity.create({_type:"matchInsight", ...})` | `wp.createInsight({...})` |
| `sanity.delete(id)` | `wp.deleteById(id, type)` |
| `sanity.fetch(GROQ categories)` | `wp.fetchCategories()` |
| `toPortableText(sections, images)` | `toHtml(sections, images)` — nối `<h2>`/`<p>`/`<figure><img>` |

**Không đụng tới:** `matchPreviewImage.js` (Puppeteer), Gemini prompts, RSS pipeline, tất cả luồng Telegram (1–6), cron 8:00 / 07:55 / 7h-13h-20h, `processedUrls`, `draftStore`.

`CATEGORIES` map hiện là `slug → {id, title}` — WP `fetchCategories()` trả cùng hình dạng, nên `/reload` và logic chọn category theo slug giữ nguyên.

### Env vars

```diff
- SANITY_PROJECT_ID
- SANITY_DATASET
- SANITY_API_TOKEN
+ WP_URL=https://bongda247.local        # local; production đổi thành domain thật
+ WP_USER=bot
+ WP_APP_PASSWORD=xxxx xxxx xxxx xxxx
```

Giữ nguyên: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_OWNER_CHAT_ID`, `GEMINI_API_KEY`, `PUBLIC_FOOTBALL_DATA_KEY`.

**Local + HTTPS:** cert `bongda247.local.crt` là self-signed. Khi test bot chạy local trỏ vào Local site, cần `NODE_TLS_REJECT_UNAUTHORIZED=0` (chỉ dev) hoặc dùng `http://bongda247.local`. Không bao giờ đặt cờ này trên Railway.

---

## 8. Dọn dẹp

Sau khi WP chạy được, xoá khỏi repo:
- `web/src/` (toàn bộ Astro app)
- `web/astro.config.mjs`, `web/tailwind.config.mjs`, `web/postcss.config.mjs`, `web/tsconfig.json`
- `web/create-categories.js` (thay bằng script tạo category qua WP REST, hoặc tạo tay trong admin)
- Dependency Astro/React/Sanity/Swiper trong `web/package.json`

Giữ lại trong `web/`: `bot-press.js`, `matchPreviewImage.js`, `lib/wp.js`, `public/font`, `public/logo` (Puppeteer cần), `nixpacks.toml`, `railway.json`.

**Thứ tự an toàn:** chỉ xoá sau khi bot + theme đã verify end-to-end. Xoá là commit riêng.

---

## 9. Verification

Không claim xong nếu chưa chạy thật. Trên `bongda247.local`:

1. Theme active, trang chủ render — HotNewsSlider, SidebarSlider, MatchInsights đều có dữ liệu
2. Bot chạy local → gửi tin nhắn Telegram → duyệt → **bài xuất hiện thật** trên WP với ảnh featured
3. Luồng 6 (bài nhận định) → ảnh preview Puppeteer upload thành công làm featured image
4. Luồng 5 (RSS) → `/fetchnews` → bài đăng được
5. Insight → hiện đúng trên carousel trang chủ, đúng thứ tự `hot` trước
6. `/list`, `/posts` → liệt kê và xoá được
7. Trang bài viết `/{category}/{slug}` render đúng: breadcrumb, ảnh, content, tags
8. Trang category archive không còn 404
9. Dark/light toggle không nháy màu khi reload
10. Responsive mobile

---

## 10. Deploy production (ngoài phạm vi implement, ghi để nhớ)

- WP: bất kỳ hosting PHP 8.1+ / MySQL. Upload theme + mu-plugin, cài RankMath, tạo user `bot` + Application Password.
- Bot: Railway giữ nguyên, chỉ đổi env `WP_URL` / `WP_USER` / `WP_APP_PASSWORD`, bỏ `SANITY_*`.
- Permalink: `/%category%/%postname%/` để khớp URL cũ.
