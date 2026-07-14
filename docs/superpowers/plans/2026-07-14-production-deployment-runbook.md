# Runbook Deploy Production — Bongda247

> Hướng dẫn từng bước đưa WordPress `bongda247` lên `https://bongda247.blog` (Hostinger hPanel + Cloudflare, first launch). Người thực thi: **bạn** (thao tác Hostinger/Cloudflare/Google/SFTP); Claude hỗ trợ config bot/env. Thiết kế & quyết định: `docs/superpowers/specs/2026-07-13-production-deployment-design.md`.

**Nguyên tắc chốt:** Hostinger shared (hPanel) · domain `bongda247.blog` (đang park tại Hostinger, NS `ns1/ns2.dns-parking.com`) · Cloudflare (DNS/CDN/SSL) · deploy theme = SFTP · KHÔNG migrate dữ liệu cũ · bot giữ trên Railway chỉ đổi env.

---

## Phase 0 — Chuẩn bị (trước ngày deploy)

- [ ] **Mua hosting Hostinger** (chưa có — mới chỉ mua domain). Gói **Business (WordPress Hosting)** khuyến nghị (backup hàng ngày, NVMe, object cache); Premium nếu tiết kiệm. Kỳ hạn 12–24 tháng.
- [ ] Tài khoản sẵn sàng: **Cloudflare** (free), **Google** (GA4 + Search Console; AdSense để sau).
- [ ] Gói file cần upload (đã build sẵn trong repo, KHÔNG cần build trên host):
  - `wp/themes/bongda247/` (toàn bộ — kèm `dist/`, `assets/vendor/`, `assets/fonts/`, `inc/`, `template-parts/`, page templates)
  - `wp/mu-plugins/bongda247-core.php`
- [ ] Lấy sẵn giá trị: `FOOTBALL_DATA_KEY` (= `PUBLIC_FOOTBALL_DATA_KEY` trong `web/.env`); nội dung 4 trang tĩnh (`web/content/pages/*.html`).
- [ ] 8 category slug bắt buộc: `ngoai-hang-anh`, `champions-league`, `la-liga`, `bundesliga`, `serie-a`, `ligue-1`, `chuyen-nhuong`, `ngoai-san-co`.

## Phase 1 — Provision hosting (Hostinger)

- [ ] hPanel → gắn domain `bongda247.blog` vào gói hosting.
- [ ] PHP **8.2** (Advanced → PHP Config).
- [ ] Cài **WordPress** (Auto Installer). Ghi lại admin user/pass + DB.
- [ ] WP admin → Settings → Reading → **☑ Discourage search engines** (ẩn khi đang dựng; bỏ ở Phase 7).
- [ ] WP admin → Settings → General → đảm bảo Site/Home URL là `https://bongda247.blog` (đặt sau khi có SSL, hoặc để http tạm rồi sửa).

## Phase 2 — DNS + SSL (Cloudflare)

- [ ] Cloudflare → Add site `bongda247.blog` → chọn Free → CF cấp **2 nameserver**.
- [ ] Hostinger (nơi đăng ký domain) → đổi nameserver từ `ns1/ns2.dns-parking.com` sang **2 NS Cloudflare**.
- [ ] Cloudflare DNS → **A record** `@` → IP hosting Hostinger (lấy trong hPanel), **Proxied (cam)**. Thêm CNAME `www` → `bongda247.blog` (proxied).
- [ ] Hostinger → bật **SSL Let's Encrypt** cho domain (Security → SSL).
- [ ] Cloudflare → SSL/TLS → mode **Full (Strict)**. ⚠️ **TUYỆT ĐỐI KHÔNG "Flexible"** (gây redirect loop + mixed content).
- [ ] Chờ propagate (vài giờ → 24h). Kiểm tra `https://bongda247.blog` load + khoá xanh.

## Phase 3 — Deploy theme + mu-plugin (SFTP)

- [ ] hPanel → Files → FTP Accounts: lấy host/port/user (đặt mật khẩu). Dùng FileZilla hoặc File Manager.
- [ ] Upload `wp/themes/bongda247/` → `wp-content/themes/bongda247/` (giữ nguyên `dist/` + `assets/`).
- [ ] Upload `wp/mu-plugins/bongda247-core.php` → `wp-content/mu-plugins/` (tạo thư mục `mu-plugins` nếu chưa có).
- [ ] WP admin → Appearance → Themes → **Activate "bongda247"**.
- [ ] WP admin → Settings → **Permalinks** → Custom Structure `/%category%/%postname%/` → **Save** (flush rewrites). ⚠️ **Bắt buộc** — không có thì trang bài/category vỡ.
- [ ] Tạo **8 category** (slug đúng ở Phase 0) — Posts → Categories.

## Phase 4 — Config các tính năng SP1–SP4

- [ ] **SP4 — FOOTBALL_DATA_KEY:** File Manager → sửa `wp-config.php`, thêm TRƯỚC dòng `/* That's all, stop editing */`:
  ```php
  define('FOOTBALL_DATA_KEY', '<giá trị từ web/.env>');
  ```
  (⚠️ không commit vào repo.)
