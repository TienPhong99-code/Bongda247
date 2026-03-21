import "dotenv/config";
import { Telegraf } from "telegraf";
import { GoogleGenerativeAI } from "@google/generative-ai";
import { createClient } from "@sanity/client";
import axios from "axios";

// --- HÀM TẠO SLUG CHUẨN SEO ---
const createSlug = (text) => {
  return text
    .toString()
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[đĐ]/g, "d")
    .replace(/[^\w\s-]/g, "")
    .trim()
    .replace(/\s+/g, "-")
    .replace(/-+/g, "-");
};

// 1. Cấu hình Sanity
const sanity = createClient({
  projectId: process.env.SANITY_PROJECT_ID,
  dataset: process.env.SANITY_DATASET || "production",
  token: process.env.SANITY_API_TOKEN,
  useCdn: false,
  apiVersion: "2024-03-03",
});

// 2. Cấu hình Gemini
const genAI = new GoogleGenerativeAI(process.env.GEMINI_API_KEY);
const model = genAI.getGenerativeModel({
  model: "gemini-2.5-flash", // Bản flash ổn định và nhanh nhất hiện tại
});

// 3. Cấu hình Telegram Bot
const bot = new Telegraf(process.env.TELEGRAM_BOT_TOKEN);

// 4. Danh mục ID
const CATEGORIES = {
  "ngoai-hang-anh": "33d05981-5ddb-4c87-8341-c263b7b8528d",
  "la-liga": "dc88df2c-e409-430f-8561-190103819a78",
  "nhan-dinh": "adfa7fef-4ad5-4ee0-a0d6-14ba645d672d",
};

const mediaGroupStorage = new Map();
const pendingPosts = new Map();

bot.start((ctx) =>
  ctx.reply(
    "⚽ Chào Văn! Gửi 'insight + nội dung' hoặc Album ảnh + Caption để soạn bài.",
  ),
);

