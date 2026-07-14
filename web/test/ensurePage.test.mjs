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
