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

const created = { posts: [], match_insight: [], media: [], tags: [] };

// Xoá từng loại/id một cách độc lập — nếu 1 lần xoá lỗi (đã bị xoá tay, hết quyền...)
// vẫn phải tiếp tục dọn hết các item còn lại, không được bỏ dở giữa chừng.
async function cleanupList(ids, type) {
  // Bỏ trùng id (VD nhiều bài test tạo lại cùng 1 tag) để khỏi log warning ảo.
  for (const id of new Set(ids)) {
    try {
      await wp.deleteById(id, type);
    } catch (e) {
      console.warn(`⚠️ Không xoá được ${type}/${id}: ${e.message}`);
    }
  }
}

after(async () => {
  await cleanupList(created.posts, "posts");
  await cleanupList(created.match_insight, "match_insight");
  await cleanupList(created.media, "media");
  await cleanupList(created.tags, "tags");
});

test("fetchCategories trả map slug → {id, title}", async () => {
  const cats = await wp.fetchCategories();
  assert.ok(cats["ngoai-hang-anh"], "thiếu category ngoai-hang-anh");
  assert.equal(typeof cats["ngoai-hang-anh"].id, "number");
  assert.equal(cats["ngoai-hang-anh"].title, "Ngoại hạng Anh");
});

test("uploadMedia trả {id, url}", async () => {
  const media = await wp.uploadMedia(PNG_1X1, "test-pixel.png");
  created.media.push(media.id);
  assert.equal(typeof media.id, "number");
  assert.match(media.url, /^http.*\.png$/);
});

// Finding 1: tên file tiếng Việt có dấu từng khiến Node ném
// "TypeError: Invalid character in header content" khi build Content-Disposition.
// wp.js phải tự làm sạch tên file (bỏ dấu, map đ/Đ, thay ký tự lạ bằng "-") trước khi
// đưa vào header, không dựa vào caller tự slugify trước.
test("uploadMedia xử lý được tên file tiếng Việt có dấu", async () => {
  const media = await wp.uploadMedia(
    PNG_1X1,
    "Ảnh bóng đá - Cầu thủ ghi bàn ở Ngoại hạng Anh ộ ư ơ.png"
  );
  created.media.push(media.id);
  assert.equal(typeof media.id, "number");
  // URL phải là ASCII hợp lệ, không văng lỗi, và vẫn giữ đuôi .png
  assert.match(media.url, /^http.*\.png$/i);
});

test("createPost gắn category, featured image, tag và meta nguồn", async () => {
  const cats = await wp.fetchCategories();
  const media = await wp.uploadMedia(PNG_1X1, "test-featured.png");
  created.media.push(media.id);

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

  // resolveTags tạo tag "Arsenal"/"Chelsea" thật trên WP — dọn luôn để khỏi rác lại.
  body.tags.forEach((id) => created.tags.push(id));
});

// Finding 4: chứng minh resolveTags không tạo trùng tag khi gọi lại với tên đã tồn tại.
// Nhánh catch bắt "term_exists" (race điều kiện 2 request tạo cùng lúc) rất khó ép xảy ra
// một cách xác định trong test, nên ở đây test đường thực tế hay gặp hơn: gọi resolveTags
// 2 lần liên tiếp với cùng 1 tên — lần 2 phải tìm thấy tag đã tạo ở lần 1 (qua search),
// trả về đúng 1 term ID, không tạo thêm bản ghi mới.
test("resolveTags dùng lại tag đã có, không tạo trùng", async () => {
  const tagName = `Tag test trùng lặp ${Date.now()}`;

  const first = await wp.resolveTags([tagName]);
  const second = await wp.resolveTags([tagName]);

  assert.equal(first.length, 1);
  assert.equal(second.length, 1);
  assert.equal(
    first[0],
    second[0],
    "resolveTags phải trả về cùng 1 term ID khi gọi lại với tên tag giống nhau"
  );
  created.tags.push(first[0]);

  const res = await fetch(
    `${process.env.WP_URL}/wp-json/wp/v2/tags?search=${encodeURIComponent(tagName)}`
  );
  const body = await res.json();
  assert.equal(body.length, 1, "không được tạo trùng tag khi resolveTags gọi lại với tên đã tồn tại");
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
