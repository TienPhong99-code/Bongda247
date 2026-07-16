import puppeteer from "puppeteer-core";
import { execFileSync } from "child_process";
import axios from "axios";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

// ============================================================
// News Preview Image — ảnh featured branded 1200×630 cho bài RSS.
// Thay cho việc scrape og:image của báo gốc (tránh rủi ro bản quyền ảnh).
// Chỉ dùng tài sản của Bongda247 (logo + font + tiêu đề tự viết); ảnh nền
// TheSportsDB (nếu có) là tuỳ chọn trang trí, cùng nguồn đã dùng in-content.
// ============================================================

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Font SVN-Hemi Head hỗ trợ tiếng Việt (dấu) — nhúng base64 để Puppeteer dùng offline.
const FONT_B64 = fs.readFileSync(
  path.join(__dirname, "public/font/SVN-Hemi/SVN-HemiHead.woff2")
).toString("base64");

const LOGO_B64 = `data:image/svg+xml;base64,${fs.readFileSync(
  path.join(__dirname, "public/logo/logo-svg.svg")
).toString("base64")}`;

// Tìm đường dẫn Chromium — cache lại sau lần đầu
let _chromiumPath = null;
function getChromiumPath() {
  if (_chromiumPath) return _chromiumPath;
  if (process.env.PUPPETEER_EXECUTABLE_PATH) {
    return (_chromiumPath = process.env.PUPPETEER_EXECUTABLE_PATH);
  }
  for (const bin of ["chromium", "chromium-browser", "google-chrome-stable", "google-chrome"]) {
    try { return (_chromiumPath = execFileSync("which", [bin]).toString().trim()); } catch {}
  }
  return (_chromiumPath = "/usr/bin/chromium");
}

// Tải ảnh → base64 data URI (nhúng thẳng vào HTML, tránh lỗi mạng trong Puppeteer)
async function toBase64(url) {
  if (!url) return null;
  try {
    const res = await axios.get(url, {
      responseType: "arraybuffer",
      timeout: 8000,
      headers: { "User-Agent": "Mozilla/5.0 (compatible; Bongda247Bot/1.0)" },
    });
    const mime = res.headers["content-type"]?.split(";")[0] || "image/png";
    return `data:${mime};base64,${Buffer.from(res.data).toString("base64")}`;
  } catch { return null; }
}

// Escape ký tự HTML trong tiêu đề/category (chống vỡ layout + injection).
function esc(s) {
  return String(s ?? "")
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

// ============================================================
// HTML TEMPLATE
// ============================================================
function buildHtml({ title, categoryName, heroImageB64 }) {
  const hero = heroImageB64
    ? `background-image:url('${heroImageB64}');background-size:cover;background-position:center;`
    : `background:radial-gradient(120% 120% at 80% 0%, #17202b 0%, #0e1217 45%, #070b11 100%);`;

  return `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@font-face{
  font-family:'SVN-Hemi Head';
  src:url('data:font/woff2;base64,${FONT_B64}') format('woff2');
  font-weight:normal;font-style:normal;
}
*{margin:0;padding:0;box-sizing:border-box}
body{width:1200px;height:630px;overflow:hidden}
.wrap{
  width:1200px;height:630px;position:relative;overflow:hidden;
  ${hero}
  font-family:'SVN-Hemi Head',Arial,sans-serif;
}
/* Overlay tối để chữ luôn đọc được (đậm hơn bên trái nơi đặt tiêu đề) */
.overlay{
  position:absolute;inset:0;
  background:linear-gradient(90deg, rgba(7,11,17,.94) 0%, rgba(7,11,17,.82) 45%, rgba(7,11,17,.55) 100%);
}
/* Lưới nền mờ */
.grid{
  position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),
    linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px);
  background-size:60px 60px;
}
/* Hào quang đỏ accent góc dưới trái */
.glow{
  position:absolute;bottom:-160px;left:-120px;width:560px;height:560px;border-radius:50%;
  background:radial-gradient(circle, rgba(220,38,38,.28) 0%, transparent 70%);
}
.content{
  position:relative;z-index:10;
  height:100%;padding:70px 80px;
  display:flex;flex-direction:column;justify-content:space-between;
}
.brand{height:58px;object-fit:contain;filter:drop-shadow(0 2px 8px rgba(0,0,0,.6))}
.bottom{max-width:920px}
.badge{
  display:inline-block;margin-bottom:26px;
  padding:9px 24px;border-radius:999px;
  background:#dc2626;color:#fff;
  font-size:20px;font-weight:400;letter-spacing:3px;text-transform:uppercase;
  box-shadow:0 6px 22px rgba(220,38,38,.4);
}
.title{
  color:#fff;font-size:60px;line-height:1.12;font-weight:400;
  text-shadow:0 3px 18px rgba(0,0,0,.7);
  display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden;
}
/* Watermark bản quyền — góc dưới phải */
.watermark{
  position:absolute;bottom:34px;right:80px;z-index:20;
  display:flex;align-items:center;gap:9px;opacity:.7;
}
.watermark img{height:26px;object-fit:contain}
.watermark span{font-size:16px;color:#e2e8f0;letter-spacing:1.5px}
</style></head><body>
<div class="wrap">
  <div class="overlay"></div>
  <div class="grid"></div>
  <div class="glow"></div>
  <div class="content">
    <img class="brand" src="${LOGO_B64}" alt="Bongda247" />
    <div class="bottom">
      ${categoryName ? `<div class="badge">${esc(categoryName)}</div>` : ""}
      <div class="title">${esc(title)}</div>
    </div>
  </div>
  <div class="watermark">
    <img src="${LOGO_B64}" alt="Bongda247" />
    <span>© bongda247.blog</span>
  </div>
</div>
</body></html>`;
}

// ============================================================
// QUEUE — chỉ 1 Puppeteer chạy tại 1 thời điểm
// ============================================================
let browserQueue = Promise.resolve();

export function generateNewsPreviewImage(params) {
  browserQueue = browserQueue.then(() => _generate(params)).catch(() => _generate(params));
  return browserQueue;
}

async function _generate({ title, categoryName = "", heroImageUrl = null }) {
  console.log(`🎨 Đang tạo news preview image: "${String(title).slice(0, 48)}"...`);

  const heroImageB64 = await toBase64(heroImageUrl);
  const html = buildHtml({ title, categoryName, heroImageB64 });

  const browser = await puppeteer.launch({
    executablePath: getChromiumPath(),
    args: [
      "--no-sandbox", "--disable-setuid-sandbox",
      "--disable-dev-shm-usage", "--disable-gpu",
      "--single-process", "--no-zygote",
    ],
    headless: true,
  });

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1200, height: 630, deviceScaleFactor: 1 });
    await page.setContent(html, { waitUntil: "domcontentloaded" });
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(() => new Promise((resolve) => {
      const imgs = Array.from(document.images);
      if (!imgs.length) return resolve();
      let done = 0;
      imgs.forEach((img) => {
        if (img.complete) { if (++done === imgs.length) resolve(); }
        else { img.onload = img.onerror = () => { if (++done === imgs.length) resolve(); }; }
      });
    }));

    const buffer = await page.screenshot({ type: "jpeg", quality: 88 });
    console.log(`✅ News preview image tạo xong (${Math.round(buffer.length / 1024)}KB)`);
    return buffer;
  } finally {
    await browser.close();
  }
}
