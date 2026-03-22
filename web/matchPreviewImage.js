import puppeteer from "puppeteer-core";
import { execFileSync } from "child_process";
import axios from "axios";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Nhúng font SVN-Hemi Head dưới dạng base64 để Puppeteer dùng offline
const FONT_B64 = fs.readFileSync(
  path.join(__dirname, "public/font/SVN-Hemi/SVN-HemiHead.woff2")
).toString("base64");

const LOGO_B64 = `data:image/svg+xml;base64,${fs.readFileSync(
  path.join(__dirname, "public/logo/logo-svg.svg")
).toString("base64")}`;

// Tìm đường dẫn Chromium trên hệ thống
function getChromiumPath() {
  if (process.env.PUPPETEER_EXECUTABLE_PATH) return process.env.PUPPETEER_EXECUTABLE_PATH;
  for (const bin of ["chromium", "chromium-browser", "google-chrome-stable", "google-chrome"]) {
    try { return execFileSync("which", [bin]).toString().trim(); } catch {}
  }
  return "/usr/bin/chromium";
}

// Map leagueCode → TheSportsDB league ID
const LEAGUE_IDS = {
  PL:  4328,  // Premier League
  CL:  4480,  // Champions League
  PD:  4335,  // La Liga
  BL1: 4331,  // Bundesliga
  SA:  4332,  // Serie A
  FL1: 4334,  // Ligue 1
};

