# CLAUDE.md — Bongda247 Project

## Quy tắc bắt buộc

Sau mỗi thay đổi đáng kể (thêm tính năng, đổi API, đổi cấu trúc, đổi env vars, đổi luồng xử lý):
1. **Cập nhật CLAUDE.md** — phần liên quan (tích hợp, env vars, luồng hoạt động, v.v.)
2. **Cập nhật memory** — các file trong `.claude/projects/.../memory/` tương ứng (bot_architecture.md, deployment.md, v.v.)

Không cần người dùng nhắc — tự động làm sau khi hoàn thành task.

---

## Tổng quan dự án

Website tin tức và phân tích bóng đá bằng AI, viết bằng **tiếng Việt**, tập trung vào các giải đấu lớn (Ngoại hạng Anh, Champions League, La Liga, Bundesliga, Serie A, Ligue 1).

Dự án gồm **2 ứng dụng chính**:
- `cms/` — CMS backend (Sanity Studio)
- `web/` — Frontend website (Astro + React)

---

## Tech Stack

### Frontend (`web/`)
| Công nghệ | Version | Mục đích |
|-----------|---------|----------|
| Astro | 5.17.1 | Framework chính (SSR hybrid) |
| React | 19.2.4 | Interactive components |
| Tailwind CSS | 4.2.1 | Styling |
| Swiper | 12.1.2 | Carousel / slider |
| @sanity/client | 7.16.0 | Kết nối CMS |
| @tanstack/react-query | 5.90.21 | Data fetching |
| @google/generative-ai | 0.2.1 | AI (Google Gemini) |
| Telegraf | 4.16.3 | Telegram bot |
| Axios | 1.13.6 | HTTP client |

### Backend/CMS (`cms/`)
| Công nghệ | Version | Mục đích |
|-----------|---------|----------|
| Sanity Studio | 5.13.0 | Headless CMS |
| TypeScript | 5.8 | Type safety |
| React | 19.1 | Studio UI |

---

## Cấu trúc thư mục

### Frontend (`web/src/`)
```
src/
├── assets/images/          # Icon assets (flame.png cho badge "hot")
├── components/
│   ├── layout/
│   │   └── Header.astro    # Navigation header
│   ├── widgets/
│   │   ├── LeagueTabs.jsx  # React — lọc theo giải đấu
│   │   └── LiveScoresTicker.jsx  # React — tỷ số trực tiếp
│   ├── HotNewsSlider.astro     # Carousel tin hot (5 bài mới nhất)
│   ├── MatchInsights.astro     # Carousel nhận định trận đấu
│   ├── NewsSection.astro       # Template section tin tức
│   ├── SidebarSlider.astro     # Sidebar carousel
│   ├── PortableText.astro      # Render Sanity rich text
│   ├── SEO.astro               # SEO meta tags
│   └── ThemeToggle.astro       # Dark/Light mode toggle
├── content/
│   ├── config.ts           # Astro content collection config
│   └── consts.ts           # Site title, description, brand color
├── layouts/
│   ├── MainLayout.astro    # HTML layout chính với meta tags
│   └── Layout.astro
├── lib/
│   ├── sanity.ts           # Sanity client + GROQ queries + image URL builder
│   └── football-data.ts    # Football-Data.org API service
├── pages/
│   ├── index.astro         # Homepage
│   ├── [category]/[slug].astro  # Dynamic article pages
│   └── api/
│       └── live-matches.ts # API endpoint lấy trận đấu trực tiếp
├── styles/
│   └── global.css
├── types/
│   └── index.ts            # TypeScript interfaces (Post, Match, etc.)
└── utils/
    ├── league-config.ts    # Cấu hình giải đấu (tên, màu sắc, mã)
    └── team-mapper.ts      # Map tên đội sang tiếng Việt (100+ đội)
```

### File đặc biệt
- `web/bot-press.js` — Telegram bot + AI tự động tạo bài đăng lên Sanity

### CMS (`cms/`)
```
cms/
├── schemaTypes/
│   ├── index.ts            # Export tất cả schemas
│   ├── post.ts             # Schema bài viết (title, slug, category, content, image, tags)
│   ├── category.ts         # Schema danh mục/giải đấu
│   └── matchInsight.ts     # Schema nhận định trận đấu
├── sanity.config.ts        # Cấu hình Sanity project
└── sanity.cli.ts           # Sanity CLI config
```

---

## Routes & API

### Frontend Routes
- `/` — Homepage (hot news slider, match insights, sidebar)
- `/[category]/[slug]` — Bài viết động (VD: `/ngoai-hang-anh/ten-bai-viet`)
- `/tin-tuc/[...slug]` → redirect → `/ngoai-hang-anh/[...slug]`

### API Endpoints
- `GET /api/live-matches?league=PL` — Lấy trận đấu theo mã giải
  - Mã giải hỗ trợ: `PL` (Premier League), `CL` (Champions League), `BL1` (Bundesliga), `SA` (Serie A), `PD` (La Liga), `FL1` (Ligue 1)

---

## Tích hợp bên ngoài

| Service | Mục đích |
|---------|----------|
| Sanity.io | CMS — Lưu bài viết, danh mục, nhận định trận |
| API-Football (api-football.com) | Real-time match data, standings, H2H, team fixtures |
| Google Gemini 2.5 Flash | AI tạo phân tích và dự đoán trận đấu |
| Telegram Bot | Phân phối nội dung tự động |
| Google AdSense | Quảng cáo |

**Sanity config**: Project ID `wwpnye2x`, Dataset `production`

---

## Environment Variables

