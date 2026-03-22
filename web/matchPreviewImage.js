import puppeteer from "puppeteer-core";
import { execFileSync } from "child_process";
import axios from "axios";

// Tìm đường dẫn Chromium trên hệ thống
function getChromiumPath() {
  if (process.env.PUPPETEER_EXECUTABLE_PATH) return process.env.PUPPETEER_EXECUTABLE_PATH;
  for (const bin of ["chromium", "chromium-browser", "google-chrome-stable", "google-chrome"]) {
    try { return execFileSync("which", [bin]).toString().trim(); } catch {}
  }
  return "/usr/bin/chromium";
}

// Tải ảnh → base64 data URI để nhúng thẳng vào HTML (tránh lỗi mạng trong Puppeteer)
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

// Lấy logo + cutout cầu thủ nổi bật của đội
async function fetchTeamAssets(teamName) {
  try {
    const res = await axios.get(
      `https://www.thesportsdb.com/api/v1/json/3/searchteams.php?t=${encodeURIComponent(teamName)}`,
      { timeout: 6000 }
    );
    const team = res.data?.teams?.[0];
    if (!team) return { logo: null };
    return { logo: team.strTeamBadge || null };
  } catch { return { logo: null }; }
}

async function fetchPlayerCutout(playerName) {
  if (!playerName) return null;
  try {
    const res = await axios.get(
      `https://www.thesportsdb.com/api/v1/json/3/searchplayers.php?p=${encodeURIComponent(playerName)}`,
      { timeout: 6000 }
    );
    const p = res.data?.player?.[0];
    if (!p) return null;
    return p.strCutout || p.strRender || p.strThumb || null;
  } catch { return null; }
}

// ============================================================
// HTML TEMPLATE
// ============================================================
function buildHtml({ homeTeam, awayTeam, matchTime, matchDate, leagueName,
  homeLogoB64, awayLogoB64, homePlayerB64, awayPlayerB64 }) {

  const dateStr = matchDate
    ? new Date(matchDate).toLocaleDateString("vi-VN", {
        weekday: "long", day: "2-digit", month: "2-digit", year: "numeric",
      })
    : "";

  const logoFallback = (name) =>
    `<div class="logo-fallback">${name.slice(0, 3).toUpperCase()}</div>`;

  const homeLogo  = homeLogoB64  ? `<img class="team-logo" src="${homeLogoB64}" />`  : logoFallback(homeTeam);
  const awayLogo  = awayLogoB64  ? `<img class="team-logo" src="${awayLogoB64}" />`  : logoFallback(awayTeam);
  const homePlayer = homePlayerB64 ? `<img class="player player-left"  src="${homePlayerB64}" />` : "";
  const awayPlayer = awayPlayerB64 ? `<img class="player player-right" src="${awayPlayerB64}" />` : "";

  return `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
*{margin:0;padding:0;box-sizing:border-box}
body{width:1200px;height:630px;overflow:hidden}
.wrap{
  width:1200px;height:630px;position:relative;overflow:hidden;
  background:linear-gradient(160deg,#060e1c 0%,#0c1c38 45%,#060e1c 100%);
  display:flex;align-items:center;justify-content:center;
  font-family:Arial,Liberation Sans,sans-serif;
}
/* Lưới nền */
.grid{
  position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),
    linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);
  background-size:60px 60px;
}
/* Hào quang 2 bên */
.glow{
  position:absolute;top:50%;width:560px;height:560px;border-radius:50%;
  background:radial-gradient(circle,rgba(37,99,235,.18) 0%,transparent 70%);
  transform:translateY(-50%);
}
.glow-l{left:-120px}.glow-r{right:-120px}
/* Cầu thủ */
.player{position:absolute;bottom:0;height:600px;width:430px;object-fit:contain;object-position:bottom}
.player-left{left:-20px;filter:drop-shadow(0 0 40px rgba(37,99,235,.45))}
.player-right{right:-20px;transform:scaleX(-1);filter:drop-shadow(0 0 40px rgba(37,99,235,.45))}
/* Fade mờ vào trung tâm */
.fade{position:absolute;top:0;width:240px;height:100%;z-index:5}
.fade-l{left:300px;background:linear-gradient(to right,transparent,rgba(6,14,28,.88))}
.fade-r{right:300px;background:linear-gradient(to left,transparent,rgba(6,14,28,.88))}
/* Panel trung tâm */
.center{
  position:relative;z-index:10;
  display:flex;flex-direction:column;align-items:center;gap:16px;
  min-width:340px;
}
.brand{font-size:17px;font-weight:900;letter-spacing:5px;color:#fff;text-transform:uppercase}
.brand em{color:#2563eb;font-style:normal}
.logos-row{display:flex;align-items:center;gap:20px}
.team-logo{width:76px;height:76px;object-fit:contain;filter:drop-shadow(0 4px 14px rgba(0,0,0,.65))}
.logo-fallback{
  width:76px;height:76px;border-radius:50%;border:2px solid rgba(37,99,235,.5);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-weight:700;font-size:13px;background:rgba(37,99,235,.12);
}
.vs{font-size:36px;font-weight:900;color:#fff;line-height:1;text-shadow:0 0 18px rgba(255,255,255,.25)}
.names{display:flex;align-items:center;gap:10px}
.name{
  font-size:13px;font-weight:700;color:#cbd5e1;text-transform:uppercase;
  letter-spacing:1px;text-align:center;max-width:130px;
  text-shadow:0 1px 6px rgba(0,0,0,.9);
}
.sep{color:#3b82f6;font-size:16px;font-weight:900}
.divider{
  width:220px;height:1px;
  background:linear-gradient(to right,transparent,#2563eb,transparent);
}
.time-block{text-align:center}
.time{font-size:30px;font-weight:900;color:#fff;text-shadow:0 0 14px rgba(255,255,255,.2)}
.date{font-size:12px;color:#94a3b8;margin-top:4px;text-transform:capitalize}
.league-badge{
  padding:5px 16px;border-radius:20px;
  background:rgba(37,99,235,.15);border:1px solid rgba(37,99,235,.45);
  color:#93c5fd;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;
}
</style></head><body>
<div class="wrap">
  <div class="grid"></div>
  <div class="glow glow-l"></div>
  <div class="glow glow-r"></div>
  ${homePlayer}
  <div class="fade fade-l"></div>
  <div class="center">
    <div class="brand">BONGDA<em>2</em>47</div>
    <div class="logos-row">
      ${homeLogo}
      <div class="vs">VS</div>
      ${awayLogo}
    </div>
    <div class="names">
      <span class="name">${homeTeam}</span>
      <span class="sep">—</span>
      <span class="name">${awayTeam}</span>
    </div>
    <div class="divider"></div>
    <div class="time-block">
      <div class="time">${matchTime}</div>
      ${dateStr ? `<div class="date">${dateStr}</div>` : ""}
    </div>
    <div class="league-badge">${leagueName}</div>
  </div>
  <div class="fade fade-r"></div>
  ${awayPlayer}
</div>
</body></html>`;
}

