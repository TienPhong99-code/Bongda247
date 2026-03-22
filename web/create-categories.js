import "dotenv/config";
import { createClient } from "@sanity/client";

const sanity = createClient({
  projectId: process.env.SANITY_PROJECT_ID,
  dataset: process.env.SANITY_DATASET || "production",
  token: process.env.SANITY_API_TOKEN,
  apiVersion: "2024-01-01",
  useCdn: false,
});

const CATEGORIES = [
  {
    slug: "ngoai-hang-anh",
    title: "Ngoại hạng Anh",
    description: "Tin tức, phân tích và nhận định về Premier League - giải bóng đá Anh",
  },
  {
    slug: "champions-league",
    title: "Champions League",
    description: "Tin tức về UEFA Champions League, Europa League và các cúp châu Âu",
  },
  {
    slug: "la-liga",
    title: "La Liga",
    description: "Tin tức, phân tích và nhận định về La Liga - giải bóng đá Tây Ban Nha",
  },
  {
    slug: "bundesliga",
    title: "Bundesliga",
    description: "Tin tức, phân tích và nhận định về Bundesliga - giải bóng đá Đức",
  },
  {
    slug: "serie-a",
    title: "Serie A",
    description: "Tin tức, phân tích và nhận định về Serie A - giải bóng đá Ý",
  },
  {
    slug: "ligue-1",
    title: "Ligue 1",
    description: "Tin tức, phân tích và nhận định về Ligue 1 - giải bóng đá Pháp",
  },
  {
    slug: "chuyen-nhuong",
    title: "Chuyển nhượng",
    description: "Tin tức chuyển nhượng cầu thủ, HLV, hợp đồng và thị trường bóng đá",
  },
  {
    slug: "ngoai-san-co",
    title: "Ngoài sân cỏ",
    description: "Tin bên lề, đời tư cầu thủ, scandal, chấn thương, tài chính CLB và các câu chuyện ngoài trận đấu",
  },
];

async function run() {
  console.log("🔍 Đang kiểm tra danh mục hiện có...");
  const existing = await sanity.fetch(
    `*[_type == "category"]{ "slug": slug.current, _id, title }`
  );
  const existingSlugs = new Set(existing.map((c) => c.slug));
  console.log(`✅ Đã có ${existing.length} danh mục:`, [...existingSlugs].join(", "));

  const toCreate = CATEGORIES.filter((c) => !existingSlugs.has(c.slug));
  if (!toCreate.length) {
    console.log("✨ Tất cả danh mục đã tồn tại, không cần tạo thêm.");
    return;
  }

  console.log(`\n📝 Sẽ tạo ${toCreate.length} danh mục mới:`, toCreate.map((c) => c.slug).join(", "));

  for (const cat of toCreate) {
    const doc = {
      _type: "category",
      title: cat.title,
      slug: { _type: "slug", current: cat.slug },
      description: cat.description,
    };
    const result = await sanity.create(doc);
    console.log(`  ✅ Tạo xong: ${cat.title} (${cat.slug}) — ID: ${result._id}`);
  }

  console.log("\n🎉 Hoàn tất! Chạy /reload trong Telegram bot để load danh mục mới.");
}

run().catch((e) => {
  console.error("❌ Lỗi:", e.message);
  process.exit(1);
});
