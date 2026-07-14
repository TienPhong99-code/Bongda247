# CLAUDE.md — Bongda247 Project

## Quy tắc bắt buộc

Sau mỗi thay đổi đáng kể (thêm tính năng, đổi API, đổi cấu trúc, đổi env vars, đổi luồng xử lý):
1. **Cập nhật CLAUDE.md** — phần liên quan (tích hợp, env vars, luồng hoạt động, v.v.)
2. **Cập nhật memory** — các file trong `.claude/projects/.../memory/` tương ứng (bot_architecture.md, deployment.md, v.v.)

Không cần người dùng nhắc — tự động làm sau khi hoàn thành task.

---

## Tổng quan dự án

Website tin tức và phân tích bóng đá bằng AI, viết bằng **tiếng Việt**, tập trung vào các giải đấu lớn (Ngoại hạng Anh, Champions League, La Liga, Bundesliga, Serie A, Ligue 1).

Dự án gồm **2 phần chính**:
- `wp/` — WordPress theme + mu-plugin (frontend website)
- `web/` — Bot Node.js (`bot-press.js`) tự động tạo + đăng bài lên WordPress

---

## Tech Stack

### Bot (`web/`)
| Công nghệ | Version | Mục đích |
|-----------|---------|----------|
| Node.js | 20 | Runtime |
| Telegraf | 4.16.3 | Telegram bot framework |
| @google/generative-ai | 0.2.1 | AI (Google Gemini 2.5 Flash) |
| Axios | 1.13.6 | HTTP client (gọi WP REST + external APIs) |
| rss-parser | latest | Parse RSS feeds |
| node-cron | latest | Cron jobs (auto preview, RSS, cleanup) |
| puppeteer-core | latest | Tạo ảnh preview trận đấu |
| dotenv | latest | Env vars |

### WordPress (`wp/`)
| Công nghệ | Version | Mục đích |
|-----------|---------|----------|
| WordPress | 7.0.1 | CMS + REST API |
| PHP | 8.2 | Server-side rendering |
| Tailwind CSS | v4 | Styling (build trong theme) |
| Swiper | 12 (vendored) | Carousel / slider |

---

## Cấu trúc thư mục

### Bot (`web/`)
```
web/
├── bot-press.js            # Telegram bot + AI automation (entry point)
├── matchPreviewImage.js    # Tạo ảnh preview trận đấu 1200×630px
├── lib/
│   └── wp.js               # Adapter ghi/đọc WordPress REST API
├── scripts/
│   └── seed-wp.mjs         # Seed categories lên WordPress
├── test/                   # Test suite cho wp.js
├── public/
│   ├── font/SVN-Hemi/      # Font SVN-HemiHead.woff2 (dùng cho preview image)
│   └── logo/               # logo-svg.svg (dùng cho preview image)
└── package.json
```

### WordPress (`wp/`)
```
wp/
├── themes/bongda247/
│   ├── style.css           # Theme header
│   ├── functions.php       # Enqueue scripts, theme setup
│   ├── front-page.php      # Trang chủ (dải trận đấu nổi bật · hot slider · insights · lưới tin theo giải · chuyển nhượng + widget số liệu)
│   ├── single.php          # Bài viết đơn
│   ├── archive.php         # Archive category/giải đấu
│   ├── search.php          # Trang kết quả tìm kiếm
│   ├── page-bang-xep-hang.php # Trang BXH đầy đủ (?league=slug)
│   ├── page-lich-thi-dau.php  # Trang lịch + kết quả đầy đủ (?league=slug)
│   ├── page.php            # Trang tĩnh mặc định
│   ├── 404.php             # Trang 404
│   ├── header.php          # Header + nav + search-toggle + mobile menu
│   ├── footer.php          # Footer
│   ├── inc/
│   │   ├── query.php       # WP_Query helpers (bd_category_posts, bd_hero, ...)
│   │   └── football-data.php # Data layer football-data.org (standings/fixtures, cache stale-while-revalidate)
│   ├── template-parts/
│   │   ├── hot-news-slider.php    # Carousel tin hot
│   │   ├── match-insights.php     # Carousel nhận định trận đấu
│   │   ├── sidebar-slider.php     # Sidebar carousel
│   │   ├── featured-matches.php   # Dải trận đấu nổi bật đầu trang (gộp lịch+KQ 5 giải, ẩn khi rỗng)
│   │   ├── category-column.php    # 1 cột lưới "TIN THEO GIẢI ĐẤU"
│   │   ├── transfer-list.php      # Cột Chuyển nhượng (lead + list) trang chủ
│   │   ├── fd-widget.php          # Khung widget số liệu (dropdown giải + body, AJAX)
│   │   ├── fd-widget-body.php     # Phần đổi theo giải: 3 tab BXH/Lịch/KQ (AJAX render lại)
│   │   ├── standings-table.php    # Bảng BXH đầy đủ (trang BXH)
│   │   ├── fixtures-list.php      # Danh sách lịch/kết quả (trang lịch)
│   │   └── theme-toggle.php       # Dark/Light mode toggle
│   ├── src/
│   │   ├── main.css        # Tailwind source
│   │   └── main.js         # JS source
│   └── dist/               # Build output (main.css, main.js)
├── mu-plugins/
│   └── bongda247-core.php  # Custom Post Type: match_insight + meta fields
└── bin/
    └── wp                  # WP-CLI wrapper
```

