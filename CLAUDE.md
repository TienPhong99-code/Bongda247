# CLAUDE.md — Bongda247 Project

## Tổng quan dự án

Website tin tức và phân tích bóng đá bằng AI, viết bằng **tiếng Việt**, tập trung vào các giải đấu lớn (Ngoại hạng Anh, Champions League, La Liga, Bundesliga, Serie A, Ligue 1).

Dự án gồm **2 ứng dụng chính**:
- `bongda247vn/` — CMS backend (Sanity Studio)
- `football-ai-blog/` — Frontend website (Astro + React)

---

## Tech Stack

### Frontend (`football-ai-blog/`)
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

### Backend/CMS (`bongda247vn/`)
| Công nghệ | Version | Mục đích |
|-----------|---------|----------|
| Sanity Studio | 5.13.0 | Headless CMS |
| TypeScript | 5.8 | Type safety |
| React | 19.1 | Studio UI |

---

## Cấu trúc thư mục

### Frontend (`football-ai-blog/src/`)
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
- `football-ai-blog/bot-press.js` — Telegram bot + AI tự động tạo bài đăng lên Sanity

### CMS (`bongda247vn/`)
```
bongda247vn/
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
| Football-Data.org | Real-time match data, standings, lịch thi đấu |
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
PUBLIC_FOOTBALL_DATA_KEY=<api_key>
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
cd football-ai-blog
npm run dev      # localhost:4321
npm run build    # Build production → ./dist/
npm run preview  # Preview bản build
```

### CMS
```bash
cd bongda247vn
yarn dev         # Start Sanity Studio
yarn build       # Build Studio
yarn deploy      # Deploy Studio lên Sanity cloud
```

### Telegram Bot
```bash
cd football-ai-blog
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
1. Tải toàn bộ danh mục (category) từ Sanity qua GROQ → lưu vào `CATEGORIES` map (slug → `{id, title}`)
2. Không hardcode ID — dùng `/reload` để cập nhật danh mục mới bất kỳ lúc nào

### Luồng 1 — INSIGHT (số liệu trận đấu)
**Trigger:** tin nhắn chứa từ `INSIGHT` (hoa/thường đều được)

1. Gửi nội dung trận đấu kèm từ khóa INSIGHT
2. Gemini trích xuất JSON: `homeTeam`, `awayTeam`, `matchTime`, `hot`, `insights[]`, `prediction`
3. Bot hiển thị preview với 3 nút: **Đổi HOT** / **Đăng lên Slide** / **Hủy**
4. Nút Đổi HOT toggle `true/false` và cập nhật ngay preview
5. Xác nhận → tạo document `matchInsight` trong Sanity → hiển thị trên `MatchInsights.astro`

**Format `matchTime`:**
- Chỉ có ngày → `"22/03"`
- Có cả giờ → `"21:00 - 22/03"`

### Luồng 2 — BÀI VIẾT (tin tức / phân tích)
**Trigger:** tin nhắn text hoặc ảnh+caption KHÔNG chứa từ INSIGHT

1. Gửi text hoặc ảnh kèm caption mô tả nội dung bài
2. AI tự nhận diện giải đấu → trả về field `league` khớp slug trong Sanity
3. Gemini viết bài JSON: `title`, `excerpt`, `league`, `sections[]` (heading + text)
4. Bot hiển thị preview: tiêu đề, excerpt, giải đấu, số ảnh đính kèm
5. Xác nhận → upload ảnh lên Sanity Assets → tạo Portable Text → tạo document `post`
6. Ảnh đầu tiên = `mainImage`, ảnh 2+ chèn xen kẽ giữa các section

### Luồng 3 — ALBUM ẢNH (media group)
**Trigger:** gửi nhiều ảnh cùng lúc (Telegram media group)

- Dùng `setTimeout(1500ms)` đợi tất cả ảnh đến trước khi xử lý → tránh race condition
- Caption trên bất kỳ ảnh nào trong album đều được nhận diện
- Sau đó vào Luồng 1 hoặc 2 tuỳ nội dung caption

### Lệnh quản lý
| Lệnh | Chức năng |
|------|-----------|
| `/list` | Xem 10 insight đang hiển thị, mỗi cái có nút 🗑 Xóa |
| `/posts` | Xem 8 bài viết gần nhất kèm danh mục + ngày, có nút 🗑 Xóa |
| `/reload` | Load lại danh mục từ Sanity (không cần restart bot) |

### Ví dụ tin nhắn gửi lên bot

**Luồng 1 — Insight:**
```
INSIGHT Arsenal vs Chelsea, 21:00 ngày 23/03, trận HOT
- Arsenal thắng 8/10 trận sân nhà gần đây
- Chelsea ghi bàn trong 6 trận liên tiếp
- Havertz đang có phong độ tốt với 4 bàn/5 trận
Dự đoán: Arsenal thắng 2-1
```

**Luồng 2 — Bài viết tin tức (text thuần):**
```
Man City thua sốc Bournemouth 1-2 tại Etihad. Haaland đá hỏng penalty phút 78.
Pep Guardiola thừa nhận đây là kết quả đáng thất vọng khi Man City đang chạy đua
vô địch với Arsenal chỉ còn kém 2 điểm.
```

**Luồng 2 — Bài viết phân tích (ảnh + caption):**
```
[Gửi kèm ảnh thống kê]
Caption: Nhận định Real Madrid vs Barcelona El Clasico vòng 30 La Liga.
Barca vào trận với phong độ bùng nổ 5 thắng liên tiếp, Lewandowski 12 bàn
gần nhất. Real thiếu Vinicius Jr treo giò.
```

**Luồng 2 — Nhận định soi kèo:**
```
Soi kèo Bayern Munich vs Dortmund Bundesliga 22/03. Bayern thua 2 trận
gần nhất trên sân khách. Dortmund mạnh ở hiệp 2 với 70% bàn thắng sau
phút 60. Kèo chấp 0.5 nghiêng về Dortmund.
```
