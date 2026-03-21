import "dotenv/config";
import { Telegraf } from "telegraf";
import { message } from "telegraf/filters";
import { GoogleGenerativeAI } from "@google/generative-ai";
import { createClient } from "@sanity/client";
import axios from "axios";
import cron from "node-cron";

// ============================================================
// 1. KHỞI TẠO
// ============================================================

const createSlug = (text) =>
  text
    .toString()
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[đĐ]/g, "d")
    .replace(/[^\w\s-]/g, "")
    .trim()
    .replace(/\s+/g, "-")
    .replace(/-+/g, "-");

const sanity = createClient({
  projectId: process.env.SANITY_PROJECT_ID,
  dataset: process.env.SANITY_DATASET || "production",
  token: process.env.SANITY_API_TOKEN,
  useCdn: false,
  apiVersion: "2024-03-03",
});

const genAI = new GoogleGenerativeAI(process.env.GEMINI_API_KEY);
const model = genAI.getGenerativeModel({ model: "gemini-2.5-flash" });
const bot = new Telegraf(process.env.TELEGRAM_BOT_TOKEN);

// Chat ID nhận bản nháp hàng ngày — set TELEGRAM_OWNER_CHAT_ID trong .env
const OWNER_CHAT_ID = process.env.TELEGRAM_OWNER_CHAT_ID;

// ============================================================
// 2. API-FOOTBALL.COM — Lấy lịch thi đấu
// ============================================================

const AF_BASE = "https://v3.football.api-sports.io";
const AF_HEADERS = { "x-apisports-key": process.env.API_FOOTBALL_KEY };

// Map mã giải → Sanity slug + thông tin hiển thị + ID API-Football
const LEAGUE_MAP = {
  PL:  { slug: "ngoai-hang-anh",   name: "Ngoại hạng Anh",  flag: "🏴󠁧󠁢󠁥󠁮󠁧󠁿", apiId: 39  },
  CL:  { slug: "champions-league", name: "Champions League", flag: "⭐",          apiId: 2   },
  PD:  { slug: "la-liga",          name: "La Liga",          flag: "🇪🇸",         apiId: 140 },
  BL1: { slug: "bundesliga",       name: "Bundesliga",       flag: "🇩🇪",         apiId: 78  },
  SA:  { slug: "serie-a",          name: "Serie A",          flag: "🇮🇹",         apiId: 135 },
  FL1: { slug: "ligue-1",          name: "Ligue 1",          flag: "🇫🇷",         apiId: 61  },
};

// Reverse map: API-Football league ID → mã giải
const LEAGUE_ID_TO_CODE = Object.fromEntries(
  Object.entries(LEAGUE_MAP).map(([code, info]) => [info.apiId, code])
);

// Lấy season hiện tại (tháng 8+ = năm hiện tại, còn lại = năm trước)
function getCurrentSeason() {
  const now = new Date();
  return now.getMonth() + 1 >= 8 ? now.getFullYear() : now.getFullYear() - 1;
}

// Chuyển utcDate → "HH:mm - DD/MM" (múi giờ Việt Nam)
function formatMatchTime(utcDateStr) {
  const date = new Date(utcDateStr);
  // Nếu giờ/phút UTC đều là 0 → thường là TBD, chỉ hiển thị ngày
  if (date.getUTCHours() === 0 && date.getUTCMinutes() === 0) {
    return date.toLocaleDateString("vi-VN", {
      timeZone: "Asia/Ho_Chi_Minh",
      day: "2-digit",
      month: "2-digit",
    });
  }
  const parts = new Intl.DateTimeFormat("vi-VN", {
    timeZone: "Asia/Ho_Chi_Minh",
    hour: "2-digit",
    minute: "2-digit",
    day: "2-digit",
    month: "2-digit",
    hour12: false,
  }).formatToParts(date);
  const get = (t) => parts.find((p) => p.type === t)?.value ?? "00";
  return `${get("hour")}:${get("minute")} - ${get("day")}/${get("month")}`;
}

async function fetchMatchesForDate(dateStr) {
  const res = await axios.get(`${AF_BASE}/fixtures`, {
    headers: AF_HEADERS,
    params: { date: dateStr },
    timeout: 10000,
  });
  // Normalize sang cấu trúc thống nhất dùng chung trong bot
  return (res.data.response ?? [])
    .map((item) => ({
      homeTeam: { id: item.teams.home.id, name: item.teams.home.name },
      awayTeam: { id: item.teams.away.id, name: item.teams.away.name },
      utcDate: item.fixture.date,
      competition: { code: LEAGUE_ID_TO_CODE[item.league.id] },
    }))
    .filter((m) => m.competition.code); // bỏ giải không theo dõi
}