### Frontend (`.env`)
```
PUBLIC_SANITY_PROJECT_ID=wwpnye2x
PUBLIC_SANITY_DATASET=production
PUBLIC_FOOTBALL_DATA_KEY=<api_key>  # dùng cho live-scores frontend
```

### Bot (`bot-press.js`)
```
SANITY_PROJECT_ID=
SANITY_DATASET=
SANITY_API_TOKEN=        # Token có quyền write
GEMINI_API_KEY=          # Google Gemini API key
TELEGRAM_BOT_TOKEN=      # Telegram bot token
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

### Frontend
```bash
cd web
npm run dev      # localhost:4321
npm run build    # Build production → ./dist/
npm run preview  # Preview bản build
```

### CMS
```bash
cd cms
yarn dev         # Start Sanity Studio
yarn build       # Build Studio
yarn deploy      # Deploy Studio lên Sanity cloud
```

### Telegram Bot
```bash
cd web
node bot-press.js
```

---

## Schemas Sanity

### Post
- `title`, `slug`, `category` (ref), `excerpt`
- `mainImage` (với hotspot), `content` (block content + inline images)
- `publishedAt`, `hashtags`

### Category
- `title` (tên giải: "Ngoại hạng Anh", "Champions League"...)
- `slug`, `description`

### MatchInsight
- `homeTeam`, `awayTeam`, `matchTime` (format: "HH:mm - DD/MM")
- `isHot` (boolean — trận nổi bật)
- `insights` (mảng string — thống kê/phân tích)
- `prediction` (dự đoán kết quả)
- `publishedAt`

---

## Luồng hoạt động AI (`bot-press.js`)

### Khởi động
1. Tải toàn bộ danh mục từ Sanity qua GROQ → lưu vào `CATEGORIES` map (slug → `{id, title}`)
2. Không hardcode ID — dùng `/reload` để cập nhật danh mục mới bất kỳ lúc nào

### Luồng 1 — INSIGHT thủ công
**Trigger:** tin nhắn chứa từ `INSIGHT`

1. Gemini trích xuất JSON: `homeTeam`, `awayTeam`, `matchTime`, `hot`, `insights[]`, `prediction`
2. Bot hiển thị preview với nút: **Đổi HOT** / **Đăng lên Slide** / **Hủy**
3. Xác nhận → tạo `matchInsight` trong Sanity (lưu cả `matchDate = null`)

### Luồng 2 — BÀI VIẾT thủ công
**Trigger:** text hoặc ảnh+caption không chứa INSIGHT

1. AI tự nhận diện giải đấu → field `league` khớp slug Sanity
2. Gemini viết JSON: `title`, `excerpt`, `league`, `sections[]`
3. Xác nhận → upload ảnh → tạo Portable Text → tạo `post`

### Luồng 3 — ALBUM ẢNH
**Trigger:** gửi nhiều ảnh cùng lúc (media group)

- `setTimeout(1500ms)` đợi đủ ảnh → tránh race condition
- Sau đó vào Luồng 1 hoặc 2 tuỳ caption

### Luồng 4 — DAILY AUTO PREVIEW (tự động)
**Trigger:** Cron 8:00 sáng giờ Việt Nam (chạy trên Railway)

1. Gọi API-Football lấy lịch thi đấu hôm nay
2. Lọc 6 giải: PL, CL, PD, BL1, SA, FL1 — tối đa 3 trận/giải
3. Gọi API lấy BXH thực tế từng giải (hạng, điểm, W/D/L, phong độ 5 trận)
4. Gọi API lấy H2H (10 trận) + form đội nhà (10 trận) + form đội khách (10 trận)
5. Tính Over 2.5, BTTS, clean sheet theo sân nhà/sân khách/tổng
6. Gemini tạo nhận định với số liệu thực (VD: "BTTS sân khách - 4/5 trận cuối")
5. Gửi từng trận về Telegram (chat ID owner) với nút:
   - `🔄 Đổi HOT` — toggle, chưa đăng
   - `✅ Đăng lên Slide` → tạo `matchInsight` (lưu `matchDate` = giờ UTC thực tế)
   - `⏭ Bỏ qua`

**Data thật từ API:** hạng BXH, điểm, W/D/L, form, H2H 10 trận, Over 2.5 / BTTS / clean sheet theo sân
**Data AI tạo:** nhận định chiến thuật, dự đoán tỉ số

### Auto Cleanup
- **07:55 sáng** — xóa tự động tất cả `matchInsight` có `matchDate < (now - 3h)`
- Insight thủ công (không có `matchDate`) → xóa tay qua `/list`
- Sau khi xóa → gửi thông báo về Telegram

### Lệnh quản lý
| Lệnh | Chức năng |
|------|-----------|
| `/preview` | Nhận định trận hôm nay (kích hoạt thủ công) |
| `/tomorrow` | Nhận định trận ngày mai |
| `/list` | Xem & xóa 10 insight đang hiển thị |
| `/posts` | Xem & xóa 8 bài viết gần nhất |
| `/reload` | Tải lại danh mục từ Sanity |

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

## Schemas Sanity (cập nhật)

### MatchInsight
- `homeTeam`, `awayTeam`, `matchTime` (string "HH:mm - DD/MM")
- `matchDate` (datetime UTC) — dùng để auto-delete sau trận
- `hot` (boolean), `insights[]`, `prediction`, `publishedAt`

---

## Deployment

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
SANITY_PROJECT_ID=wwpnye2x
SANITY_DATASET=production
SANITY_API_TOKEN
PUBLIC_FOOTBALL_DATA_KEY
```
