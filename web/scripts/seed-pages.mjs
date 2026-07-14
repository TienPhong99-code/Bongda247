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