### File đặc biệt
- `web/bot-press.js` — Telegram bot + AI tự động tạo bài đăng lên WordPress
- `web/matchPreviewImage.js` — Tạo ảnh preview trận đấu 1200×630px (JPEG) bằng Puppeteer + HTML template

---

## Routes & API

### WordPress Routes
- `/` — Trang chủ (dải trận đấu nổi bật · hot news slider · match insights · lưới tin theo giải · section Chuyển nhượng + widget số liệu)
- `/{category}/{slug}/` — Bài viết đơn (VD: `/ngoai-hang-anh/ten-bai-viet/`)
- `/{category}/` — Archive danh mục / giải đấu
- `/?s=từ+khóa` — Kết quả tìm kiếm
- `/bang-xep-hang/?league={slug}` — BXH đầy đủ 1 giải
- `/lich-thi-dau/?league={slug}` — Lịch + kết quả đầy đủ 1 giải

### admin-ajax (theme)
- `GET /wp-admin/admin-ajax.php?action=bd_fd_widget&league={slug}` — render lại body widget số liệu (BXH/Lịch/KQ) cho 1 giải; input `league` validate qua `BD_FD_LEAGUES` (sai → default `ngoai-hang-anh`). Dùng cho dropdown đổi giải trên trang chủ (không reload).

### WordPress REST API (bot ghi qua `lib/wp.js`)
- `POST /wp-json/wp/v2/posts` — Tạo bài viết
- `POST /wp-json/wp/v2/match_insight` — Tạo nhận định trận
- `POST /wp-json/wp/v2/media` — Upload ảnh
- `GET /wp-json/wp/v2/categories` — Lấy danh mục
- `DELETE /wp-json/wp/v2/{type}/{id}?force=true` — Xóa

---

## Tích hợp bên ngoài

| Service | Mục đích |
|---------|----------|
| WordPress REST API | CMS — Lưu bài viết, danh mục, nhận định trận |
| Football-Data.org (football-data.org) | Fixtures + BXH — free plan: 10 req/min, per-competition endpoint. Dùng bởi **bot** (env `PUBLIC_FOOTBALL_DATA_KEY`) VÀ **theme** (`inc/football-data.php`, đọc hằng wp-config `FOOTBALL_DATA_KEY`, cache stale-while-revalidate) cho widget số liệu + **dải trận đấu nổi bật** (`bd_fd_featured_matches()` gộp fixtures 5 giải, tái dùng cache) trang chủ + trang BXH/lịch |
| Google Gemini 2.5 Flash | AI tạo phân tích, nhận định, viết lại bài RSS |
| Telegram Bot | Phân phối nội dung tự động + kiểm duyệt |
| RSS Feeds | Sky Sports, BBC Sport, Bóng Đá Plus — nguồn tin tức tự động |
| TheSportsDB (free key `3`) | Logo đội, màu sắc, cutout cầu thủ, fanart sân vận động — dùng cho preview image |
| Google AdSense | Quảng cáo |

**WordPress config**: `WP_URL` (VD: `http://bongda247.local`), user `bot` role editor + Application Password

