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
  const ext = filename.split(".").pop().toLowerCase();
  return {
    jpg: "image/jpeg",
    jpeg: "image/jpeg",
    png: "image/png",
    webp: "image/webp",
    gif: "image/gif",
  }[ext] ?? "image/jpeg";
}

/**
 * Upload ảnh lên Media Library.
 * Dùng raw binary + Content-Disposition (WP hỗ trợ sẵn) → không cần multipart/form-data.
 */
export async function uploadMedia(buffer, filename = `bongda247-${Date.now()}.jpg`) {
  const res = await api.post("/media", buffer, {
    headers: {
      "Content-Disposition": `attachment; filename="${filename}"`,
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

/** type: "posts" | "match_insight" */
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
