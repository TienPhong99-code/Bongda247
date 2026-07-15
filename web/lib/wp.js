// web/lib/wp.js — Adapter ghi/đọc dữ liệu WordPress qua REST API.
// Mọi lời gọi HTTP tới WP nằm ở file này. bot-press.js không gọi thẳng axios tới WP.
import axios from "axios";

const WP_URL = (process.env.WP_URL || "").replace(/\/$/, "");
const WP_USER = process.env.WP_USER;
const WP_APP_PASSWORD = process.env.WP_APP_PASSWORD;

if (!WP_URL || !WP_USER || !WP_APP_PASSWORD) {
  console.warn("⚠️ Thiếu WP_URL / WP_USER / WP_APP_PASSWORD — bot sẽ không ghi được lên WordPress.");
}

const api = axios.create({
  baseURL: `${WP_URL}/wp-json/wp/v2`,
  auth: { username: WP_USER, password: WP_APP_PASSWORD },
  timeout: 30000,
});

// axios chỉ ném "Request failed with status code 400" — lý do thật (VD "insights is
// not of type array", "Sorry, you are not allowed to create posts as this user") nằm
// trong body lỗi của WP (e.response.data.message/code) và bị bỏ phí. Ghi đè error.message
// ngay tại đây để MỌI catch (e) { ... e.message } trong bot-press.js tự động có thông
// báo rõ ràng, không cần sửa từng chỗ gọi.
api.interceptors.response.use(
  (res) => res,
  (error) => {
    const data = error.response?.data;
    if (data?.message) {
      const status = error.response.status;
      const code = data.code ? ` [${data.code}]` : "";
      error.message = `${data.message}${code} (HTTP ${status})`;
    }
    return Promise.reject(error);
  }
);

