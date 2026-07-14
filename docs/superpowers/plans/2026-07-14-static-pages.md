# Static Pages (SEO/AdSense) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thêm 4 trang tĩnh (Chính sách bảo mật, Giới thiệu, Liên hệ, Điều khoản) cho website WordPress `bongda247`, đủ điều kiện AdSense + tăng E-E-A-T.

**Architecture:** Một template `page.php` dùng chung (bám `single.php`, bỏ phần bài báo) + 4 WordPress Page tạo bằng seed script idempotent qua REST (`wp.ensurePage`) từ file HTML trong repo + hàng link footer. Nội dung sửa được trong admin; provisioning lặp lại được local ↔ prod.

**Tech Stack:** WordPress 7.0.1 (PHP 8.2), theme classic `bongda247` (Tailwind v4 build sẵn `dist/`), Node 20 + axios (`web/lib/wp.js`), wp-cli wrapper `wp/bin/wp`.

## Global Constraints

- **Site local:** `http://bongda247.local` (dùng **http**, KHÔNG https).
- **Ngôn ngữ:** nội dung + comment code tiếng Việt.
- **KSES (bắt buộc):** Page tạo qua REST bởi user `bot` (đã bị gỡ `unfiltered_html`) → nội dung đi qua KSES. Chỉ dùng thẻ KSES-safe: `h2 h3 p ul ol li a strong em blockquote`. Link `<a>` chỉ dùng href/rel/target. Protocol cho phép: `http https mailto`.
- **Theme conventions (khớp `single.php`/`footer.php`):** wrapper `class="container"`; nội dung trong `<div class="prose-bd">`; heading `font-oswald`; màu `text-secondary`, viền `border-card`, nền `bg-card`, hover `hover:text-brand`, brand accent `text-brand`; bài đọc `max-w-4xl mx-auto`.
- **UI:** áp nguyên tắc `design-taste-frontend` ở chế độ **match-existing** — KHÔNG tạo aesthetic mới, KHÔNG thêm dependency/motion/JS. Giữ nguyên theme dark/blue hiện có. Dials thấp (editorial/trust-first).
- **Thông tin thật:** email liên hệ `phonght.dev@gmail.com`; brand hiển thị `Bongda247` (vận hành cá nhân, KHÔNG bịa pháp nhân doanh nghiệp).
- **KHÔNG đụng:** luồng runtime của `bot-press.js`, `matchPreviewImage.js`, các hàm hiện có trong `wp.js`. Chỉ **thêm** hàm `ensurePage` vào `wp.js`.
- **Commit sau mỗi task.**

---

## File Structure

**Tạo mới:**

| File | Trách nhiệm |
|---|---|
| `wp/themes/bongda247/page.php` | Template WordPress Page chung (trang tĩnh) |
| `web/content/pages/chinh-sach-bao-mat.html` | Nội dung Chính sách bảo mật |
| `web/content/pages/gioi-thieu.html` | Nội dung Giới thiệu |
| `web/content/pages/lien-he.html` | Nội dung Liên hệ |
| `web/content/pages/dieu-khoan.html` | Nội dung Điều khoản |
| `web/scripts/seed-pages.mjs` | Seed idempotent 4 Page từ file HTML |
| `web/test/ensurePage.test.mjs` | Integration test cho `wp.ensurePage` (chạy thật với WP local) |

**Sửa:**

| File | Thay đổi |
|---|---|
| `web/lib/wp.js` | Thêm export `ensurePage(slug, title, html)` |
| `web/package.json` | Thêm script `seed:pages` |
| `wp/themes/bongda247/footer.php` | Thêm hàng link footer tới 4 trang |

---

## Task 1: Template `page.php`

**Files:**
- Create: `wp/themes/bongda247/page.php`

**Interfaces:**
- Consumes: `get_header()`, `get_footer()`, `.prose-bd` (đã có trong `dist/main.css`), theme conventions của `single.php`.
- Produces: WordPress sẽ dùng `page.php` để render mọi `is_page()` (template hierarchy). Không export gì cho task khác.