// Lấy bảng xếp hạng thực tế → { teamId: { position, points, form, won, draw, lost, gf, ga } }
async function fetchStandings(leagueCode) {
  try {
    const leagueId = LEAGUE_MAP[leagueCode]?.apiId;
    if (!leagueId) return {};
    const res = await axios.get(`${AF_BASE}/standings`, {
      headers: AF_HEADERS,
      params: { league: leagueId, season: getCurrentSeason() },
      timeout: 10000,
    });
    const table = res.data.response?.[0]?.league?.standings?.[0] ?? [];
    const lookup = {};
    for (const row of table) {
      lookup[row.team.id] = {
        name: row.team.name,
        position: row.rank,
        points: row.points,
        played: row.all.played,
        won: row.all.win,
        draw: row.all.draw,
        lost: row.all.lose,
        form: row.form ?? "N/A",
        goalsFor: row.all.goals.for,
        goalsAgainst: row.all.goals.against,
      };
    }
    return lookup;
  } catch {
    return {}; // CL knockout / lỗi → fallback về AI tự phân tích
  }
}

// Lấy lịch sử đối đầu 10 trận gần nhất → raw fixtures array
async function fetchHeadToHead(homeId, awayId) {
  try {
    const res = await axios.get(`${AF_BASE}/fixtures/headtohead`, {
      headers: AF_HEADERS,
      params: { h2h: `${homeId}-${awayId}`, last: 10 },
      timeout: 10000,
    });
    return res.data.response ?? [];
  } catch {
    return [];
  }
}

// Lấy 10 trận gần nhất của một đội
async function fetchTeamFixtures(teamId) {
  try {
    const res = await axios.get(`${AF_BASE}/fixtures`, {
      headers: AF_HEADERS,
      params: { team: teamId, last: 10, season: getCurrentSeason() },
      timeout: 10000,
    });
    return res.data.response ?? [];
  } catch {
    return [];
  }
}

// Tính stats từ danh sách fixture của một đội
// teamId dùng để xác định đội đang xét là home hay away trong từng trận
function computeTeamStats(fixtures, teamId) {
  const homeGames = fixtures.filter((f) => f.teams.home.id === teamId);
  const awayGames = fixtures.filter((f) => f.teams.away.id === teamId);

  const calc = (games) => {
    const n = games.length;
    if (!n) return null;
    const over25 = games.filter((g) => (g.goals.home ?? 0) + (g.goals.away ?? 0) > 2.5).length;
    const btts   = games.filter((g) => (g.goals.home ?? 0) > 0 && (g.goals.away ?? 0) > 0).length;
    const cs     = games.filter((g) => {
      const isHome = g.teams.home.id === teamId;
      return isHome ? (g.goals.away ?? 0) === 0 : (g.goals.home ?? 0) === 0;
    }).length;
    return { n, over25, btts, cs };
  };

  return { home: calc(homeGames), away: calc(awayGames), all: calc(fixtures) };
}

// Tính stats từ các trận H2H
function computeH2HStats(fixtures) {
  const n = fixtures.length;
  if (!n) return null;
  const over25 = fixtures.filter((f) => (f.goals.home ?? 0) + (f.goals.away ?? 0) > 2.5).length;
  const btts   = fixtures.filter((f) => (f.goals.home ?? 0) > 0 && (f.goals.away ?? 0) > 0).length;
  return { n, over25, btts };
}

// Format stats thành chuỗi ngắn gọn cho prompt
function formatTeamStatsForPrompt(stats, teamName, isHomeTeam) {
  if (!stats) return `${teamName}: không có dữ liệu form`;
  const lines = [];
  const venue = isHomeTeam ? stats.home : stats.away;
  const all   = stats.all;

  if (venue?.n >= 3) {
    lines.push(`${isHomeTeam ? "Sân nhà" : "Sân khách"} (${venue.n} trận): Over 2.5: ${venue.over25}/${venue.n}, BTTS: ${venue.btts}/${venue.n}, Sạch lưới: ${venue.cs}/${venue.n}`);
  }
  if (all?.n >= 5) {
    lines.push(`Tổng (${all.n} trận): Over 2.5: ${all.over25}/${all.n}, BTTS: ${all.btts}/${all.n}`);
  }
  return lines.length
    ? `${teamName}: ${lines.join(" | ")}`
    : `${teamName}: không đủ dữ liệu`;
}

// Lọc các giải hỗ trợ, tối đa 3 trận/giải
function selectMatches(matches) {
  const grouped = {};
  for (const match of matches) {
    const code = match.competition?.code;
    if (!LEAGUE_MAP[code]) continue;
    if (!grouped[code]) grouped[code] = [];
    if (grouped[code].length < 3) grouped[code].push(match);
  }
  return Object.entries(grouped).flatMap(([code, list]) =>
    list.map((m) => ({ ...m, leagueCode: code }))
  );
}

// ============================================================
// 3. DYNAMIC CATEGORIES — tải từ Sanity, không hardcode ID
// ============================================================

let CATEGORIES = {};

async function loadCategories() {
  try {
    const cats = await sanity.fetch(
      `*[_type == "category"]{ _id, "slug": slug.current, title }`
    );
    CATEGORIES = {};
    cats.forEach((c) => {
      CATEGORIES[c.slug] = { id: c._id, title: c.title };
    });
    console.log(
      `✅ Đã tải ${Object.keys(CATEGORIES).length} danh mục:`,
      Object.keys(CATEGORIES).join(", ")
    );
  } catch (e) {
    console.error("❌ Không tải được danh mục:", e.message);
  }
}