// --- LUỒNG XỬ LÝ TIN NHẮN ĐẾN ---
bot.on(["photo", "text"], async (ctx) => {
  const chatId = ctx.chat.id;
  const message = ctx.message;
  const now = new Date();
  const currentDateStr = now.toLocaleDateString("vi-VN");

  // Xử lý Album ảnh
  if (message.media_group_id) {
    if (!mediaGroupStorage.has(message.media_group_id)) {
      mediaGroupStorage.set(message.media_group_id, []);
    }
    const photos = mediaGroupStorage.get(message.media_group_id);
    photos.push(message.photo[message.photo.length - 1].file_id);
    if (!message.caption) return;
  }

  const rawText = message.caption || message.text;
  if (!rawText) return;

  // --- LUỒNG 1: XỬ LÝ SỐ LIỆU CHUYÊN SÂU (INSIGHT) ---
  if (rawText.toUpperCase().includes("INSIGHT")) {
    ctx.reply("📊 Đang trích xuất số liệu...");
    try {
      const prompt = `
  Bạn là chuyên gia bóng đá. Hôm nay là ngày: ${currentDateStr}.
  Nhiệm vụ: Trích xuất thông tin từ nội dung sau: "${rawText}"

  Yêu cầu về "matchTime":
  - Nếu nội dung CHỈ CÓ NGÀY (vd: 15/03) và KHÔNG CÓ GIỜ cụ thể, hãy trả về: "DD/MM" (Ví dụ: "15/03").
  - Nếu nội dung CÓ CẢ GIỜ (vd: 22:00 ngày 15/03), hãy trả về: "HH:mm - DD/MM" (Ví dụ: "22:00 - 15/03").
  
  Trả về DUY NHẤT định dạng JSON: { "homeTeam", "awayTeam", "matchTime", "hot", "insights": [], "prediction" }
`;

      const result = await model.generateContent(prompt);
      const cleanJson = result.response
        .text()
        .replace(/```json/g, "")
        .replace(/```/g, "")
        .trim();
      const insightData = JSON.parse(cleanJson);

      // Lưu tạm vào Map
      pendingPosts.set(chatId, { ...insightData, type: "matchInsight" });

      const hotStatus = "❄️ KHÔNG";

      // QUAN TRỌNG: Dùng return để không chạy xuống Luồng 2 bên dưới
      return ctx.reply(
        `🔍 **XÁC NHẬN INSIGHT**\n------------------\n🏟 ${insightData.homeTeam} VS ${insightData.awayTeam}\n⏰ ${insightData.matchTime}\n🔥 Trận HOT: ${hotStatus}\n\n📊 **Số liệu:**\n${insightData.insights.join("\n")}`,
        {
          parse_mode: "Markdown",
          reply_markup: {
            inline_keyboard: [
              [
                {
                  text: `Đổi trạng thái HOT (Hiện: ${hotStatus})`,
                  callback_data: "toggle_hot",
                },
              ],
              [{ text: "🚀 Đăng lên Slide", callback_data: "confirm_insight" }],
              [{ text: "❌ Hủy", callback_data: "cancel_post" }],
            ],
          },
        },
      );
    } catch (e) {
      console.error(e);
      return ctx.reply("❌ Lỗi xử lý Insight.");
    }
  }

  // --- LUỒNG 2: XỬ LÝ BÀI VIẾT THÔNG THƯỜNG ---
  ctx.reply("⏳ AI đang soạn bài viết...");
  try {
    const lowerText = rawText.toLowerCase();
    let selectedCat = CATEGORIES["ngoai-hang-anh"];
    let label = "Ngoại hạng Anh";

    if (lowerText.match(/nhận định|soi kèo|dự đoán|tỷ lệ/)) {
      selectedCat = CATEGORIES["nhan-dinh"];
      label = "Nhận định bóng đá";
    } else if (lowerText.match(/la liga|tây ban nha/)) {
      selectedCat = CATEGORIES["la-liga"];
      label = "Tây Ban Nha";
    }

    const prompt = `Viết bài báo JSON chuẩn SEO từ: "${rawText}"...`; // Prompt rút gọn của bạn

    const result = await model.generateContent(prompt);
    const cleanJson = result.response
      .text()
      .replace(/```json/g, "")
      .replace(/```/g, "")
      .trim();
    const data = JSON.parse(cleanJson);

    let currentPhotos = message.media_group_id
      ? [...(mediaGroupStorage.get(message.media_group_id) || [])]
      : message.photo
        ? [message.photo[message.photo.length - 1].file_id]
        : [];

    pendingPosts.set(chatId, {
      ...data,
      categoryId: selectedCat,
      photos: currentPhotos,
      type: "post",
    });

    ctx.reply(`🔍 **XEM TRƯỚC: ${data.title}**`, {
      reply_markup: {
        inline_keyboard: [
          [{ text: "✅ Đăng ngay", callback_data: "confirm_post" }],
          [{ text: "❌ Hủy", callback_data: "cancel_post" }],
        ],
      },
    });

    if (message.media_group_id)
      mediaGroupStorage.delete(message.media_group_id);
  } catch (error) {
    ctx.reply("❌ Lỗi soạn bài.");
  }
});

// --- ACTIONS XỬ LÝ NÚT BẤM ---

// 1. Đổi trạng thái HOT
bot.action("toggle_hot", async (ctx) => {
  const chatId = ctx.chat.id;
  const data = pendingPosts.get(chatId);
  if (!data || data.type !== "matchInsight")
    return ctx.answerCbQuery("❌ Hết hạn!");

  data.hot = !data.hot; // Đảo trạng thái true/false
  pendingPosts.set(chatId, data); // Cập nhật lại vào bộ nhớ tạm

  const hotStatus = data.hot ? "🔥 CÓ" : "❄️ KHÔNG";

  try {
    await ctx.editMessageText(
      `🔍 **XÁC NHẬN INSIGHT**\n------------------\n🏟 ${data.homeTeam} VS ${data.awayTeam}\n⏰ ${data.matchTime}\n🔥 Trận HOT: ${hotStatus}\n\n📊 **Số liệu:**\n${data.insights.join("\n")}`,
      {
        parse_mode: "Markdown",
        reply_markup: {
          inline_keyboard: [
            [
              {
                text: `Đổi trạng thái HOT (Hiện: ${hotStatus})`,
                callback_data: "toggle_hot",
              },
            ],
            [{ text: "🚀 Đăng lên Slide", callback_data: "confirm_insight" }],
            [{ text: "❌ Hủy", callback_data: "cancel_post" }],
          ],
        },
      },
    );
    ctx.answerCbQuery(`Đã chuyển HOT sang: ${hotStatus}`);
  } catch (e) {
    ctx.answerCbQuery("Lỗi giao diện.");
  }
});