- [ ] **Step 1: Viết `page.php`**

Create `wp/themes/bongda247/page.php` (bám `single.php`, bỏ category/ngày/featured image/tags/nguồn; breadcrumb "Trang chủ / {tiêu đề}"):

```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <?php while (have_posts()) : the_post(); ?>
    <article class="max-w-4xl mx-auto">
      <nav class="flex text-sm mb-8 gap-2 font-medium text-secondary">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="transition-colors hover:text-brand">Trang chủ</a>
        <span>/</span>
        <span class="text-brand"><?php the_title(); ?></span>
      </nav>

      <header class="mb-10">
        <h1 class="text-4xl md:text-5xl font-bold font-oswald leading-tight pb-6 border-b border-card"><?php the_title(); ?></h1>
      </header>

      <div class="p-6 md:p-10 rounded-3xl border border-card shadow-inner bg-card">
        <div class="prose-bd">
          <?php the_content(); ?>
        </div>
      </div>
    </article>
  <?php endwhile; ?>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 2: Tạo 1 Page tạm để kiểm tra template được dùng**

Run (từ repo root):
```bash
ID=$(./wp/bin/wp post create --post_type=page --post_status=publish \
  --post_title="ZZZ Kiểm Tra Page" \
  --post_content='<h2>Mục kiểm tra</h2><p>Đoạn văn ABC kiểm tra page template.</p>' --porcelain)
URL=$(./wp/bin/wp post get "$ID" --field=url)
echo "Page tạm: $ID → $URL"
```
Expected: in ra ID (số) và URL dạng `http://bongda247.local/zzz-kiem-tra-page/`.

- [ ] **Step 3: Kiểm chứng render qua `page.php`**

Run:
```bash
curl -s "$URL" -o /tmp/bd-page.html -w "HTTP %{http_code}\n"
echo "content: $(grep -c 'Đoạn văn ABC kiểm tra' /tmp/bd-page.html)"
echo "prose-bd: $(grep -c 'prose-bd' /tmp/bd-page.html)"
echo "breadcrumb: $(grep -c 'Trang chủ' /tmp/bd-page.html)"
echo "khong-co-badge-category: $(grep -c 'bg-brand/15' /tmp/bd-page.html)"
```
Expected:
```
HTTP 200
content: 1        (≥1 — nội dung render đầy đủ, không phải archive/excerpt)
prose-bd: 1       (≥1 — dùng page.php)
breadcrumb: 1     (≥1)
khong-co-badge-category: 0   (0 — page.php KHÔNG có badge category như single.php)
```
Nếu `content` = 0 hoặc `prose-bd` = 0 → WP đang fallback về `index.php` (template chưa được nhận) — kiểm tra lại tên file `page.php` đặt đúng thư mục theme active.

- [ ] **Step 4: Dọn Page tạm**

Run:
```bash
./wp/bin/wp post delete "$ID" --force
echo "Đã xoá page tạm $ID"
```
Expected: `Success: Deleted post <ID>.`

- [ ] **Step 5: Commit**

```bash
git add wp/themes/bongda247/page.php
git commit -m "feat(theme): template page.php cho trang tĩnh"
```

---

## Task 2: Hàm `ensurePage` trong `wp.js`

**Files:**
- Modify: `web/lib/wp.js` (thêm 1 export ở cuối file, sau `ensureCategory`)
- Create: `web/test/ensurePage.test.mjs`

**Interfaces:**
- Consumes: `api` (axios instance đã cấu hình auth trong `wp.js`), `deleteById(id, type)`.
- Produces: `ensurePage(slug, title, html) → Promise<{ id, link }>` — tạo Page nếu slug chưa tồn tại, cập nhật nếu đã có (idempotent). Task 3 dùng hàm này.

- [ ] **Step 1: Viết test thất bại**