// WP trả title/name dưới dạng HTML entity — giải mã để hiển thị trên Telegram.
function decodeEntities(str = "") {
  return str
    .replace(/&#(\d+);/g, (_, d) => String.fromCharCode(Number(d)))
    .replace(/&#x([0-9a-f]+);/gi, (_, h) => String.fromCharCode(parseInt(h, 16)))
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#039;|&apos;/g, "'")
    .replace(/&nbsp;/g, " ");
}

function mimeFromName(filename) {
  // Bỏ query string (VD "preview.png?v=2") trước khi tách đuôi file,
  // nếu không sẽ đọc nhầm ext thành "png?v=2" và luôn rơi về mặc định image/jpeg.
  const clean = String(filename || "").split("?")[0].split("#")[0];
  const ext = clean.split(".").pop().toLowerCase();
  return {
    jpg: "image/jpeg",
    jpeg: "image/jpeg",
    png: "image/png",
    webp: "image/webp",
    gif: "image/gif",
  }[ext] ?? "image/jpeg";
}

/**
 * Chuẩn hoá tên file thành ASCII an toàn cho HTTP header.
 * Header (Content-Disposition) chỉ nhận ký tự Latin-1 — tên file tiếng Việt có dấu
 * (ộ, ư, ơ, đ...) sẽ khiến Node ném TypeError nếu đưa thẳng vào header.
 * Đây là boundary HTTP nên phải tự làm sạch, không dựa vào caller tự slugify trước.
 */
function sanitizeFilename(filename) {
  const raw = String(filename || "").split("?")[0].split("#")[0]; // bỏ query string / hash nếu tên lấy từ URL
  const lastDot = raw.lastIndexOf(".");
  const hasExt = lastDot > 0 && lastDot < raw.length - 1;
  const base = hasExt ? raw.slice(0, lastDot) : raw;
  const ext = hasExt
    ? raw
        .slice(lastDot + 1)
        .toLowerCase()
        .replace(/[^a-z0-9]/g, "")
    : "";

  const slug = base
    .replace(/đ/g, "d")
    .replace(/Đ/g, "D")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "") // bỏ dấu (combining marks) sau khi NFD decompose
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");

  // Nếu tên gốc toàn ký tự không map được về ASCII (VD chữ Hán, emoji...)
  // vẫn phải trả về tên dùng được, không để rỗng.
  const safeBase = slug || `file-${Date.now()}`;
  return ext ? `${safeBase}.${ext}` : safeBase;
}

/**
 * Upload ảnh lên Media Library.
 * Dùng raw binary + Content-Disposition (WP hỗ trợ sẵn) → không cần multipart/form-data.
 */
export async function uploadMedia(buffer, filename = `bongda247-${Date.now()}.jpg`) {
  const safeFilename = sanitizeFilename(filename);
  const res = await api.post("/media", buffer, {
    headers: {
      "Content-Disposition": `attachment; filename="${safeFilename}"`,
      "Content-Type": mimeFromName(filename),
    },
    maxBodyLength: Infinity,
    maxContentLength: Infinity,
  });
  return { id: res.data.id, url: res.data.source_url };
}

/**
 * Đổi tên tag (chuỗi) → term ID. Tự tạo tag nếu chưa có.
 */
export async function resolveTags(names = []) {
  const ids = [];
  for (const raw of names) {
    const name = String(raw).replace(/^#/, "").trim();
    if (!name) continue;
    try {
      const found = await api.get("/tags", { params: { search: name, per_page: 1 } });
      if (found.data.length && decodeEntities(found.data[0].name).toLowerCase() === name.toLowerCase()) {
        ids.push(found.data[0].id);
        continue;
      }
      const created = await api.post("/tags", { name });
      ids.push(created.data.id);
    } catch (e) {
      // WP trả term_exists kèm term_id khi tag đã tồn tại nhưng search không khớp
      const existing = e.response?.data?.data?.term_id;
      if (existing) ids.push(existing);
      else console.warn(`⚠️ Bỏ qua tag "${name}": ${e.message}`);
    }
  }
  return ids;
}

export async function createPost({
  title,
  html,
  excerpt = "",
  categoryId = null,
  tags = [],
  featuredMedia = null,
  sourceUrl = "",
  sourceCredit = "",
}) {
  const tagIds = await resolveTags(tags);
  const res = await api.post("/posts", {
    title,
    content: html,
    excerpt,
    status: "publish",
    ...(categoryId && { categories: [categoryId] }),
    ...(tagIds.length && { tags: tagIds }),
    ...(featuredMedia && { featured_media: featuredMedia }),
    meta: { source_url: sourceUrl || "", source_credit: sourceCredit || "" },
  });
  return { id: res.data.id, link: res.data.link };
}

export async function createInsight({
  homeTeam,
  awayTeam,
  matchTime,
  matchDate = null,
  hot = false,
  insights = [],
  prediction = "",
}) {
  const res = await api.post("/match_insight", {
    title: `${homeTeam} vs ${awayTeam}`,
    status: "publish",
    meta: {
      home_team: homeTeam,
      away_team: awayTeam,
      match_time: matchTime,
      match_date: matchDate ?? "",
      hot: hot ? 1 : 0,
      insights,
      prediction,
    },
  });
  return { id: res.data.id };
}

export async function listPosts(n = 8) {
  const res = await api.get("/posts", {
    params: { per_page: n, orderby: "date", order: "desc", _fields: "id,title,date,categories" },
  });
  return res.data.map((p) => ({
    id: p.id,
    title: decodeEntities(p.title?.rendered ?? ""),
    date: p.date,
    categoryIds: p.categories ?? [],
  }));
}

export async function listInsights(n = 10) {
  const res = await api.get("/match_insight", {
    params: { per_page: n, orderby: "date", order: "desc", _fields: "id,meta" },
  });
  return res.data.map((i) => ({
    id: i.id,
    homeTeam: i.meta?.home_team ?? "",
    awayTeam: i.meta?.away_team ?? "",
    matchTime: i.meta?.match_time ?? "",
    matchDate: i.meta?.match_date ?? "",
    hot: Number(i.meta?.hot ?? 0) === 1,
  }));
}

/** type: REST base bất kỳ — "posts" | "match_insight" | "media" | "tags" | ... */
export async function deleteById(id, type = "posts") {
  await api.delete(`/${type}/${id}`, { params: { force: true } });
}

export async function fetchCategories() {
  const res = await api.get("/categories", {
    params: { per_page: 100, _fields: "id,slug,name" },
  });
  const map = {};
  res.data.forEach((c) => {
    map[c.slug] = { id: c.id, title: decodeEntities(c.name) };
  });
  return map;
}

/** Tạo category nếu chưa có, trả về id. Dùng cho script seed. */
export async function ensureCategory(slug, name) {
  const cats = await fetchCategories();
  if (cats[slug]) return cats[slug].id;
  const res = await api.post("/categories", { name, slug });
  return res.data.id;
}

/** Ghi 1 dự đoán (status=pending). Dedup theo match_id — trùng thì bỏ. */
export async function createPrediction({ matchId, home, away, leagueCode, matchDate, predHome, predAway, predText }) {
  const pending = await listPredictions({ status: "pending" });
  if (pending.some((p) => Number(p.match_id) === Number(matchId))) {
    return { skipped: true };
  }
  const res = await api.post("/bd_prediction", {
    title: `${home} vs ${away}`,
    status: "publish",
    meta: {
      match_id: matchId,
      home_team: home,
      away_team: away,
      league_code: leagueCode || "",
      match_date: matchDate || "",
      pred_home: predHome,
      pred_away: predAway,
      pred_text: predText || "",
      status: "pending",
    },
  });
  return { id: res.data.id };
}

/** Danh sách dự đoán (kèm meta). Lọc client-side theo meta status nếu truyền. */
export async function listPredictions({ status } = {}) {
  const res = await api.get("/bd_prediction", {
    params: { per_page: 100, orderby: "date", order: "desc", status: "publish" },
  });
  const rows = res.data.map((p) => ({ id: p.id, ...p.meta }));
  return status ? rows.filter((r) => r.status === status) : rows;
}

/** Cập nhật 1 dự đoán thành settled + kết quả chấm. */
export async function settlePrediction(id, { actualHome, actualAway, outcomeCorrect, scoreCorrect }) {
  await api.post(`/bd_prediction/${id}`, {
    meta: {
      status: "settled",
      actual_home: actualHome,
      actual_away: actualAway,
      outcome_correct: outcomeCorrect,
      score_correct: scoreCorrect,
      settled_at: new Date().toISOString(),
    },
  });
  return { id };
}

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
