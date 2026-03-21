// src/pages/api/live-matches.ts
import type { APIRoute } from "astro";

export const GET: APIRoute = async ({ url }) => {
  const API_KEY = import.meta.env.PUBLIC_FOOTBALL_DATA_KEY;

  // 1. Lấy tham số giải đấu từ URL (Ví dụ: ?league=PL)
  const league = url.searchParams.get("league");

  // 2. Kiểm tra API Key
  if (!API_KEY) {
    return new Response(
      JSON.stringify({ error: "Missing API Key in .env file" }),
      {
        status: 500,
        headers: { "Content-Type": "application/json" },
      },
    );
  }

  try {
    // 3. Xây dựng URL gọi API
    // Nếu có league cụ thể: Gọi tới competitions/{code}/matches
    // Nếu không có: Gọi tới danh sách matches chung và lọc các giải lớn
    let apiUrl = "https://api.football-data.org/v4/matches";

    if (league && league !== "ALL") {
      // Lưu ý: Football-data.org yêu cầu định dạng này cho giải đấu cụ thể
      apiUrl = `https://api.football-data.org/v4/competitions/${league}/matches`;
    } else {
      // Mặc định lấy các giải lớn để tránh lấy các giải "rác"
      // PL: Anh, CL: Champions League, BL1: Đức, SA: Ý, PD: Tây Ban Nha, FL1: Pháp
      apiUrl += "?competitions=PL,CL,BL1,SA,PD,FL1";
    }

    // 4. Gọi API Football-Data
    const response = await fetch(apiUrl, {
      method: "GET",
      headers: {
        "X-Auth-Token": API_KEY,
        "Content-Type": "application/json",
      },
    });

    const data = await response.json();

    // 5. Kiểm tra phản hồi từ API (Xử lý lỗi 400, 403, 429)
    if (!response.ok) {
      console.error("Football-Data API Error:", data.message);
      return new Response(
        JSON.stringify({
          error: data.message || "API Error",
          code: response.status,
        }),
        {
          status: response.status,
          headers: { "Content-Type": "application/json" },
        },
      );
    }

    // 6. Trả về dữ liệu kèm Header Cache để tối ưu performance
    return new Response(JSON.stringify(data), {
      status: 200,
      headers: {
        "Content-Type": "application/json",
        // Cache trên trình duyệt 30s, trên Server 60s để tránh bị Rate Limit
        "Cache-Control": "public, s-maxage=60, stale-while-revalidate=30",
      },
    });
  } catch (error) {
    console.error("Server Catch Error:", error);
    return new Response(JSON.stringify({ error: "Internal Server Error" }), {
      status: 500,
      headers: { "Content-Type": "application/json" },
    });
  }
};
