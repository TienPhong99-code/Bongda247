// Integration test — chạy thật với WordPress ở WP_URL (Local phải đang chạy).
import "dotenv/config";
import { test, after } from "node:test";
import assert from "node:assert/strict";
import * as wp from "../lib/wp.js";

// PNG 1x1 trong suốt — đủ để WP nhận là ảnh hợp lệ
const PNG_1X1 = Buffer.from(
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=",
  "base64"
);

const created = { posts: [], match_insight: [] };

after(async () => {
  for (const id of created.posts) await wp.deleteById(id, "posts");
  for (const id of created.match_insight) await wp.deleteById(id, "match_insight");
});

test("fetchCategories trả map slug → {id, title}", async () => {
  const cats = await wp.fetchCategories();
  assert.ok(cats["ngoai-hang-anh"], "thiếu category ngoai-hang-anh");
  assert.equal(typeof cats["ngoai-hang-anh"].id, "number");
  assert.equal(cats["ngoai-hang-anh"].title, "Ngoại hạng Anh");
});

test("uploadMedia trả {id, url}", async () => {
  const media = await wp.uploadMedia(PNG_1X1, "test-pixel.png");
  assert.equal(typeof media.id, "number");
  assert.match(media.url, /^http.*\.png$/);
});

test("createPost gắn category, featured image, tag và meta nguồn", async () => {
  const cats = await wp.fetchCategories();
  const media = await wp.uploadMedia(PNG_1X1, "test-featured.png");

  const post = await wp.createPost({
    title: "Bài test tự động",
    html: "<h2>Mục một</h2>\n<p>Nội dung một.</p>",
    excerpt: "Mô tả ngắn",
    categoryId: cats["ngoai-hang-anh"].id,
    tags: ["#Arsenal", "Chelsea"],
    featuredMedia: media.id,
    sourceUrl: "https://example.com/bai-goc",
    sourceCredit: "Example",
  });
  created.posts.push(post.id);

  assert.equal(typeof post.id, "number");
  assert.match(post.link, /^http/);

  const res = await fetch(`${process.env.WP_URL}/wp-json/wp/v2/posts/${post.id}`);
  const body = await res.json();
  assert.equal(body.title.rendered, "Bài test tự động");
  assert.deepEqual(body.categories, [cats["ngoai-hang-anh"].id]);
  assert.equal(body.featured_media, media.id);
  assert.equal(body.tags.length, 2);
  assert.equal(body.meta.source_url, "https://example.com/bai-goc");
  assert.ok(body.content.rendered.includes("Mục một"));
});

test("createInsight ghi được meta mảng insights", async () => {
  const insight = await wp.createInsight({
    homeTeam: "Arsenal",
    awayTeam: "Chelsea",
    matchTime: "21:00 - 23/03",
    matchDate: "2026-03-23T14:00:00Z",
    hot: true,
    insights: ["Arsenal thắng 8/10 sân nhà", "Chelsea ghi bàn 6 trận liền", "Đối đầu nghiêng về Arsenal"],
    prediction: "Arsenal thắng 2-1",
  });
  created.match_insight.push(insight.id);

  const res = await fetch(`${process.env.WP_URL}/wp-json/wp/v2/match_insight/${insight.id}`);
  const body = await res.json();
  assert.equal(body.meta.home_team, "Arsenal");
  assert.equal(body.meta.hot, 1);
  assert.equal(body.meta.insights.length, 3);
  assert.equal(body.meta.insights[0], "Arsenal thắng 8/10 sân nhà");
  assert.equal(body.meta.prediction, "Arsenal thắng 2-1");
});

test("listInsights map meta về đúng shape bot cần", async () => {
  const insight = await wp.createInsight({
    homeTeam: "Liverpool",
    awayTeam: "Everton",
    matchTime: "23:30 - 24/03",
    hot: false,
    insights: ["Một ghi chú"],
    prediction: "Hòa 1-1",
  });
  created.match_insight.push(insight.id);

  const list = await wp.listInsights(10);
  const found = list.find((i) => i.id === insight.id);
  assert.ok(found, "không thấy insight vừa tạo trong listInsights");
  assert.equal(found.homeTeam, "Liverpool");
  assert.equal(found.awayTeam, "Everton");
  assert.equal(found.matchTime, "23:30 - 24/03");
  assert.equal(found.hot, false);
});

test("listPosts trả id, title đã decode entity, categoryIds", async () => {
  const cats = await wp.fetchCategories();
  const post = await wp.createPost({
    title: "Tin tức & phân tích",
    html: "<p>Nội dung</p>",
    categoryId: cats["chuyen-nhuong"].id,
  });
  created.posts.push(post.id);

  const list = await wp.listPosts(8);
  const found = list.find((p) => p.id === post.id);
  assert.ok(found, "không thấy bài vừa tạo trong listPosts");
  assert.equal(found.title, "Tin tức & phân tích"); // KHÔNG được là "&amp;"
  assert.deepEqual(found.categoryIds, [cats["chuyen-nhuong"].id]);
});