- [ ] **SP1 + SP4 — tạo các WP Page:**
  - Trang tĩnh (SP1): `chinh-sach-bao-mat`, `gioi-thieu`, `lien-he`, `dieu-khoan` — nội dung từ `web/content/pages/{slug}.html`.
  - Trang dữ liệu (SP4): `bang-xep-hang`, `lich-thi-dau` — **nội dung rỗng** (page template tự render).
  - **Cách nhanh (sau Phase 5):** `cd web && WP_URL=https://bongda247.blog WP_USER=bot WP_APP_PASSWORD=<...> npm run seed:pages` (tạo 4 trang tĩnh tự động). 2 trang dữ liệu tạo tay (rỗng).
  - **Cách tay:** tạo cả 6 Page trong admin.
- [ ] Kiểm tra: `/chinh-sach-bao-mat/`, `/bang-xep-hang/` load đúng (BXH có data sau khi có key).

## Phase 5 — Kết nối bot Railway

- [ ] WP admin → Users → Add New → user **`bot`** role **Editor** → Application Passwords → generate → **lưu app password**.
- [ ] ⚠️ **Gotcha bắt buộc xử lý** (nếu không bot nhận 401 khó hiểu):
  - Cloudflare → Caching → **cache rule bypass** cho `/wp-json/*`, `/wp-admin/*`, `wp-login.php`. WAF **không challenge** POST `/wp-json/` (bot là server-to-server).
  - Nếu bot vẫn 401: thêm vào `.htaccess` (Apache/LiteSpeed strip Authorization header):
    `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` hoặc `CGIPassAuth On`.
- [ ] **Railway** → project Bongda247 → Variables:
  - THÊM: `WP_URL=https://bongda247.blog`, `WP_USER=bot`, `WP_APP_PASSWORD=<app pw>`
  - XOÁ: `SANITY_PROJECT_ID`, `SANITY_DATASET`, `SANITY_API_TOKEN`
  - GIỮ: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_OWNER_CHAT_ID`, `GEMINI_API_KEY`, `PUBLIC_FOOTBALL_DATA_KEY`
- [ ] Railway redeploy → log phải thấy `✅ Đã tải 9 danh mục...` + `🚀 Bongda247 Bot is Running...`.
- [ ] Test Telegram: `/fetchnews` + `/preview` → bài/insight thật xuất hiện trên `bongda247.blog`.

## Phase 6 — SEO + Analytics + Backup + Security (v1)

- [ ] **RankMath**: cài → Setup Wizard → bật Sitemap → connect Search Console. Permalink đã đặt Phase 3.
- [ ] **GA4**: tạo property → gắn Measurement ID (qua RankMath General → Analytics, hoặc plugin) → verify Realtime.
- [ ] **Search Console**: verify domain bằng **TXT record qua Cloudflare** → submit sitemap `https://bongda247.blog/sitemap_index.xml`.
- [ ] **Backup**: UpdraftPlus → lịch hàng ngày + offsite (Google Drive). (Business đã có backup hàng ngày sẵn.)
- [ ] **Security**: `wp-config.php` thêm `define('DISALLOW_FILE_EDIT', true);`; plugin limit-login; tắt XML-RPC; admin password mạnh + 2FA.

## Phase 7 — Go-live + smoke test

- [ ] WP admin → Settings → Reading → **BỎ** "Discourage search engines".
- [ ] Smoke test (mở/curl):
  - [ ] `/` (homepage: hot slider + sidebar + insight carousel + block tin theo giải)
  - [ ] 1 bài viết `/{category}/{slug}/`, archive `/{category}/` (có BXH+lịch với 5 giải), `/404-khong-ton-tai/` → 404
  - [ ] `/chinh-sach-bao-mat/`, `/gioi-thieu/`, `/lien-he/`, `/dieu-khoan/`
  - [ ] `/bang-xep-hang/` (tab + BXH data), `/lich-thi-dau/`
  - [ ] Search (icon → nhập → kết quả), mobile nav (hamburger), dark/light toggle
  - [ ] SSL khoá xanh, không mixed-content, không redirect loop
  - [ ] Bot đăng được 1 bài + 1 insight thật
- [ ] Cloudflare: cache rule cuối (bypass admin/wp-json; cache static assets).

## Phase 8 — Sau launch

- [ ] Theo dõi index (GSC) + traffic (GA4) vài ngày.
- [ ] **Google AdSense**: đăng ký khi site đã đủ nội dung + 4 trang tĩnh sẵn (Privacy đã có sẵn khai báo AdSense/DART) → gắn mã khi được duyệt.

---

## Checklist "must-fix" đã ghi trong final review (làm trước/khi go-live)

- [ ] **Rotate App Password** user `bot` (bản migration ghi plaintext trên đĩa local — tạo mới trên prod là bản sạch).
- [ ] Permalink `/%category%/%postname%/` (Phase 3) — nếu thiếu, trang render sai.
- [ ] 8 category slug tồn tại TRƯỚC khi bot chạy (getCategoryId fallback về category đầu nếu thiếu).
- [ ] `FOOTBALL_DATA_KEY` + 2 Page `bang-xep-hang`/`lich-thi-dau` (Phase 4) — nếu thiếu, BXH/lịch ẩn/404.

## Rollback

- Lỗi theme → deactivate về theme mặc định WP (site vẫn sống).
- Lỗi bot ghi prod → revert env Railway (bot ngừng tạo mới, không phá data cũ).
- Lỗi DNS/SSL → Cloudflare "Pause" (bypass proxy) để chẩn đoán.
- Không có dữ liệu cũ để mất (site mới) → rollback nhẹ.