**Theme football-data**: đặt hằng trong `wp-config.php` — `define('FOOTBALL_DATA_KEY', '...')` (dùng `./wp/bin/wp config set FOOTBALL_DATA_KEY "<key>" --type=constant`). Thiếu key → `bd_fd_standings`/`bd_fd_fixtures` trả `[]`, widget hiện "Chưa có dữ liệu". `BD_FD_LEAGUES` map 5 slug→code: `ngoai-hang-anh`→PL, `la-liga`→PD, `bundesliga`→BL1, `serie-a`→SA, `ligue-1`→FL1 (không có Champions League vì free plan không hỗ trợ bảng nhóm).

---

## Environment Variables

### Bot (`web/.env`)
```
WP_URL=http://bongda247.local       # URL WordPress (không có dấu / cuối)
WP_USER=bot                         # WordPress username
WP_APP_PASSWORD=xxxx xxxx xxxx      # Application Password tạo trong WP
GEMINI_API_KEY=                     # Google Gemini API key
TELEGRAM_BOT_TOKEN=                 # Telegram bot token
TELEGRAM_OWNER_CHAT_ID=2050679271
PUBLIC_FOOTBALL_DATA_KEY=           # football-data.org API key
```

---

## Thiết kế & Giao diện

### Màu sắc
- Brand primary: `#0232ff` (xanh dương)
- Accent: `#dc2626` (đỏ)
- Background (light): `#f3f3f3` | (dark): `#0e1217`
- Card (light): `#f5f8fc` | (dark): `#1c1f26`

### Font chữ
- **Inter** — body text
- **Oswald** — headings
- **SVN-Hemi Head** — branding/titles

### Theme
- Dark/Light mode toggle, lưu vào `localStorage`
- Mobile-first responsive design

---

## Chạy dự án

### Bot
```bash
cd web
npm start        # Chạy bot (node bot-press.js)
npm test         # Chạy test suite wp.js
npm run seed     # Seed categories lên WordPress
```

### WordPress Theme (build CSS/JS)
```bash
cd wp/themes/bongda247
npm run build    # Build Tailwind CSS + JS → dist/
```

### WP-CLI
```bash
wp/bin/wp <command>   # WP-CLI wrapper local
```

---

## Data model WordPress

### Post (WP built-in)
- `title`, `slug`, `categories` (term IDs), `excerpt`
- `featured_media` (attachment ID), `content` (HTML)
- `status: "publish"`, meta: `source_url`, `source_credit`

### Category (WP built-in term)
- `name` (tên giải: "Ngoại hạng Anh", "Champions League"...)
- `slug`, `description`

### match_insight (Custom Post Type — `mu-plugins/bongda247-core.php`)
- `home_team`, `away_team`, `match_time` (string "HH:mm - DD/MM")
- `match_date` (ISO UTC datetime) — dùng để auto-delete sau trận
- `hot` (int 0/1), `insights` (array string), `prediction` (string)

---

## Luồng hoạt động AI (`bot-press.js`)

### Khởi động
1. Tải toàn bộ danh mục từ WordPress qua REST API → lưu vào `CATEGORIES` map (slug → `{id, title}`)
2. Không hardcode ID — dùng `/reload` để cập nhật danh mục mới bất kỳ lúc nào

### Luồng 1 — INSIGHT thủ công
**Trigger:** tin nhắn chứa từ `INSIGHT`

1. Gemini trích xuất JSON: `homeTeam`, `awayTeam`, `matchTime`, `hot`, `insights[]`, `prediction`
2. Bot hiển thị preview với nút: **Đổi HOT** / **Đăng lên Slide** / **Hủy**
3. Xác nhận → tạo `match_insight` trên WordPress (lưu cả `matchDate = null`)

### Luồng 2 — BÀI VIẾT thủ công
**Trigger:** text hoặc ảnh+caption không chứa INSIGHT

1. AI tự nhận diện giải đấu → field `league` khớp slug WordPress category
2. Gemini viết JSON: `title`, `excerpt`, `league`, `sections[]`
3. Xác nhận → upload ảnh lên WP Media Library → tạo `post`

### Luồng 3 — ALBUM ẢNH
**Trigger:** gửi nhiều ảnh cùng lúc (media group)

- `setTimeout(1500ms)` đợi đủ ảnh → tránh race condition
- Sau đó vào Luồng 1 hoặc 2 tuỳ caption

### Luồng 4 — DAILY AUTO PREVIEW (tự động)
**Trigger:** Cron 8:00 sáng giờ Việt Nam (chạy trên Railway)