Create `web/test/ensurePage.test.mjs`:
```js
import { test } from "node:test";
import assert from "node:assert/strict";
import "dotenv/config";
import axios from "axios";
import * as wp from "../lib/wp.js";

const WP = (process.env.WP_URL || "").replace(/\/$/, "");
const auth = { username: process.env.WP_USER, password: process.env.WP_APP_PASSWORD };
const SLUG = "zzz-ensurepage-test";

test("ensurePage: tạo mới rồi cập nhật cùng slug là idempotent", async () => {
  // Dọn sạch trước (phòng lần chạy trước rớt lại)
  const pre = await axios.get(`${WP}/wp-json/wp/v2/pages`, { params: { slug: SLUG, _fields: "id" }, auth });
  for (const p of pre.data) await wp.deleteById(p.id, "pages");

  const first = await wp.ensurePage(SLUG, "Tiêu đề một", "<h2>Phần A</h2><p>Nội dung một.</p>");
  assert.ok(first.id, "lần 1 phải trả về id");
  assert.match(first.link, new RegExp(SLUG), "link chứa slug");

  const second = await wp.ensurePage(SLUG, "Tiêu đề hai", "<h2>Phần B</h2><p>Nội dung hai.</p>");
  assert.equal(second.id, first.id, "chạy lại phải CÙNG id (update, không tạo trùng)");

  // Chỉ được tồn tại đúng 1 page với slug này
  const list = await axios.get(`${WP}/wp-json/wp/v2/pages`, { params: { slug: SLUG, _fields: "id" }, auth });
  assert.equal(list.data.length, 1, "chỉ được có 1 page với slug này");

  // Nội dung phải là bản cập nhật (lần 2)
  const got = await axios.get(`${WP}/wp-json/wp/v2/pages/${second.id}`, { auth });
  assert.match(got.data.content.rendered, /Nội dung hai/, "nội dung phải được cập nhật sang bản 2");

  // Dọn dẹp
  await wp.deleteById(second.id, "pages");
});
```

- [ ] **Step 2: Chạy test để xác nhận nó FAIL**

Run:
```bash
cd web && node --test test/ensurePage.test.mjs
```
Expected: FAIL — `wp.ensurePage is not a function` (chưa implement).

- [ ] **Step 3: Thêm `ensurePage` vào `wp.js`**

Thêm vào cuối `web/lib/wp.js` (sau hàm `ensureCategory`):
```js
/**
 * Tạo hoặc cập nhật một WordPress Page theo slug (idempotent). Dùng cho seed trang tĩnh.
 * Trả về { id, link }. Nội dung HTML phải hợp lệ KSES (h2/h3/p/ul/li/a/strong...) vì
 * user bot đã bị gỡ unfiltered_html.
 */
export async function ensurePage(slug, title, html) {
  const found = await api.get("/pages", { params: { slug, _fields: "id" } });
  const payload = { title, content: html, status: "publish", slug };
  if (found.data.length) {
    const res = await api.put(`/pages/${found.data[0].id}`, payload);
    return { id: res.data.id, link: res.data.link };
  }
  const res = await api.post("/pages", payload);
  return { id: res.data.id, link: res.data.link };
}
```

- [ ] **Step 4: Chạy test để xác nhận PASS**

Run:
```bash
cd web && node --test test/ensurePage.test.mjs
```
Expected: `# pass 1`, `# fail 0`.

- [ ] **Step 5: Chạy toàn bộ suite để chắc không phá test cũ**

Run:
```bash
cd web && npm test
```
Expected: `# fail 0` (8 test cũ của wp.js + 1 test mới đều pass). Yêu cầu WP local đang chạy + `web/.env` đủ `WP_URL`/`WP_USER`/`WP_APP_PASSWORD`.

- [ ] **Step 6: Commit**

```bash
git add web/lib/wp.js web/test/ensurePage.test.mjs
git commit -m "feat(wp): ensurePage() idempotent tạo/cập nhật WordPress Page"
```

---

## Task 3: Nội dung 4 trang + seed script

**Files:**
- Create: `web/content/pages/chinh-sach-bao-mat.html`, `gioi-thieu.html`, `lien-he.html`, `dieu-khoan.html`
- Create: `web/scripts/seed-pages.mjs`
- Modify: `web/package.json` (thêm script `seed:pages`)

