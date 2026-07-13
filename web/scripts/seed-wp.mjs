// Seed dữ liệu demo lên WordPress để dựng/kiểm tra theme.
// Chạy: npm run seed
import "dotenv/config";
import axios from "axios";
import * as wp from "../lib/wp.js";

const POSTS = [
  { title: "Arsenal đè bẹp Chelsea 3-0 tại Emirates",        cat: "ngoai-hang-anh",  img: "https://picsum.photos/seed/arsenal/1200/675" },
  { title: "Real Madrid chốt xong tương lai của Vinicius",    cat: "la-liga",         img: "https://picsum.photos/seed/real/1200/675" },
  { title: "Bayern Munich thắng ngược Dortmund trong Der Klassiker", cat: "bundesliga", img: "https://picsum.photos/seed/bayern/1200/675" },
  { title: "Inter Milan dẫn đầu Serie A sau vòng 28",         cat: "serie-a",         img: "https://picsum.photos/seed/inter/1200/675" },
  { title: "PSG khủng hoảng lực lượng trước vòng knock-out",  cat: "ligue-1",         img: "https://picsum.photos/seed/psg/1200/675" },
  { title: "Man City nhắm tiền đạo trẻ trong kỳ chuyển nhượng hè", cat: "chuyen-nhuong", img: "https://picsum.photos/seed/mancity/1200/675" },
];

const INSIGHTS = [
  { homeTeam: "Arsenal",   awayTeam: "Chelsea",     matchTime: "21:00 - 20/07", hot: true,
    insights: ["Arsenal thắng 8/10 trận sân nhà gần đây", "Chelsea ghi bàn trong 6 trận liên tiếp", "Hai đội hòa 2 lần gần nhất"],
    prediction: "Arsenal thắng 2-1" },
  { homeTeam: "Liverpool", awayTeam: "Man City",    matchTime: "23:30 - 21/07", hot: true,
    insights: ["Liverpool bất bại 12 trận sân nhà", "Man City ghi trung bình 2.4 bàn/trận"],
    prediction: "Hòa 2-2" },
  { homeTeam: "Real Madrid", awayTeam: "Barcelona", matchTime: "02:00 - 22/07", hot: false,
    insights: ["El Clasico luôn có hơn 2.5 bàn trong 5 lần gần nhất", "Barca thắng 5 trận liên tiếp"],
    prediction: "Barcelona thắng 2-1" },
  { homeTeam: "Bayern",    awayTeam: "Dortmund",    matchTime: "20:30 - 23/07", hot: false,
    insights: ["Bayern thắng 4/5 lần đối đầu gần nhất", "Dortmund thủng lưới ở 7 trận liền"],
    prediction: "Bayern thắng 3-1" },
  { homeTeam: "Inter",     awayTeam: "Juventus",    matchTime: "01:45 - 24/07", hot: false,
    insights: ["Derby d'Italia có tỉ lệ hòa cao nhất Serie A", "Inter giữ sạch lưới 5/8 trận sân nhà"],
    prediction: "Hòa 1-1" },
];

function articleHtml(title) {
  return `<h2>Diễn biến trận đấu</h2>
<p>${title}. Trận đấu diễn ra với thế trận áp đảo ngay từ những phút đầu tiên, khi đội chủ nhà liên tục tạo sức ép lên khung thành đối phương.</p>
<h2>Phân tích chiến thuật</h2>
<p>Sơ đồ 4-3-3 phát huy hiệu quả rõ rệt, đặc biệt ở khu vực giữa sân nơi các tiền vệ kiểm soát bóng vượt trội.</p>
<h2>Nhận định</h2>
<p>Kết quả này tác động lớn tới cuộc đua vô địch trong giai đoạn còn lại của mùa giải.</p>`;
}

async function uploadFromUrl(url, filename) {
  const res = await axios.get(url, { responseType: "arraybuffer", timeout: 20000 });
  return wp.uploadMedia(Buffer.from(res.data), filename);
}

async function main() {
  const cats = await wp.fetchCategories();
  console.log(`📁 ${Object.keys(cats).length} danh mục có sẵn`);

  // Bước 1: Xóa dữ liệu cũ từ lần chạy trước
  console.log("\n🧹 Kiểm tra & xóa dữ liệu từ lần chạy trước...");
  const existingPosts = await wp.listPosts(100);
  let deletedPostsCount = 0;

  // Xóa bài viết có tiêu đề khớp với POSTS array
  const postTitles = new Set(POSTS.map(p => p.title));
  for (const post of existingPosts) {
    if (postTitles.has(post.title) && post.id !== 1) { // Giữ lại "Hello world!" (id=1)
      await wp.deleteById(post.id, "posts");
      console.log(`   ❌ Xoá bài: ${post.title}`);
      deletedPostsCount++;
    }
  }

  // Xóa insights có cặp homeTeam/awayTeam khớp với INSIGHTS array
  const existingInsights = await wp.listInsights(100);
  let deletedInsightsCount = 0;

  const insightPairs = new Set(
    INSIGHTS.map(i => `${i.homeTeam}|${i.awayTeam}`)
  );
  for (const insight of existingInsights) {
    const pair = `${insight.homeTeam}|${insight.awayTeam}`;
    if (insightPairs.has(pair)) {
      await wp.deleteById(insight.id, "match_insight");
      console.log(`   ❌ Xoá nhận định: ${insight.homeTeam} vs ${insight.awayTeam}`);
      deletedInsightsCount++;
    }
  }

  if (deletedPostsCount === 0 && deletedInsightsCount === 0) {
    console.log("   ℹ️  Không có dữ liệu cũ cần xóa");
  } else {
    console.log(`   ✓ Đã xóa ${deletedPostsCount} bài viết + ${deletedInsightsCount} nhận định`);
  }

  // Bước 2: Seed dữ liệu mới
  console.log("\n🌱 Seed dữ liệu mới...");
  for (const p of POSTS) {
    const media = await uploadFromUrl(p.img, `seed-${p.cat}-${Date.now()}.jpg`);
    const catId = cats[p.cat]?.id ?? null;

    // Warning nếu danh mục không tìm thấy
    if (!cats[p.cat]) {
      console.warn(`⚠️  Danh mục "${p.cat}" không tìm thấy — gán vào "Uncategorized"`);
    }

    const post = await wp.createPost({
      title: p.title,
      html: articleHtml(p.title),
      excerpt: `${p.title} — cập nhật chi tiết diễn biến, phân tích chiến thuật và nhận định chuyên sâu.`,
      categoryId: catId,
      tags: ["Bóng đá", "Phân tích"],
      featuredMedia: media.id,
    });
    console.log(`   📰 ${post.id} — ${p.title}`);
  }

  for (const i of INSIGHTS) {
    const insight = await wp.createInsight(i);
    console.log(`   ⚽ ${insight.id} — ${i.homeTeam} vs ${i.awayTeam}${i.hot ? " 🔥" : ""}`);
  }

  console.log("\n✅ Seed xong. Mở http://bongda247.local");
}

main().catch((e) => {
  console.error("❌ Seed lỗi:", e.response?.data ?? e.message);
  process.exit(1);
});