function getCategoryId(slug) {
  return CATEGORIES[slug]?.id ?? Object.values(CATEGORIES)[0]?.id ?? null;
}

// ============================================================
// 4. BỘ NHỚ TẠM
// ============================================================

const mediaGroupStorage = new Map(); // groupId → { photos: [], caption: null }
const mediaGroupTimers = new Map();  // groupId → timer
const pendingPosts = new Map();      // chatId → post đang chờ xác nhận
const draftStore = new Map();        // draftId → bản nháp nhận định từ AI

// ============================================================
// 5. HELPER FUNCTIONS
// ============================================================

function parseAIJson(text) {
  return JSON.parse(
    text.replace(/```json/gi, "").replace(/```/g, "").trim()
  );
}

// Escape ký tự đặc biệt Markdown V1 trong nội dung động (AI có thể tạo ra)
function escapeMd(text) {
  return String(text ?? "").replace(/[*_`[]/g, "\\$&");
}

function buildInsightText(data) {
  const hotStatus = data.hot ? "🔥 CÓ" : "❄️ KHÔNG";
  return (
    `🏟 *${escapeMd(data.homeTeam)}* vs *${escapeMd(data.awayTeam)}*\n` +
    `⏰ ${escapeMd(data.matchTime)}  •  🔥 HOT: ${hotStatus}\n\n` +
    `📊 *Nhận định:*\n${data.insights.map((i) => `• ${escapeMd(i)}`).join("\n")}\n\n` +
    `🎯 *Dự đoán:* ${escapeMd(data.prediction)}`
  );
}

function buildInsightKeyboard(data) {
  const hotStatus = data.hot ? "🔥 CÓ" : "❄️ KHÔNG";
  return {
    inline_keyboard: [
      [{ text: `🔄 Đổi HOT (Hiện: ${hotStatus})`, callback_data: "toggle_hot" }],
      [{ text: "🚀 Đăng lên Slide", callback_data: "confirm_insight" }],
      [{ text: "❌ Hủy", callback_data: "cancel_post" }],
    ],
  };
}

// Keyboard cho bản nháp daily preview — dùng draftId để phân biệt từng trận
function buildDraftKeyboard(draftId, hot) {
  const hotStatus = hot ? "🔥 CÓ" : "❄️ KHÔNG";
  return {
    inline_keyboard: [
      [{ text: `🔄 Đổi HOT (Hiện: ${hotStatus})`, callback_data: `dtoggle_${draftId}` }],
      [{ text: "✅ Đăng lên Slide", callback_data: `dapprove_${draftId}` }],
      [{ text: "⏭ Bỏ qua", callback_data: `dskip_${draftId}` }],
    ],
  };
}

function buildPortableText(sections, assetIds) {
  return sections.flatMap((section, i) => {
    const blocks = [
      {
        _type: "block",
        _key: `h2-${i}-${Date.now()}`,
        style: "h2",
        markDefs: [],
        children: [{ _type: "span", _key: `sh-${i}`, text: section.heading }],
      },
      {
        _type: "block",
        _key: `p-${i}-${Date.now() + i}`,
        style: "normal",
        markDefs: [],
        children: [{ _type: "span", _key: `sp-${i}`, text: section.text }],
      },
    ];
    if (assetIds[i + 1]) {
      blocks.push({
        _type: "image",
        _key: `img-${i}-${Date.now()}`,
        asset: { _type: "reference", _ref: assetIds[i + 1] },
      });
    }
    return blocks;
  });
}

async function uploadPhotos(ctx, photos) {
  return Promise.all(
    photos.map(async (fileId) => {
      const link = await ctx.telegram.getFileLink(fileId);
      const res = await axios.get(link.href, { responseType: "arraybuffer" });
      const asset = await sanity.assets.upload("image", Buffer.from(res.data));
      return asset._id;
    })
  );
}

function delay(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

// ============================================================
// 6. AI — TẠO NHẬN ĐỊNH CHO MỘT TRẬN
// ============================================================

// standings: object từ fetchStandings
// h2hFixtures: raw array từ fetchHeadToHead
// homeFixtures/awayFixtures: raw array từ fetchTeamFixtures
async function generateDraftForMatch(match, standings = {}, h2hFixtures = [], homeFixtures = [], awayFixtures = []) {
  const leagueInfo = LEAGUE_MAP[match.leagueCode];
  const homeTeam = match.homeTeam?.name ?? "Đội nhà";
  const awayTeam = match.awayTeam?.name ?? "Đội khách";
  const matchTime = formatMatchTime(match.utcDate);

  // BXH
  const homeStanding = standings[match.homeTeam?.id];
  const awayStanding = standings[match.awayTeam?.id];
  const formatStanding = (s, name) =>
    s
      ? `${name}: Hạng ${s.position}, ${s.points}đ (${s.won}W-${s.draw}D-${s.lost}L), form: ${s.form}`
      : `${name}: không có dữ liệu BXH`;

  // Form stats từ fixture gần nhất
  const homeTeamStats = computeTeamStats(homeFixtures, match.homeTeam?.id);
  const awayTeamStats = computeTeamStats(awayFixtures, match.awayTeam?.id);
  const h2hStats = computeH2HStats(h2hFixtures);

  // Tóm tắt H2H 5 trận gần nhất dạng text
  const h2hSummary = h2hFixtures.slice(0, 5)
    .map((f) => `${f.teams.home.name} ${f.goals.home ?? 0}-${f.goals.away ?? 0} ${f.teams.away.name}`)
    .join(", ") || "không có dữ liệu";

  const dataContext = `
DỮ LIỆU THỰC TẾ (ưu tiên dùng số liệu này, KHÔNG bịa):
BXH:
- ${formatStanding(homeStanding, homeTeam)}
- ${formatStanding(awayStanding, awayTeam)}

Form gần đây:
- ${formatTeamStatsForPrompt(homeTeamStats, homeTeam, true)}
- ${formatTeamStatsForPrompt(awayTeamStats, awayTeam, false)}

H2H (${h2hFixtures.length} trận): ${h2hSummary}
H2H stats: Over 2.5: ${h2hStats ? `${h2hStats.over25}/${h2hStats.n}` : "N/A"}, BTTS: ${h2hStats ? `${h2hStats.btts}/${h2hStats.n}` : "N/A"}`;

  const prompt = `
Bạn là chuyên gia phân tích bóng đá tiếng Việt. Viết insights NGẮN GỌN, SỬ DỤNG SỐ LIỆU THỰC TẾ từ dữ liệu bên dưới.
Trận: ${homeTeam} vs ${awayTeam} | ${leagueInfo.name} | ${matchTime}
${dataContext}

Ví dụ insight tốt (dùng số liệu thực):
- "🏠 ${homeTeam}: Over 2.5 sân nhà - X/Y trận cuối"
- "✈️ ${awayTeam}: BTTS sân khách - X/Y trận cuối"
- "⚔️ H2H: BTTS - X/Y trận gần nhất"
- "🔑 [yếu tố then chốt dựa trên data]"

Trả về DUY NHẤT JSON hợp lệ:
{
  "homeTeam": "${homeTeam}",
  "awayTeam": "${awayTeam}",
  "matchTime": "${matchTime}",
  "hot": false,
  "insights": [
    "🏠 [insight đội nhà với số liệu thực, tối đa 12 từ]",
    "✈️ [insight đội khách với số liệu thực, tối đa 12 từ]",
    "⚔️ [insight H2H với số liệu thực, tối đa 12 từ]",
    "🔑 [yếu tố quyết định, tối đa 12 từ]"
  ],
  "prediction": "Tỉ số dự đoán + lý do ngắn (tối đa 10 từ)"
}`;

  const result = await model.generateContent(prompt);
  const data = parseAIJson(result.response.text());

  return {
    ...data,
    leagueCode: match.leagueCode,
    leagueSlug: leagueInfo.slug,
    leagueName: leagueInfo.name,
    leagueFlag: leagueInfo.flag,
    matchDate: match.utcDate, // ISO datetime thực tế — dùng để auto-delete
  };
}

// ============================================================
// 7. DAILY PREVIEW — Gửi bản nháp nhận định hàng ngày
// ============================================================

async function runDailyPreview(targetChatId, dateOffset = 0) {
  if (!targetChatId) {
    console.warn("⚠️ Chưa set TELEGRAM_OWNER_CHAT_ID trong .env");
    return;
  }

  const target = new Date();
  target.setDate(target.getDate() + dateOffset);
  // Dùng sv-SE locale để lấy YYYY-MM-DD theo giờ Việt Nam (tránh lệch ngày UTC)
  const dateStr = target.toLocaleDateString("sv-SE", { timeZone: "Asia/Ho_Chi_Minh" });
  const dateVN = target.toLocaleDateString("vi-VN", {
    timeZone: "Asia/Ho_Chi_Minh",
  });

  await bot.telegram.sendMessage(
    targetChatId,
    `🗓 *Nhận định ngày ${dateVN}*\n_Đang tải lịch thi đấu..._`,
    { parse_mode: "Markdown" }
  );

  // Lấy lịch thi đấu
  let matches;
  try {
    const allMatches = await fetchMatchesForDate(dateStr);
    matches = selectMatches(allMatches);
  } catch (e) {
    await bot.telegram.sendMessage(
      targetChatId,
      `❌ Không lấy được lịch: ${e.message}`
    );
    return;
  }

  if (!matches.length) {
    await bot.telegram.sendMessage(
      targetChatId,
      "📭 Hôm nay không có trận nào trong các giải theo dõi."
    );
    return;
  }

  // Group theo giải
  const byLeague = {};
  for (const m of matches) {
    if (!byLeague[m.leagueCode]) byLeague[m.leagueCode] = [];
    byLeague[m.leagueCode].push(m);
  }

  const totalMatches = matches.length;
  const leagueCount = Object.keys(byLeague).length;
  await bot.telegram.sendMessage(
    targetChatId,
    `✅ *${totalMatches} trận* từ *${leagueCount} giải*\n_Đang tải BXH + tạo nhận định..._`,
    { parse_mode: "Markdown" }
  );

  // Fetch BXH cho từng giải (data thực tế từ API)
  const standingsMap = {}; // leagueCode → { teamId: stats }
  for (const code of Object.keys(byLeague)) {
    standingsMap[code] = await fetchStandings(code);
    await delay(2000);
  }

  // Xử lý từng giải
  for (const [code, leagueMatches] of Object.entries(byLeague)) {
    const leagueInfo = LEAGUE_MAP[code];

    for (const match of leagueMatches) {
      try {
        const h2hFixtures   = await fetchHeadToHead(match.homeTeam?.id, match.awayTeam?.id);
        await delay(2000);
        const homeFixtures  = await fetchTeamFixtures(match.homeTeam?.id);
        await delay(2000);
        const awayFixtures  = await fetchTeamFixtures(match.awayTeam?.id);
        await delay(2000);
        const draft = await generateDraftForMatch(match, standingsMap[code], h2hFixtures, homeFixtures, awayFixtures);
        const draftId = `d_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`;
        draftStore.set(draftId, draft);

        const header = `${leagueInfo.flag} *${escapeMd(leagueInfo.name)}*\n`;
        await bot.telegram.sendMessage(
          targetChatId,
          header + buildInsightText(draft),
          {
            parse_mode: "Markdown",
            reply_markup: buildDraftKeyboard(draftId, draft.hot),
          }
        );

        // Delay 2s giữa các lần gọi Gemini tránh rate limit
        await delay(2000);
      } catch (e) {
        await bot.telegram.sendMessage(
          targetChatId,
          `⚠️ Lỗi tạo nhận định *${match.homeTeam?.name}* vs *${match.awayTeam?.name}*: ${e.message}`,
          { parse_mode: "Markdown" }
        );
      }
    }
  }

  await bot.telegram.sendMessage(
    targetChatId,
    "✅ Xong! Chọn các trận muốn đăng bên trên.",
  );
}

// ============================================================
// 8. CORE — Xử lý INSIGHT thủ công
// ============================================================

async function processInsight(ctx, rawText) {
  const currentDateStr = new Date().toLocaleDateString("vi-VN");
  await ctx.reply("📊 Đang trích xuất số liệu...");

  const prompt = `
Bạn là chuyên gia phân tích bóng đá. Hôm nay là ${currentDateStr}.
Trích xuất thông tin trận đấu từ nội dung: "${rawText}"

Quy tắc matchTime:
- Chỉ có ngày → "DD/MM"  |  Có cả giờ → "HH:mm - DD/MM"

Trả về DUY NHẤT JSON hợp lệ:
{
  "homeTeam": "...", "awayTeam": "...", "matchTime": "...",
  "hot": false,
  "insights": ["...", "...", "...", "..."],
  "prediction": "..."
}`;

  const result = await model.generateContent(prompt);
  const insightData = parseAIJson(result.response.text());

  pendingPosts.set(ctx.chat.id, { ...insightData, type: "matchInsight" });

  await ctx.reply(buildInsightText(insightData), {
    parse_mode: "Markdown",
    reply_markup: buildInsightKeyboard(insightData),
  });
}

// ============================================================
// 9. CORE — Xử lý BÀI VIẾT thủ công
// ============================================================

async function processArticle(ctx, rawText, photos) {
  const currentDateStr = new Date().toLocaleDateString("vi-VN");
  await ctx.reply("⏳ AI đang soạn bài viết...");

  const availableSlugs = Object.keys(CATEGORIES).join(", ");

  const prompt = `
Bạn là biên tập viên bóng đá chuyên nghiệp viết tiếng Việt. Hôm nay là ${currentDateStr}.
Viết bài báo bóng đá chuẩn SEO từ nội dung: "${rawText}"

Yêu cầu:
- Chọn "league" từ danh sách: [${availableSlugs}] — tự nhận diện giải đấu từ nội dung
- 3–5 sections, mỗi section: heading h2 + đoạn text 80–150 từ tiếng Việt
- Tiêu đề hấp dẫn, excerpt 1–2 câu

Trả về DUY NHẤT JSON hợp lệ:
{
  "title": "...", "excerpt": "...", "league": "slug-giai-dau",
  "sections": [{ "heading": "...", "text": "..." }]
}`;

  const result = await model.generateContent(prompt);
  const data = parseAIJson(result.response.text());

  const categoryId = getCategoryId(data.league);
  const leagueTitle = CATEGORIES[data.league]?.title ?? data.league;

  pendingPosts.set(ctx.chat.id, { ...data, categoryId, photos, type: "post" });

  await ctx.reply(
    `📝 *${data.title}*\n\n📌 _${data.excerpt}_\n\n🏆 Giải: *${leagueTitle}*\n📸 Ảnh: ${photos.length}`,
    {
      parse_mode: "Markdown",
      reply_markup: {
        inline_keyboard: [
          [{ text: "✅ Đăng ngay", callback_data: "confirm_post" }],
          [{ text: "❌ Hủy", callback_data: "cancel_post" }],
        ],
      },
    }
  );
}

// ============================================================
// 10. COMMANDS
// ============================================================

bot.start((ctx) =>
  ctx.reply(
    `⚽ *Bongda247 Bot*\n\n🔑 *Chat ID của bạn:* \`${ctx.chat.id}\`\n\n` +
      "📌 *Gửi thủ công:*\n" +
      "• Text/ảnh → soạn bài viết tự động\n" +
      "• Có từ *INSIGHT* → tạo số liệu trận đấu\n\n" +
      "📋 *Lệnh:*\n" +
      "/preview — Nhận định trận hôm nay\n" +
      "/tomorrow — Nhận định trận ngày mai\n" +
      "/list — Xem & xóa insights đang hiển thị\n" +
      "/posts — Xem & xóa bài viết gần đây\n" +
      "/reload — Tải lại danh mục từ Sanity",
    { parse_mode: "Markdown" }
  )
);

bot.command("reload", async (ctx) => {
  await loadCategories();
  const list = Object.entries(CATEGORIES)
    .map(([slug, cat]) => `• \`${slug}\` — ${cat.title}`)
    .join("\n");
  ctx.reply(
    `✅ Đã tải lại *${Object.keys(CATEGORIES).length}* danh mục:\n${list}`,
    { parse_mode: "Markdown" }
  );
});

bot.command("preview", async (ctx) => {
  const chatId = ctx.chat.id.toString();
  await ctx.reply("🔄 Đang tải nhận định hôm nay...");
  runDailyPreview(chatId, 0).catch((e) =>
    ctx.reply("❌ Lỗi preview: " + e.message)
  );
});

bot.command("tomorrow", async (ctx) => {
  const chatId = ctx.chat.id.toString();
  await ctx.reply("🔄 Đang tải nhận định ngày mai...");
  runDailyPreview(chatId, 1).catch((e) =>
    ctx.reply("❌ Lỗi preview: " + e.message)
  );
});

bot.command("list", async (ctx) => {
  try {
    const insights = await sanity.fetch(
      `*[_type == "matchInsight"] | order(publishedAt desc) [0...10] { _id, homeTeam, awayTeam, matchTime, hot }`
    );
    if (!insights.length) return ctx.reply("📭 Chưa có insight nào.");

    await ctx.reply(`📋 *${insights.length} Insight gần nhất:*`, {
      parse_mode: "Markdown",
    });
    for (const item of insights) {
      const badge = item.hot ? "🔥" : "⚽";
      await ctx.reply(
        `${badge} *${item.homeTeam}* vs *${item.awayTeam}*  ⏰ ${item.matchTime}`,
        {
          parse_mode: "Markdown",
          reply_markup: {
            inline_keyboard: [
              [{ text: "🗑 Xóa", callback_data: `delete_insight_${item._id}` }],
            ],
          },
        }
      );
    }
  } catch (e) {
    ctx.reply("❌ Lỗi: " + e.message);
  }
});

bot.command("posts", async (ctx) => {
  try {
    const posts = await sanity.fetch(
      `*[_type == "post"] | order(publishedAt desc) [0...8] { _id, title, publishedAt, "category": category->title }`
    );
    if (!posts.length) return ctx.reply("📭 Chưa có bài viết nào.");

    await ctx.reply(`📋 *${posts.length} bài viết gần nhất:*`, {
      parse_mode: "Markdown",
    });
    for (const post of posts) {
      const date = new Date(post.publishedAt).toLocaleDateString("vi-VN");
      await ctx.reply(
        `📰 *${post.title}*\n🏆 ${post.category ?? "—"}  📅 ${date}`,
        {
          parse_mode: "Markdown",
          reply_markup: {
            inline_keyboard: [
              [{ text: "🗑 Xóa", callback_data: `delete_post_${post._id}` }],
            ],
          },
        }
      );
    }
  } catch (e) {
    ctx.reply("❌ Lỗi: " + e.message);
  }
});

// ============================================================
// 11. MESSAGE HANDLER
// ============================================================

async function handleMessage(ctx) {
  const message = ctx.message;
  const rawText = message.caption || message.text || "";

  // Album ảnh — fix race condition với setTimeout
  if (message.media_group_id) {
    const groupId = message.media_group_id;
    if (!mediaGroupStorage.has(groupId))
      mediaGroupStorage.set(groupId, { photos: [], caption: null });

    const group = mediaGroupStorage.get(groupId);
    group.photos.push(message.photo[message.photo.length - 1].file_id);
    if (message.caption) group.caption = message.caption;

    if (mediaGroupTimers.has(groupId)) clearTimeout(mediaGroupTimers.get(groupId));

    const timer = setTimeout(async () => {
      const groupData = mediaGroupStorage.get(groupId);
      mediaGroupStorage.delete(groupId);
      mediaGroupTimers.delete(groupId);
      if (!groupData.caption) return;
      try {
        if (groupData.caption.toUpperCase().includes("INSIGHT")) {
          await processInsight(ctx, groupData.caption);
        } else {
          await processArticle(ctx, groupData.caption, groupData.photos);
        }
      } catch (e) {
        ctx.reply("❌ Lỗi xử lý album: " + e.message);
      }
    }, 1500);

    mediaGroupTimers.set(groupId, timer);
    return;
  }

  if (!rawText) return;

  const photos = message.photo
    ? [message.photo[message.photo.length - 1].file_id]
    : [];

  try {
    if (rawText.toUpperCase().includes("INSIGHT")) {
      await processInsight(ctx, rawText);
    } else {
      await processArticle(ctx, rawText, photos);
    }
  } catch (e) {
    ctx.reply("❌ Lỗi: " + e.message);
  }
}

bot.on(message("text"), handleMessage);
bot.on(message("photo"), handleMessage);

// ============================================================
// 12. CALLBACK ACTIONS — thủ công (pendingPosts)
// ============================================================

bot.action("toggle_hot", async (ctx) => {
  const data = pendingPosts.get(ctx.chat.id);
  if (!data || data.type !== "matchInsight")
    return ctx.answerCbQuery("❌ Dữ liệu hết hạn!");
  data.hot = !data.hot;
  pendingPosts.set(ctx.chat.id, data);
  try {
    await ctx.editMessageText(buildInsightText(data), {
      parse_mode: "Markdown",
      reply_markup: buildInsightKeyboard(data),
    });
    ctx.answerCbQuery(`HOT: ${data.hot ? "🔥 CÓ" : "❄️ KHÔNG"}`);
  } catch {
    ctx.answerCbQuery("Đã cập nhật!");
  }
});

bot.action("confirm_insight", async (ctx) => {
  const data = pendingPosts.get(ctx.chat.id);
  if (!data || data.type !== "matchInsight")
    return ctx.reply("❌ Dữ liệu hết hạn.");
  try {
    await sanity.create({
      _type: "matchInsight",
      homeTeam: data.homeTeam,
      awayTeam: data.awayTeam,
      matchTime: data.matchTime,
      matchDate: data.matchDate ?? null,
      hot: !!data.hot,
      insights: data.insights,
      prediction: data.prediction,
      publishedAt: new Date().toISOString(),
    });
    await ctx.editMessageText(
      `✅ *Đã đăng Insight!*\n\n🏟 *${data.homeTeam}* vs *${data.awayTeam}*\n⏰ ${data.matchTime}`,
      { parse_mode: "Markdown" }
    );
    pendingPosts.delete(ctx.chat.id);
  } catch (e) {
    ctx.reply("❌ Lỗi: " + e.message);
  }
});

bot.action("confirm_post", async (ctx) => {
  const data = pendingPosts.get(ctx.chat.id);
  if (!data || data.type !== "post")
    return ctx.answerCbQuery("❌ Dữ liệu hết hạn!");
  ctx.answerCbQuery("🚀 Đang đăng bài...");
  try {
    const assetIds =
      data.photos.length > 0 ? await uploadPhotos(ctx, data.photos) : [];
    await sanity.create({
      _type: "post",
      title: data.title,
      slug: { _type: "slug", current: `${createSlug(data.title)}-${Date.now()}` },
      mainImage: assetIds[0]
        ? { _type: "image", asset: { _type: "reference", _ref: assetIds[0] } }
        : undefined,
      excerpt: data.excerpt,
      content: buildPortableText(data.sections || [], assetIds),
      category: data.categoryId
        ? { _type: "reference", _ref: data.categoryId }
        : undefined,
      publishedAt: new Date().toISOString(),
    });
    await ctx.editMessageText(
      `✅ *Đã xuất bản!*\n\n📰 ${data.title}`,
      { parse_mode: "Markdown" }
    );
    pendingPosts.delete(ctx.chat.id);
  } catch (e) {
    ctx.reply("❌ Lỗi đăng bài: " + e.message);
  }
});

bot.action("cancel_post", (ctx) => {
  pendingPosts.delete(ctx.chat.id);
  ctx.editMessageText("❌ Đã hủy bản nháp.");
});

// ============================================================
// 13. CALLBACK ACTIONS — bản nháp daily preview (draftStore)
// ============================================================

bot.on("callback_query", async (ctx) => {
  const action = ctx.callbackQuery?.data ?? "";

  // --- Toggle HOT cho bản nháp ---
  if (action.startsWith("dtoggle_")) {
    const draftId = action.replace("dtoggle_", "");
    const draft = draftStore.get(draftId);
    if (!draft) return ctx.answerCbQuery("❌ Bản nháp hết hạn!");

    draft.hot = !draft.hot;
    draftStore.set(draftId, draft);

    const leagueInfo = LEAGUE_MAP[draft.leagueCode] ?? {};
    const header = leagueInfo.flag ? `${leagueInfo.flag} *[${leagueInfo.name}]*\n` : "";
    try {
      await ctx.editMessageText(header + buildInsightText(draft), {
        parse_mode: "Markdown",
        reply_markup: buildDraftKeyboard(draftId, draft.hot),
      });
      ctx.answerCbQuery(`HOT: ${draft.hot ? "🔥 CÓ" : "❄️ KHÔNG"}`);
    } catch {
      ctx.answerCbQuery("Đã cập nhật!");
    }
    return;
  }

  // --- Duyệt đăng Insight từ bản nháp ---
  if (action.startsWith("dapprove_")) {
    const draftId = action.replace("dapprove_", "");
    const draft = draftStore.get(draftId);
    if (!draft) return ctx.answerCbQuery("❌ Bản nháp hết hạn!");

    try {
      await sanity.create({
        _type: "matchInsight",
        homeTeam: draft.homeTeam,
        awayTeam: draft.awayTeam,
        matchTime: draft.matchTime,
        matchDate: draft.matchDate ?? null,
        hot: !!draft.hot,
        insights: draft.insights,
        prediction: draft.prediction,
        publishedAt: new Date().toISOString(),
      });
      ctx.answerCbQuery("✅ Đã đăng lên Slide!");
      ctx.editMessageText(
        `✅ *Đã lên Slide!*\n🏟 *${draft.homeTeam}* vs *${draft.awayTeam}*  ⏰ ${draft.matchTime}\n${draft.hot ? "🔥 HOT" : ""}`,
        { parse_mode: "Markdown" }
      );
      draftStore.delete(draftId);
    } catch (e) {
      ctx.answerCbQuery("❌ Lỗi: " + e.message);
    }
    return;
  }

  // --- Bỏ qua bản nháp ---
  if (action.startsWith("dskip_")) {
    const draftId = action.replace("dskip_", "");
    draftStore.delete(draftId);
    ctx.answerCbQuery("⏭ Đã bỏ qua");
    ctx.editMessageText("⏭ Bỏ qua.", { parse_mode: "Markdown" });
    return;
  }

  // --- Xóa Insight đã đăng ---
  if (action.startsWith("delete_insight_")) {
    const id = action.replace("delete_insight_", "");
    try {
      await sanity.delete(id);
      ctx.answerCbQuery("✅ Đã xóa!");
      ctx.editMessageText("🗑 *Đã xóa khỏi Slide.*", { parse_mode: "Markdown" });
    } catch {
      ctx.answerCbQuery("❌ Lỗi xóa.");
    }
    return;
  }

  // --- Xóa bài viết đã đăng ---
  if (action.startsWith("delete_post_")) {
    const id = action.replace("delete_post_", "");
    try {
      await sanity.delete(id);
      ctx.answerCbQuery("✅ Đã xóa!");
      ctx.editMessageText("🗑 *Bài viết đã được xóa.*", { parse_mode: "Markdown" });
    } catch {
      ctx.answerCbQuery("❌ Lỗi xóa.");
    }
  }
});

// ============================================================
// 14. CRON JOBS
// ============================================================

// 8:00 sáng — gửi nhận định hàng ngày
cron.schedule(
  "0 8 * * *",
  () => {
    console.log("⏰ Cron: Chạy daily preview...");
    runDailyPreview(OWNER_CHAT_ID, 0);
  },
  { timezone: "Asia/Ho_Chi_Minh" }
);

// 7:55 sáng — tự động xóa insight cũ (chạy trước daily preview 5 phút)
cron.schedule(
  "55 7 * * *",
  async () => {
    console.log("🧹 Cron: Dọn dẹp matchInsight cũ...");
    try {
      // Xóa insight có matchDate đã qua hơn 3 tiếng (trận chắc chắn kết thúc)
      const cutoff = new Date(Date.now() - 3 * 60 * 60 * 1000).toISOString();
      const stale = await sanity.fetch(
        `*[_type == "matchInsight" && matchDate < $cutoff]{ _id, homeTeam, awayTeam }`,
        { cutoff }
      );

      if (!stale.length) {
        console.log("✅ Không có insight nào cần xóa.");
        return;
      }

      await Promise.all(stale.map((doc) => sanity.delete(doc._id)));

      console.log(`🗑 Đã xóa ${stale.length} insight:`, stale.map((d) => `${d.homeTeam} vs ${d.awayTeam}`).join(", "));

      if (OWNER_CHAT_ID) {
        await bot.telegram.sendMessage(
          OWNER_CHAT_ID,
          `🧹 *Dọn dẹp tự động:* Đã xóa *${stale.length}* insight của các trận đã kết thúc.`,
          { parse_mode: "Markdown" }
        );
      }
    } catch (e) {
      console.error("❌ Lỗi cleanup:", e.message);
    }
  },
  { timezone: "Asia/Ho_Chi_Minh" }
);

// ============================================================
// 15. LAUNCH
// ============================================================

loadCategories().then(() => {
  bot.launch();
  console.log("🚀 Bongda247 Bot is Running...");
  console.log(
    OWNER_CHAT_ID
      ? `📨 Daily preview sẽ gửi đến chat ID: ${OWNER_CHAT_ID}`
      : "⚠️  TELEGRAM_OWNER_CHAT_ID chưa được set — cron sẽ không gửi được"
  );
});

process.once("SIGINT", () => bot.stop("SIGINT"));
process.once("SIGTERM", () => bot.stop("SIGTERM"));
