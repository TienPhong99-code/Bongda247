# Deploy Production — Bongda247 (WordPress) Design

> **Bối cảnh:** Sau khi hoàn tất WordPress migration (Task 1–8 của `2026-07-13-wordpress-migration.md`), đưa site WordPress `bongda247` lên production và trỏ bot Telegram (Railway) ghi vào WP thật.

## Mục tiêu

Đưa site WordPress lên chạy công khai tại `https://bongda247.blog`, qua Cloudflare, với bot Telegram trên Railway ghi bài/insight qua WP REST API. Site mới hoàn toàn — **không migrate dữ liệu cũ**.

## Điều kiện tiên quyết (phải xong TRƯỚC khi chạy runbook này)

- **Task 7 hoàn tất & verify end-to-end:** `bot-press.js` đã dùng `web/lib/wp.js` (WP REST), không còn `@sanity/client`.
- **Task 8 hoàn tất:** repo đã gỡ Astro/Sanity, `CLAUDE.md` + memory cập nhật.
- `wp/themes/bongda247/dist/main.css` + `dist/main.js` + `assets/vendor/` đã build sẵn và commit (deploy = upload file tĩnh, host không cần chạy Node/npm).

## Bối cảnh & ràng buộc đã chốt

| Yếu tố | Quyết định |
|--------|-----------|
| Hosting | Hostinger shared (hPanel; runbook viết panel-agnostic để dùng được cả cPanel) |
| Domain | `bongda247.blog` — mới, đang park tại Hostinger (NS `ns1/ns2.dns-parking.com`) |
| Tình huống | **First launch** — không có site cũ đang chạy → không lo downtime |
| DNS / CDN / SSL | Cloudflare (free) |
| Deploy theme/plugin | SFTP / File Manager (thủ công) |
| Phạm vi v1 | RankMath SEO · GA4 + Search Console · Backup + Security |
| Ngoài phạm vi v1 | Google AdSense (đợi đủ nội dung + duyệt sau) |
| Bot | Railway giữ nguyên code/cron; chỉ đổi env `WP_URL`/`WP_USER`/`WP_APP_PASSWORD`, bỏ `SANITY_*` |
| Chủ sở hữu | Phong Hồ · phonght.dev@gmail.com |

**Người thực thi:** phần lớn thao tác do **người dùng** làm tay (Hostinger, Cloudflare, Google, SFTP). Claude hỗ trợ: giá trị điền sẵn, xử lý config bot/env, lệnh verify.

## Quyết định thiết kế

### A. Dựng thẳng trên host production, che lại trong lúc build
Site mới chưa có traffic → **không** cần staging subdomain riêng. Dựng thẳng trên hosting thật nhưng ẩn khỏi search engine trong lúc build (WP Settings → Reading → *Discourage search engines*, chưa submit sitemap). Test kỹ xong mới bỏ noindex + submit sitemap = go-live. Tránh phải migrate 2 lần.
*Loại bỏ:* staging subdomain rồi copy sang prod — thừa cho site chưa có dữ liệu thật.

### B. Tạo dữ liệu ban đầu bằng tay, chỉ 8 category
Không migrate dữ liệu cũ. Seed script (`web/scripts/seed-wp.mjs`) tạo cả category **lẫn 6 bài demo** → không dùng trên prod. **Tạo tay đúng 8 category** trong WP admin, để bot tự sinh bài thật.
Slug bắt buộc (khớp `LEAGUE_MAP`/spec): `ngoai-hang-anh`, `champions-league`, `la-liga`, `bundesliga`, `serie-a`, `ligue-1`, `chuyen-nhuong`, `ngoai-san-co`.

### C. Cloudflare SSL = Full (Strict)
Bật Let's Encrypt free của Hostinger ở **origin**, Cloudflare đặt **Full (Strict)**.
⚠️ **KHÔNG dùng "Flexible"** — gây redirect loop + mixed content với WordPress (WP tự redirect lên HTTPS trong khi CF→origin đi HTTP).

## Kiến trúc deploy (7 phase)