**Interfaces:**
- Consumes: `wp.ensurePage(slug, title, html)` (Task 2).
- Produces: 4 WordPress Page publish với slug `chinh-sach-bao-mat`, `gioi-thieu`, `lien-he`, `dieu-khoan`. Task 4 (footer) link tới các slug này.

- [ ] **Step 1: Tạo file nội dung `web/content/pages/chinh-sach-bao-mat.html`**

```html
<p>Bongda247 (sau đây gọi là "chúng tôi") tôn trọng quyền riêng tư của bạn. Chính sách này giải thích chúng tôi thu thập, sử dụng và bảo vệ thông tin như thế nào khi bạn truy cập website.</p>

<h2>1. Thông tin chúng tôi thu thập</h2>
<p>Chúng tôi không yêu cầu bạn đăng ký tài khoản và không chủ động thu thập thông tin cá nhân định danh. Khi bạn truy cập, máy chủ có thể tự động ghi nhận dữ liệu kỹ thuật thông thường như địa chỉ IP, loại trình duyệt, thiết bị, trang đã xem và thời điểm truy cập nhằm mục đích thống kê và bảo mật.</p>

<h2>2. Cookie và công nghệ theo dõi</h2>
<p>Website sử dụng cookie để ghi nhớ tuỳ chọn (ví dụ chế độ sáng/tối) và phục vụ thống kê, quảng cáo. Bạn có thể tắt hoặc xoá cookie trong cài đặt trình duyệt; một số tính năng có thể hoạt động không đầy đủ nếu tắt cookie.</p>

<h2>3. Quảng cáo của bên thứ ba (Google AdSense)</h2>
<p>Chúng tôi sử dụng Google AdSense để hiển thị quảng cáo. Google và các đối tác có thể dùng cookie (bao gồm cookie DART) để phân phối quảng cáo dựa trên lần truy cập của bạn tới website này và các website khác.</p>
<ul>
  <li>Tìm hiểu cách Google sử dụng dữ liệu tại <a href="https://policies.google.com/technologies/partner-sites" rel="nofollow noopener" target="_blank">policies.google.com/technologies/partner-sites</a>.</li>
  <li>Tắt quảng cáo cá nhân hoá tại <a href="https://www.google.com/settings/ads" rel="nofollow noopener" target="_blank">google.com/settings/ads</a>.</li>
</ul>

<h2>4. Google Analytics</h2>
<p>Chúng tôi dùng Google Analytics để hiểu hành vi truy cập ở dạng tổng hợp, ẩn danh, giúp cải thiện nội dung. Dữ liệu này không dùng để định danh cá nhân bạn.</p>

<h2>5. Nguồn dữ liệu bên thứ ba</h2>
<p>Một số số liệu bóng đá (lịch thi đấu, bảng xếp hạng) được lấy từ dịch vụ football-data.org. Đây là dữ liệu công khai về giải đấu, không liên quan tới thông tin cá nhân của bạn.</p>

<h2>6. Liên kết tới website khác</h2>
<p>Nội dung có thể chứa liên kết tới website bên ngoài. Chúng tôi không chịu trách nhiệm về chính sách bảo mật hay nội dung của các website đó.</p>

<h2>7. Quyền của bạn</h2>
<p>Bạn có quyền yêu cầu biết, chỉnh sửa hoặc xoá thông tin liên quan tới mình (nếu có), cũng như từ chối cookie quảng cáo. Vui lòng liên hệ với chúng tôi để được hỗ trợ.</p>

<h2>8. Thay đổi chính sách</h2>
<p>Chính sách có thể được cập nhật theo thời gian. Mọi thay đổi sẽ được đăng tải trên trang này.</p>

<h2>9. Liên hệ</h2>
<p>Mọi thắc mắc về quyền riêng tư, vui lòng gửi email tới <a href="mailto:phonght.dev@gmail.com">phonght.dev@gmail.com</a>.</p>
```

- [ ] **Step 2: Tạo file `web/content/pages/gioi-thieu.html`**