async function fetchLeagueLogo(leagueCode) {
  const id = LEAGUE_IDS[leagueCode];
  if (!id) return null;
  try {
    const res = await axios.get(
      `https://www.thesportsdb.com/api/v1/json/3/lookupleague.php?id=${id}`,
      { timeout: 6000 }
    );
    return res.data?.leagues?.[0]?.strBadge || null;
  } catch { return null; }
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

// Lấy logo + màu + venueId + teamId của đội
async function fetchTeamAssets(teamName) {
  try {
    const res = await axios.get(
      `https://www.thesportsdb.com/api/v1/json/3/searchteams.php?t=${encodeURIComponent(teamName)}`,
      { timeout: 6000 }
    );
    const team = res.data?.teams?.[0];
    if (!team) return { logo: null, color1: null, color2: null, venueId: null, teamId: null };
    return {
      logo:    team.strBadge || team.strTeamBadge || null,
      color1:  team.strColour1 || null,
      color2:  team.strColour2 || null,
      venueId: team.idVenue || null,
      teamId:  team.idTeam || null,
    };
  } catch { return { logo: null, color1: null, color2: null, venueId: null, teamId: null }; }
}

// Lấy cutout tiền đạo của đội từ danh sách cầu thủ
const FORWARD_POSITIONS = ["Forward", "Striker", "Centre-Forward", "Left Wing", "Right Wing", "Second Striker"];

async function fetchTeamForwardCutout(teamId) {
  if (!teamId) return null;
  try {
    const res = await axios.get(
      `https://www.thesportsdb.com/api/v1/json/3/lookup_all_players.php?id=${teamId}`,
      { timeout: 8000 }
    );
    const players = res.data?.player;
    if (!players?.length) return null;

    // Lọc tiền đạo, ưu tiên có strCutout
    const forwards = players.filter((p) =>
      FORWARD_POSITIONS.some((pos) => p.strPosition?.includes(pos))
    );

    // Ưu tiên: có cutout → có render → có thumb
    for (const list of [forwards, players]) {
      const withCutout = list.find((p) => p.strCutout);
      if (withCutout) return withCutout.strCutout;
    }
    // Fallback: tiền đạo đầu tiên có bất kỳ ảnh nào
    const withImg = forwards.find((p) => p.strRender || p.strThumb);
    return withImg?.strRender || withImg?.strThumb || null;
  } catch { return null; }
}

async function fetchVenueData(venueId) {
  if (!venueId) return { name: null, image: null };
  try {
    const res = await axios.get(
      `https://www.thesportsdb.com/api/v1/json/3/lookupvenue.php?id=${venueId}`,
      { timeout: 6000 }
    );
    const v = res.data?.venues?.[0];
    if (!v) return { name: null, image: null };
    return {
      name:  v.strVenue || null,
      image: v.strFanart1 || v.strFanart2 || v.strThumb || null,
    };
  } catch { return { name: null, image: null }; }
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
  homeLogoB64, awayLogoB64, homePlayerB64, awayPlayerB64,
  homeColor1, awayColor1, leagueLogoB64, venueName, venueImageB64 }) {

  // Màu nền gradient hai bên — fallback về xanh navy nếu không có
  const hc = homeColor1 || "#1a3a6b";
  const ac = awayColor1 || "#1a3a6b";

  const venueBg = venueImageB64
    ? `background-image:url('${venueImageB64}');background-size:cover;background-position:center;`
    : `background:#000;`;

  const dateStr = matchDate
    ? new Date(matchDate).toLocaleDateString("vi-VN", {
        weekday: "long", day: "2-digit", month: "2-digit", year: "numeric",
      })
    : "";

  const logoFallback = (name) =>
    `<div class="logo-fallback">${name.slice(0, 3).toUpperCase()}</div>`;

  const homeLogo = homeLogoB64
    ? `<div class="logo-wrap"><img class="team-logo" src="${homeLogoB64}" /></div>`
    : `<div class="logo-wrap">${logoFallback(homeTeam)}</div>`;
  const awayLogo = awayLogoB64
    ? `<div class="logo-wrap"><img class="team-logo" src="${awayLogoB64}" /></div>`
    : `<div class="logo-wrap">${logoFallback(awayTeam)}</div>`;
  const homePlayer = homePlayerB64 ? `<img class="player player-left"  src="${homePlayerB64}" />` : "";
  const awayPlayer = awayPlayerB64 ? `<img class="player player-right" src="${awayPlayerB64}" />` : "";

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
  ${venueBg}
  display:flex;align-items:center;justify-content:center;
  font-family:'SVN-Hemi Head',Arial,sans-serif;
}
/* Overlay tối phủ lên venue image */
.venue-overlay{
  position:absolute;inset:0;
  background:rgba(0,0,0,${venueImageB64 ? "0.65" : "1"});
}
/* Màu đội — overlay mờ 2 bên */
.team-color-l{
  position:absolute;left:0;top:0;width:50%;height:100%;
  background:linear-gradient(to right,${hc}55 0%,transparent 100%);
}
.team-color-r{
  position:absolute;right:0;top:0;width:50%;height:100%;
  background:linear-gradient(to left,${ac}55 0%,transparent 100%);
}
/* Lưới nền */
.grid{
  position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),
    linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);
  background-size:60px 60px;
}
/* Hào quang 2 bên lấy đúng màu đội */
.glow{
  position:absolute;top:50%;width:560px;height:560px;border-radius:50%;
  transform:translateY(-50%);
}
.glow-l{left:-120px;background:radial-gradient(circle,${hc}44 0%,transparent 70%)}
.glow-r{right:-120px;background:radial-gradient(circle,${ac}44 0%,transparent 70%)}
/* Cầu thủ */
.player{position:absolute;bottom:0;height:600px;width:430px;object-fit:contain;object-position:bottom}
.player-left{left:-20px;filter:drop-shadow(0 0 20px ${hc}55)}
.player-right{right:-20px;transform:scaleX(-1);filter:drop-shadow(0 0 20px ${ac}55)}
/* Fade mờ vào trung tâm */
.fade{position:absolute;top:0;width:240px;height:100%;z-index:5}
.fade-l{left:300px;background:linear-gradient(to right,transparent,rgba(6,14,28,.88))}
.fade-r{right:300px;background:linear-gradient(to left,transparent,rgba(6,14,28,.88))}
/* Panel trung tâm */
.center{
  position:relative;z-index:10;
  display:flex;flex-direction:column;align-items:center;gap:22px;
  min-width:440px;
}
.brand{height:80px;object-fit:contain;filter:drop-shadow(0 2px 8px rgba(0,0,0,.5))}
/* Watermark bản quyền — góc dưới phải */
.watermark{
  position:absolute;bottom:16px;left:50%;z-index:20;transform:translateX(-50%);
  display:flex;align-items:center;gap:8px;
  opacity:0.55;
}
.watermark img{height:22px;object-fit:contain}
.watermark span{
  font-size:13px;font-weight:400;color:#fff;letter-spacing:1px;
  font-family:'SVN-Hemi Head',Arial,sans-serif;
}
/* Team card: logo + tên theo cột */
.teams-row{display:flex;align-items:center;gap:28px}
.team-card{
  display:flex;flex-direction:column;align-items:center;gap:14px;
  width:190px;
}
.logo-wrap{
  width:130px;height:130px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
}
.team-logo{
  max-width:130px;max-height:130px;width:auto;height:auto;
  object-fit:contain;filter:drop-shadow(0 4px 20px rgba(0,0,0,.7));
}
.logo-fallback{
  width:130px;height:130px;border-radius:50%;border:2px solid rgba(37,99,235,.5);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-weight:700;font-size:22px;background:rgba(37,99,235,.12);
}
.name{
  font-size:18px;font-weight:700;color:#cbd5e1;text-transform:uppercase;
  letter-spacing:1.5px;text-align:center;
  text-shadow:0 1px 6px rgba(0,0,0,.9);
  line-height:1.3;
}
.vs{font-size:64px;font-weight:900;color:#fff;line-height:1;text-shadow:0 0 28px rgba(255,255,255,.3)}
.dividers{display:flex;width:340px}
.divider-l{flex:1;height:1px;background:linear-gradient(to left,${hc},transparent)}
.divider-r{flex:1;height:1px;background:linear-gradient(to right,${ac},transparent)}
.time-block{text-align:center}
.time{font-size:52px;font-weight:900;color:#fff;text-shadow:0 0 20px rgba(255,255,255,.25)}
.date{font-size:15px;color:#94a3b8;margin-top:6px;text-transform:capitalize}
.league-badge{
  padding:8px 26px;border-radius:20px;
  background:rgba(37,99,235,.15);border:1px solid rgba(37,99,235,.45);
  color:#93c5fd;font-size:14px;font-weight:700;letter-spacing:2px;text-transform:uppercase;
}
.league-logo{width:60px;height:60px;object-fit:contain;filter:drop-shadow(0 2px 8px rgba(0,0,0,.6))}
.venue-name{
  font-size:12px;color:#64748b;letter-spacing:1.5px;text-transform:uppercase;
  display:flex;align-items:center;gap:6px;
}
.venue-name::before,.venue-name::after{
  content:"";display:block;width:20px;height:1px;background:#334155;
}
</style></head><body>
<div class="wrap">
  <div class="venue-overlay"></div>
  <div class="team-color-l"></div>
  <div class="team-color-r"></div>
  <div class="grid"></div>
  <div class="glow glow-l"></div>
  <div class="glow glow-r"></div>
  ${homePlayer}
  <div class="fade fade-l"></div>
  <div class="center">
    <img class="brand" src="${LOGO_B64}" alt="Bongda247" />
    <div class="teams-row">
      <div class="team-card">
        ${homeLogo}
        <span class="name">${homeTeam}</span>
      </div>
      <div class="vs">VS</div>
      <div class="team-card">
        ${awayLogo}
        <span class="name">${awayTeam}</span>
      </div>
    </div>
    <div class="dividers"><div class="divider-l"></div><div class="divider-r"></div></div>
    <div class="time-block">
      <div class="time">${matchTime}</div>
      ${dateStr ? `<div class="date">${dateStr}</div>` : ""}
    </div>
    ${venueName ? `<div class="venue-name">${venueName}</div>` : ""}
    ${leagueLogoB64
      ? `<img class="league-logo" src="${leagueLogoB64}" alt="${leagueName}" />`
      : `<div class="league-badge">${leagueName}</div>`
    }
  </div>
  <div class="fade fade-r"></div>
  ${awayPlayer}
  <div class="watermark">
    <img src="${LOGO_B64}" alt="Bongda247" />
    <span>© bongda247.blog</span>
  </div>
</div>
</body></html>`;
}

// ============================================================
// QUEUE — đảm bảo chỉ 1 Puppeteer chạy tại 1 thời điểm
// ============================================================
let browserQueue = Promise.resolve();

// ============================================================
// EXPORT CHÍNH
// ============================================================
export function generateMatchPreviewImage(params) {
  // Xếp hàng: chờ lần trước xong mới chạy tiếp
  browserQueue = browserQueue.then(() => _generate(params)).catch(() => _generate(params));
  return browserQueue;
}

async function _generate({
  homeTeam, awayTeam, matchTime, matchDate, leagueName,
  leagueCode = null, homePlayer = null, awayPlayer = null,
}) {
  console.log(`🎨 Đang tạo preview image: ${homeTeam} vs ${awayTeam}...`);

  // Bước 1: Fetch team assets + league logo song song
  const [homeTeamAssets, awayTeamAssets, leagueLogoUrl] = await Promise.all([
    fetchTeamAssets(homeTeam),
    fetchTeamAssets(awayTeam),
    fetchLeagueLogo(leagueCode),
  ]);

  // Bước 2: Fetch cầu thủ — dùng tên nếu có, ngược lại tự tìm tiền đạo theo teamId
  const [homePlayerUrl, awayPlayerUrl] = await Promise.all([
    homePlayer
      ? fetchPlayerCutout(homePlayer)
      : fetchTeamForwardCutout(homeTeamAssets.teamId),
    awayPlayer
      ? fetchPlayerCutout(awayPlayer)
      : fetchTeamForwardCutout(awayTeamAssets.teamId),
  ]);

  // Lấy thông tin sân vận động từ đội nhà
  const venueData = await fetchVenueData(homeTeamAssets.venueId);

  // Chuyển sang base64 để nhúng vào HTML
  const [homeLogoB64, awayLogoB64, homePlayerB64, awayPlayerB64, leagueLogoB64, venueImageB64] = await Promise.all([
    toBase64(homeTeamAssets.logo),
    toBase64(awayTeamAssets.logo),
    toBase64(homePlayerUrl),
    toBase64(awayPlayerUrl),
    toBase64(leagueLogoUrl),
    toBase64(venueData.image),
  ]);

  const html = buildHtml({
    homeTeam, awayTeam, matchTime, matchDate, leagueName,
    homeLogoB64, awayLogoB64, homePlayerB64, awayPlayerB64,
    homeColor1: homeTeamAssets.color1,
    awayColor1: awayTeamAssets.color1,
    leagueLogoB64,
    venueName: venueData.name,
    venueImageB64,
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
    await page.evaluate(() => document.fonts.ready);

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