// 2. Xác nhận đăng Insight
bot.action("confirm_insight", async (ctx) => {
  const chatId = ctx.chat.id;
  const data = pendingPosts.get(chatId);
  if (!data || data.type !== "matchInsight")
    return ctx.reply("❌ Dữ liệu hết hạn.");

  try {
    await sanity.create({
      _type: "matchInsight",
      homeTeam: data.homeTeam,
      awayTeam: data.awayTeam,
      matchTime: data.matchTime,
      hot: data.hot,
      insights: data.insights,
      prediction: data.prediction,
      publishedAt: new Date().toISOString(),
    });

    ctx.editMessageText(
      `✅ **ĐÃ ĐĂNG THÀNH CÔNG!**\n\n🏟 ${data.homeTeam} VS ${data.awayTeam}\n🔥 HOT: ${data.hot ? "CÓ" : "KHÔNG"}\n📊 Insights:\n${data.insights.join("\n")}`,
      { parse_mode: "Markdown" },
    );
    pendingPosts.delete(chatId);
  } catch (e) {
    ctx.reply("Lỗi: " + e.message);
  }
});

// 3. Xác nhận đăng Bài viết
bot.action("confirm_post", async (ctx) => {
  const chatId = ctx.chat.id;
  const data = pendingPosts.get(chatId);
  if (!data || data.type !== "post") return;

  ctx.answerCbQuery("🚀 Đang đẩy bài viết...");
  try {
    const assetIds = await Promise.all(
      data.photos.map(async (fId) => {
        const link = await ctx.telegram.getFileLink(fId);
        const res = await axios.get(link.href, { responseType: "arraybuffer" });
        const asset = await sanity.assets.upload(
          "image",
          Buffer.from(res.data),
        );
        return asset._id;
      }),
    );

    // Xây dựng Portable Text
    let finalContent = data.sections
      .map((section, index) => {
        let blocks = [
          {
            _type: "block",
            style: "h2",
            children: [{ _type: "span", text: section.heading }],
          },
          {
            _type: "block",
            style: "normal",
            children: [{ _type: "span", text: section.text }],
          },
        ];
        if (assetIds[index + 1]) {
          blocks.push({
            _type: "image",
            asset: { _type: "reference", _ref: assetIds[index + 1] },
          });
        }
        return blocks;
      })
      .flat();

    await sanity.create({
      _type: "post",
      title: data.title,
      slug: {
        _type: "slug",
        current: `${createSlug(data.title)}-${Date.now()}`,
      },
      mainImage: assetIds[0]
        ? { _type: "image", asset: { _ref: assetIds[0] } }
        : undefined,
      excerpt: data.excerpt,
      content: finalContent,
      category: { _type: "reference", _ref: data.categoryId },
      publishedAt: new Date().toISOString(),
    });

    ctx.editMessageText(`✅ Đã xuất bản bài viết: ${data.title}`);
    pendingPosts.delete(chatId);
  } catch (error) {
    ctx.reply("Lỗi: " + error.message);
  }
});

// 4. Hủy/Xóa
bot.action("cancel_post", (ctx) => {
  pendingPosts.delete(ctx.chat.id);
  ctx.editMessageText("❌ Đã hủy bản nháp.");
});

// 5. Quản lý Insight (Lệnh xóa tay)
bot.on("callback_query", async (ctx) => {
  const action = ctx.callbackQuery.data;
  if (action.startsWith("delete_insight_")) {
    const id = action.replace("delete_insight_", "");
    try {
      await sanity.delete(id);
      ctx.answerCbQuery("✅ Đã xóa!");
      ctx.editMessageText("🗑 Trận đấu đã gỡ khỏi Slide.");
    } catch (e) {
      ctx.answerCbQuery("Lỗi xóa.");
    }
  }
});

bot.launch();
console.log("🚀 Bongda247 Bot is Running...");