```html
<p><strong>Bongda247</strong> là blog tin tức và phân tích bóng đá bằng tiếng Việt, tập trung vào các giải đấu lớn: Ngoại hạng Anh, Champions League, La Liga, Bundesliga, Serie A và Ligue 1.</p>

<h2>Chúng tôi làm gì</h2>
<p>Chúng tôi tổng hợp, biên tập và phân tích tin chuyển nhượng, nhận định trận đấu và diễn biến các giải đấu hàng đầu, giúp người hâm mộ Việt Nam theo dõi bóng đá thế giới nhanh và dễ hiểu.</p>

<h2>Quy trình nội dung</h2>
<p>Chúng tôi ứng dụng trí tuệ nhân tạo để hỗ trợ soạn thảo và tổng hợp thông tin, nhưng mọi bài viết đều được con người xem xét và kiểm duyệt trước khi đăng. Số liệu (bảng xếp hạng, phong độ) lấy từ nguồn dữ liệu bóng đá công khai; các nhận định và dự đoán mang tính tham khảo.</p>

<h2>Cam kết</h2>
<ul>
  <li>Nội dung tiếng Việt, dễ đọc, cập nhật thường xuyên.</li>
  <li>Dẫn nguồn khi tham khảo thông tin từ bên thứ ba.</li>
  <li>Tôn trọng bản quyền và sẵn sàng tiếp nhận phản hồi.</li>
</ul>

<h2>Ai đứng sau Bongda247</h2>
<p>Bongda247 được vận hành bởi một cá nhân yêu bóng đá tại Việt Nam. Chúng tôi luôn hoan nghênh góp ý để cải thiện chất lượng nội dung.</p>

<h2>Liên hệ</h2>
<p>Góp ý và hợp tác, vui lòng gửi email tới <a href="mailto:phonght.dev@gmail.com">phonght.dev@gmail.com</a> hoặc xem trang <a href="/lien-he/">Liên hệ</a>.</p>
```

- [ ] **Step 3: Tạo file `web/content/pages/lien-he.html`**

```html
<p>Cảm ơn bạn đã quan tâm tới Bongda247. Chúng tôi luôn sẵn sàng lắng nghe góp ý, phản hồi và đề nghị hợp tác.</p>

<h2>Email</h2>
<p>Cách nhanh nhất để liên hệ là gửi email tới:</p>
<p><strong><a href="mailto:phonght.dev@gmail.com">phonght.dev@gmail.com</a></strong></p>

<h2>Nội dung nên gửi</h2>
<ul>
  <li>Góp ý về nội dung, báo lỗi thông tin.</li>
  <li>Đề nghị hợp tác, quảng cáo.</li>
  <li>Yêu cầu liên quan tới bản quyền, gỡ nội dung (xem thêm trang <a href="/dieu-khoan/">Điều khoản</a>).</li>
</ul>

<h2>Thời gian phản hồi</h2>
<p>Chúng tôi cố gắng phản hồi trong vòng 2–3 ngày làm việc. Cảm ơn bạn đã kiên nhẫn.</p>
```

- [ ] **Step 4: Tạo file `web/content/pages/dieu-khoan.html`**