// ============================================================
// EXPORT CHÍNH
// ============================================================
export async function generateMatchPreviewImage({
  homeTeam, awayTeam, matchTime, matchDate, leagueName,
  homePlayer = null, awayPlayer = null,
}) {
  console.log(`🎨 Đang tạo preview image: ${homeTeam} vs ${awayTeam}...`);

  // Fetch song song tất cả assets
  const [homeTeamAssets, awayTeamAssets, homePlayerUrl, awayPlayerUrl] = await Promise.all([
    fetchTeamAssets(homeTeam),
    fetchTeamAssets(awayTeam),
    fetchPlayerCutout(homePlayer),
    fetchPlayerCutout(awayPlayer),
  ]);

  // Chuyển sang base64 để nhúng vào HTML
  const [homeLogoB64, awayLogoB64, homePlayerB64, awayPlayerB64] = await Promise.all([
    toBase64(homeTeamAssets.logo),
    toBase64(awayTeamAssets.logo),
    toBase64(homePlayerUrl),
    toBase64(awayPlayerUrl),
  ]);

  const html = buildHtml({
    homeTeam, awayTeam, matchTime, matchDate, leagueName,
    homeLogoB64, awayLogoB64, homePlayerB64, awayPlayerB64,
  });

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

    // Chờ tất cả ảnh render xong
    await page.evaluate(() => new Promise((resolve) => {
      const imgs = Array.from(document.images);
      if (!imgs.length) return resolve();
      let done = 0;
      imgs.forEach((img) => {
        if (img.complete) { if (++done === imgs.length) resolve(); }
        else { img.onload = img.onerror = () => { if (++done === imgs.length) resolve(); }; }
      });
    }));

    const buffer = await page.screenshot({ type: "png" });
    console.log(`✅ Preview image tạo xong (${Math.round(buffer.length / 1024)}KB)`);
    return buffer;
  } finally {
    await browser.close();
  }
}