1. Gọi football-data.org per-competition: `GET /v4/competitions/{code}/matches?dateFrom=&dateTo=`
2. Lọc giải theo `LEAGUE_MAP` (hiện chỉ PL, các giải khác comment out) — tối đa 3 trận/giải
3. Gọi `GET /v4/competitions/{code}/standings` lấy BXH thực tế từng giải
4. Gemini tạo nhận định dựa trên BXH (hạng, điểm, W/D/L, form)
5. Gửi từng trận về Telegram (chat ID owner) với nút:
   - `🔄 Đổi HOT` — toggle, chưa đăng
   - `✅ Đăng lên Slide` → tạo `match_insight` trên WordPress (lưu `matchDate` = giờ UTC thực tế)
   - `📝 Tạo bài nhận định` → kích hoạt Luồng 6
   - `⏭ Bỏ qua`

**Data thật từ API:** hạng BXH, điểm, W/D/L, form
**Data AI tạo:** nhận định chiến thuật, dự đoán tỉ số

### Luồng 6 — BÀI NHẬN ĐỊNH ĐẦY ĐỦ (on-demand)
**Trigger:** Bấm nút `📝 Tạo bài nhận định` trên card matchInsight

1. Lấy thông tin từ `draftStore`: homeTeam, awayTeam, matchTime, leagueCode, matchDate
2. Gọi `generateMatchArticle()` — Gemini viết bài 6 sections ~1000 từ:
   - Section 1: Bối cảnh trận đấu
   - Section 2: Phong độ đội nhà (dựa trên BXH thực tế)
   - Section 3: Phong độ đội khách (dựa trên BXH thực tế)
   - Section 4: Lịch sử đối đầu (Gemini knowledge)
   - Section 5: Lực lượng & Đội hình dự kiến (Gemini knowledge)
   - Section 6: Nhận định & Dự đoán tỉ số
3. Gửi preview Telegram: title, excerpt, dự đoán với nút `✅ Đăng bài nhận định` / `⏭ Bỏ qua`
4. Duyệt → `generateMatchPreviewImage()` tạo ảnh JPEG 1200×630px → upload WordPress Media Library làm featured image

**Lưu ý:** H2H, lực lượng, đội hình dự kiến do Gemini tự điền từ kiến thức training — chính xác với các đội lớn PL, có thể không cập nhật diễn biến mới nhất

**Giới hạn free plan football-data.org:**
- Chỉ có Fixtures + League Tables (không có H2H, team fixtures)
- Phải fetch per-competition (không có global `/matches` endpoint)
- 10 req/min → delay 7s giữa mỗi request
- timeout 15s (Railway cần nhiều hơn 10s)

### Luồng 5 — RSS NEWS PIPELINE (tự động)
**Trigger:** Cron 7h, 13h, 20h mỗi ngày

1. `fetchRSSFeeds()` — fetch 4 nguồn: Sky Sports PL, Sky Sports Football, BBC Sport, Bóng Đá Plus
2. `filterAndRankArticles()` — lọc bài mới (< 6h), có từ khóa liên quan, loại URL đã xử lý
3. Chấm điểm → lấy top 2 bài mỗi lần chạy
4. `extractOgImage()` — scrape og:image nếu RSS không có ảnh
5. `generateNewsPost()` — Gemini viết lại hoàn toàn tiếng Việt, chuẩn SEO (KHÔNG dịch thẳng)
6. Gửi Telegram preview với ảnh: **✅ Đăng bài** / **⏭ Bỏ qua**
7. Duyệt → upload ảnh lên WP Media Library → tạo `post` với `source_url` + `source_credit`
8. `processedUrls` Set reset lúc 0h mỗi ngày

**RSS Sources:**
- Sky Sports PL: `https://www.skysports.com/rss/12040`
- Sky Sports Football: `https://www.skysports.com/rss/12006`
- BBC Sport: `https://feeds.bbci.co.uk/sport/football/rss.xml`
- Bóng Đá Plus: `https://bongdaplus.vn/rss/tin-tuc.rss`

### Auto Cleanup
- **07:55 sáng** — xóa tự động tất cả `match_insight` có `match_date < (now - 3h)` qua WP REST API
- Insight thủ công (không có `match_date`) → xóa tay qua `/list`
- Sau khi xóa → gửi thông báo về Telegram

### Lệnh quản lý
| Lệnh | Chức năng |
|------|-----------|
| `/preview` | Nhận định trận hôm nay (kích hoạt thủ công) |
| `/tomorrow` | Nhận định trận ngày mai |
| `/fetchnews` | Fetch tin tức mới từ RSS (kích hoạt thủ công) |
| `/list` | Xem & xóa 10 insight đang hiển thị |
| `/posts` | Xem & xóa 8 bài viết gần nhất |
| `/reload` | Tải lại danh mục từ WordPress |