```html
<p>Bằng việc truy cập và sử dụng Bongda247, bạn đồng ý với các điều khoản dưới đây. Nếu không đồng ý, vui lòng ngừng sử dụng website.</p>

<h2>1. Sử dụng nội dung</h2>
<p>Nội dung trên website nhằm mục đích thông tin và giải trí. Bạn có thể đọc và chia sẻ liên kết tới bài viết. Việc sao chép, đăng lại toàn bộ nội dung khi chưa được phép là không được khuyến khích.</p>

<h2>2. Bản quyền và nguồn</h2>
<p>Nhiều bài viết được biên tập lại từ các nguồn tin bóng đá và có dẫn nguồn khi phù hợp. Một số số liệu do football-data.org cung cấp. Nếu bạn là chủ sở hữu bản quyền và cho rằng nội dung nào đó vi phạm quyền của mình, vui lòng liên hệ để chúng tôi xem xét gỡ bỏ hoặc điều chỉnh.</p>

<h2>3. Nhận định và dự đoán</h2>
<p>Các bài nhận định, thống kê và dự đoán tỉ số chỉ mang tính tham khảo, không phải lời khuyên cá cược hay đầu tư. Chúng tôi không khuyến khích cá cược và không chịu trách nhiệm cho bất kỳ quyết định nào dựa trên nội dung của website.</p>

<h2>4. Giới hạn trách nhiệm</h2>
<p>Chúng tôi nỗ lực bảo đảm thông tin chính xác nhưng không cam kết nội dung luôn đầy đủ, cập nhật hoặc không có sai sót. Chúng tôi không chịu trách nhiệm cho thiệt hại phát sinh từ việc sử dụng website.</p>

<h2>5. Liên kết bên thứ ba</h2>
<p>Website có thể chứa quảng cáo và liên kết tới bên thứ ba. Chúng tôi không kiểm soát và không chịu trách nhiệm về nội dung, sản phẩm hay dịch vụ của họ.</p>

<h2>6. Thay đổi điều khoản</h2>
<p>Điều khoản có thể được cập nhật bất kỳ lúc nào. Việc tiếp tục sử dụng website sau khi thay đổi đồng nghĩa bạn chấp nhận điều khoản mới.</p>

<h2>7. Liên hệ</h2>
<p>Mọi thắc mắc về điều khoản, vui lòng gửi email tới <a href="mailto:phonght.dev@gmail.com">phonght.dev@gmail.com</a>.</p>
```

- [ ] **Step 5: Viết `web/scripts/seed-pages.mjs`**

```js
// Seed 4 trang tĩnh (Giới thiệu, Liên hệ, Chính sách bảo mật, Điều khoản) lên WordPress.
// Idempotent — chạy lại cập nhật đúng trang theo slug, không tạo trùng. Chạy: npm run seed:pages
import "dotenv/config";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import * as wp from "../lib/wp.js";

const __dirname = dirname(fileURLToPath(import.meta.url));
const CONTENT_DIR = join(__dirname, "..", "content", "pages");

const PAGES = [
  { slug: "gioi-thieu",         title: "Giới thiệu" },
  { slug: "lien-he",            title: "Liên hệ" },
  { slug: "chinh-sach-bao-mat", title: "Chính sách bảo mật" },
  { slug: "dieu-khoan",         title: "Điều khoản sử dụng" },
];

async function main() {
  console.log("🌱 Seed trang tĩnh...");
  for (const p of PAGES) {
    const html = readFileSync(join(CONTENT_DIR, `${p.slug}.html`), "utf8");
    const page = await wp.ensurePage(p.slug, p.title, html);
    console.log(`   📄 ${page.id} — ${p.title} → ${page.link}`);
  }
  console.log("✅ Seed trang tĩnh xong.");
}

main().catch((e) => {
  console.error("❌ Seed trang lỗi:", e.response?.data ?? e.message);
  process.exit(1);
});
```

- [ ] **Step 6: Thêm script vào `web/package.json`**

Trong khối `"scripts"`, thêm dòng `seed:pages` (giữ nguyên các script khác):
```json
    "seed:pages": "node scripts/seed-pages.mjs"
```
(đặt cạnh script `"seed"` hiện có).

- [ ] **Step 7: Chạy seed**

Run:
```bash
cd web && npm run seed:pages
```
Expected: 4 dòng `📄 <id> — <tên> → http://bongda247.local/<slug>/` và `✅ Seed trang tĩnh xong.`

- [ ] **Step 8: Kiểm chứng 4 trang + markers AdSense**

Run (từ repo root):
```bash
for s in chinh-sach-bao-mat gioi-thieu lien-he dieu-khoan; do
  curl -s -o "/tmp/bd-$s.html" -w "$s: HTTP %{http_code}\n" "http://bongda247.local/$s/"
done
echo "--- markers Privacy ---"
grep -c "Google AdSense" /tmp/bd-chinh-sach-bao-mat.html
grep -c "cookie" /tmp/bd-chinh-sach-bao-mat.html
grep -c "phonght.dev@gmail.com" /tmp/bd-chinh-sach-bao-mat.html
grep -c "prose-bd" /tmp/bd-gioi-thieu.html
```
Expected: cả 4 trang `HTTP 200`; các `grep -c` đều ≥ 1 (Privacy có "Google AdSense", "cookie", email; Giới thiệu render qua `page.php` nên có `prose-bd`).