```
Phase 0  Chuẩn bị        → checklist tài khoản + build dist/ + gói file upload
Phase 1  Provision       → Hostinger: gắn domain, PHP 8.2, DB, cài WP sạch, noindex
Phase 2  DNS + SSL       → Cloudflare NS thay dns-parking; A record; SSL Full(Strict)
Phase 3  Deploy code     → SFTP theme+mu-plugin; activate; permalink; 8 category
Phase 4  Kết nối bot     → user bot + App Password; gotcha /wp-json; env Railway; test
Phase 5  SEO/GA/Backup   → RankMath+sitemap; GA4+GSC; UpdraftPlus; hardening
Phase 6  Go-live         → bỏ noindex; smoke test; Cloudflare cache rules
Phase 7  Sau launch      → theo dõi index/traffic; AdSense sau
```

### Điểm rủi ro / gotcha phải xử lý (Phase 4 & 6)

1. **Authorization header cho Application Password.** WP Application Password dùng HTTP Basic Auth. Một số cấu hình Apache/LiteSpeed **strip header `Authorization`** → bot nhận 401 khó hiểu. Fix: thêm vào `.htaccess` (Apache/LiteSpeed):
   `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` hoặc `CGIPassAuth On`.
2. **Cloudflare không được cache/chặn `/wp-json/` và `/wp-admin/`.** Tạo cache rule *bypass* cho `/wp-json/*`, `/wp-admin/*`, `wp-login.php`. WAF không được challenge POST tới `/wp-json/` (bot là request server-to-server, không giải được JS challenge).
3. **Cloudflare cache cho GET `/wp-json`** có thể trả dữ liệu cũ cho bot → luôn bypass.
4. **SSL mode sai** (Decision C) — redirect loop.
5. **`WP_URL` phải là `https://bongda247.blog`** (https, không dấu `/` cuối) — khớp cách `web/lib/wp.js` ghép URL.

## Interfaces / thay đổi ngoài WordPress

- **Railway env (đổi):** thêm `WP_URL`, `WP_USER`, `WP_APP_PASSWORD`; **xoá** `SANITY_PROJECT_ID`, `SANITY_DATASET`, `SANITY_API_TOKEN`. Giữ nguyên: `TELEGRAM_*`, `GEMINI_API_KEY`, `PUBLIC_FOOTBALL_DATA_KEY`.
- **Không đụng code bot** ở bước deploy — code đã xong ở Task 7. Deploy production chỉ là đổi env + redeploy Railway.

## Ngoài phạm vi

- Google AdSense (v2).
- Tính năng đã bỏ ở spec migration: LiveScoresTicker, LeagueTabs, `/lich-thi-dau`, `/bang-xep-hang`.
- Migrate nội dung Sanity cũ.
- CI/CD tự động cho theme (deploy thủ công qua SFTP là đủ ở giai đoạn này).

## Tiêu chí thành công (go-live checklist)

- [ ] `https://bongda247.blog/` trả 200, render homepage (hot slider, sidebar, insight carousel).
- [ ] Trang bài viết `/{category}/{slug}/`, archive `/{category}/`, và 404 hoạt động đúng (200/200/404).
- [ ] Dark/light toggle + 3 Swiper chạy (JS load đúng).
- [ ] SSL hợp lệ (khoá xanh), không mixed-content, không redirect loop.
- [ ] Bot Railway đăng được **1 bài thật** + **1 insight** lên prod qua Telegram (test `/fetchnews`, `/preview`).
- [ ] Sitemap RankMath truy cập được + đã submit GSC; GA4 nhận realtime hit.
- [ ] Backup tự động đã lên lịch (UpdraftPlus) + hardening cơ bản bật.
- [ ] Sau khi verify: bỏ "Discourage search engines".

## Rollback

Site mới, không có dữ liệu cũ để mất → rollback = đơn giản:
- Lỗi theme → deactivate theme về default WP (site vẫn sống).
- Lỗi bot ghi prod → revert env Railway (bot không phá dữ liệu đã có, chỉ ngừng tạo mới).
- Lỗi DNS/SSL → Cloudflare "Pause" (bypass proxy) hoặc tạm bật SSL Full (non-strict) để chẩn đoán.
