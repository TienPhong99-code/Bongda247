// src/lib/football-data.ts

const BASE_URL = 'https://api.football-data.org/v4';
const API_KEY = import.meta.env.PUBLIC_FOOTBALL_DATA_KEY; 

async function fetchFromApi(endpoint: string) {
  const response = await fetch(`${BASE_URL}/${endpoint}`, {
    headers: { 'X-Auth-Token': API_KEY }
  });

  if (!response.ok) {
    if (response.status === 429) throw new Error('Vượt quá giới hạn request (Gói Free)');
    throw new Error('Lỗi kết nối Football-Data.org');
  }
  
  return response.json();
}

export const footballService = {
  // Lấy bảng xếp hạng (PL = Premier League, WC = World Cup, CL = Champions League)
  getStandings: async (leagueCode = 'PL') => {
    const data = await fetchFromApi(`competitions/${leagueCode}/standings`);
    return data.standings[0].table; // Trả về mảng các đội (position, team, playedGames, points...)
  },

  // Lấy lịch thi đấu hôm nay (mặc định các giải lớn)
  getTodayMatches: async () => {
    const data = await fetchFromApi('matches');
    return data.matches;
  },

  // Lấy chi tiết một trận đấu (Để AI viết bài nhận định)
  getMatchDetails: async (matchId: number) => {
    return await fetchFromApi(`matches/${matchId}`);
  }
};