- [ ] **Step 9: Kiểm chứng idempotent**

Run:
```bash
cd web && npm run seed:pages
./wp/bin/wp post list --post_type=page --field=post_name 2>/dev/null | sort | uniq -c | sort -rn | head
```
Expected: seed chạy lại in ra cùng 4 id như lần trước (không tạo mới); mỗi slug xuất hiện đúng **1 lần** (không nhân đôi).

- [ ] **Step 10: Commit**

```bash
git add web/content/pages web/scripts/seed-pages.mjs web/package.json
git commit -m "feat(pages): nội dung + seed 4 trang tĩnh (privacy, about, contact, terms)"
```

---

## Task 4: Link footer tới 4 trang

**Files:**
- Modify: `wp/themes/bongda247/footer.php`

**Interfaces:**
- Consumes: 4 Page với slug `gioi-thieu`, `lien-he`, `chinh-sach-bao-mat`, `dieu-khoan` (Task 3).
- Produces: hàng link footer hiển thị trên mọi trang.

- [ ] **Step 1: Sửa `footer.php` thêm hàng link**

Thay nội dung `wp/themes/bongda247/footer.php` bằng (thêm `<nav>` giữa brand và copyright; guard `get_page_by_path` để không vỡ nếu trang chưa seed):

```php
<?php defined('ABSPATH') || exit; ?>
</main>

<footer class="border-t border-card py-10">
  <div class="container">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
      <span class="font-hemi text-xl uppercase">
        BONGDA<span class="text-brand">247</span>
      </span>

      <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm text-secondary">
        <?php
        $bd_footer_pages = [
          'gioi-thieu'         => 'Giới thiệu',
          'lien-he'            => 'Liên hệ',
          'chinh-sach-bao-mat' => 'Chính sách bảo mật',
          'dieu-khoan'         => 'Điều khoản',
        ];
        foreach ($bd_footer_pages as $bd_slug => $bd_label) :
          $bd_page = get_page_by_path($bd_slug);
          if ($bd_page) : ?>
            <a href="<?php echo esc_url(get_permalink($bd_page)); ?>" class="transition-colors hover:text-brand"><?php echo esc_html($bd_label); ?></a>
        <?php endif; endforeach; ?>
      </nav>

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

- [ ] **Step 2: Kiểm chứng link footer hiện trên trang chủ**

Run (từ repo root):
```bash
curl -s http://bongda247.local/ -o /tmp/bd-home.html
for s in gioi-thieu lien-he chinh-sach-bao-mat dieu-khoan; do
  echo "$s: $(grep -c "/$s/" /tmp/bd-home.html)"
done
```
Expected: mỗi slug ≥ 1 (link footer trỏ đúng `http://bongda247.local/<slug>/`).

- [ ] **Step 3: Kiểm chứng không lỗi PHP**

Run:
```bash
grep -i "fatal error\|warning:\|notice:\|deprecated" /tmp/bd-home.html || echo "✅ Không lỗi PHP rò ra output"
```
Expected: `✅ Không lỗi PHP rò ra output`.

- [ ] **Step 4: Commit**

```bash
git add wp/themes/bongda247/footer.php
git commit -m "feat(theme): link footer tới 4 trang tĩnh"
```

---

## Ghi chú (ngoài phạm vi plan)

- **Deploy prod:** chạy `npm run seed:pages` với `WP_URL` = domain thật để tạo 4 trang trên prod (đưa vào deploy runbook, sub-project deploy).
- **Menu điều hướng:** nếu sau này muốn thêm 4 trang vào menu header, tạo trong WP admin (Appearance → Menus, location `primary`) — không cần code.
- **Cài mã AdSense/GA4 thật:** thuộc deploy runbook, không phải plan này.