### Ví dụ tin nhắn gửi lên bot

**Luồng 1 — Insight thủ công:**
```
INSIGHT Arsenal vs Chelsea, 21:00 ngày 23/03
- Arsenal thắng 8/10 trận sân nhà gần đây
- Chelsea ghi bàn trong 6 trận liên tiếp
Dự đoán: Arsenal thắng 2-1
```

**Luồng 2 — Bài viết tin tức:**
```
Man City thua sốc Bournemouth 1-2 tại Etihad. Haaland đá hỏng penalty phút 78.
```

**Luồng 2 — Nhận định (ảnh + caption):**
```
[Gửi kèm ảnh thống kê]
Caption: Nhận định Real Madrid vs Barcelona El Clasico vòng 30 La Liga.
Barca 5 thắng liên tiếp, Real thiếu Vinicius Jr treo giò.
```

---

## Match Preview Image (`matchPreviewImage.js`)

Module tạo ảnh preview trận đấu tự động, chỉ dùng cho **Luồng 6** (bài nhận định).

### Luồng xử lý
1. **Bước 1** — fetch song song: `fetchTeamAssets` x2 + `fetchLeagueLogo`
2. **Bước 2** — fetch song song: tiền đạo đội nhà/khách + `fetchVenueData`
   - Nếu có `homePlayer`/`awayPlayer` → dùng tên → `fetchPlayerCutout`
   - Không có → tự tìm tiền đạo theo `teamId` → `fetchTeamForwardCutout`
3. **Bước 3** — convert tất cả URL → base64 song song (6 ảnh)
4. **`buildHtml()`** — tạo HTML nhúng font + logo + ảnh base64
5. **Puppeteer** — screenshot JPEG quality 88 → ~150KB

### TheSportsDB endpoints dùng
| Hàm | Endpoint | Trả về |
|-----|----------|--------|
| `fetchTeamAssets` | `searchteams.php?t=` | logo, `strColour1`, `teamId`, `venueId` |
| `fetchTeamForwardCutout` | `lookup_all_players.php?id=` | lọc Forward/Striker → `strCutout` |
| `fetchVenueData` | `lookupvenue.php?id=` | tên sân, `strFanart1` |
| `fetchLeagueLogo` | `lookupleague.php?id=` | `strBadge` giải đấu |

### LEAGUE_IDS (TheSportsDB)
```js
PL: 4328, CL: 4480, PD: 4335, BL1: 4331, SA: 4332, FL1: 4334
```

### Tối ưu
- `FONT_B64` + `LOGO_B64` đọc file 1 lần lúc module load
- `getChromiumPath()` cache kết quả sau lần đầu
- Queue (`browserQueue`) đảm bảo chỉ 1 Puppeteer chạy tại 1 thời điểm
- Output: JPEG quality 88 (~150KB, giảm từ PNG ~1MB)

### Assets tĩnh cần có
- `web/public/font/SVN-Hemi/SVN-HemiHead.woff2`
- `web/public/logo/logo-svg.svg`

---

## Deployment

### WordPress — Local / Hosting
- **Local:** Chạy với Local by Flywheel hoặc MAMP, URL `http://bongda247.local`
- **Production:** Upload `wp/themes/bongda247/` (kèm `dist/`) + `wp/mu-plugins/`
- Cài RankMath, đặt permalink `/%category%/%postname%/`
- Tạo user `bot` role editor + Application Password

### Bot (`bot-press.js`) — Railway
- **URL:** railway.app, project Bongda247
- **Root Directory:** `web`
- **Start Command:** `node bot-press.js` (cấu hình qua `nixpacks.toml`)
- **Auto-deploy:** push GitHub → Railway tự redeploy
- **Chi phí:** ~$0.50–1/tháng (nằm trong $5 free credit)

### Environment Variables trên Railway
```
TELEGRAM_BOT_TOKEN
TELEGRAM_OWNER_CHAT_ID=2050679271
GEMINI_API_KEY
WP_URL=https://bongda247.com       # URL WordPress production
WP_USER=bot
WP_APP_PASSWORD=                   # Application Password WP
PUBLIC_FOOTBALL_DATA_KEY
```
