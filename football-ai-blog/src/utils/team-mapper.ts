// src/utils/team-mapper.ts
const teamMap: Record<string, string> = {
  // ===== Premier League =====
  "Manchester City FC": "Man City",
  "Manchester United FC": "Man Utd",
  "Liverpool FC": "Liverpool",
  "Arsenal FC": "Arsenal",
  "Chelsea FC": "Chelsea",
  "Tottenham Hotspur FC": "Tottenham",
  "Newcastle United FC": "Newcastle",
  "Aston Villa FC": "Aston Villa",
  "West Ham United FC": "West Ham",
  "Everton FC": "Everton",
  "Leicester City FC": "Leicester",
  "Brighton & Hove Albion FC": "Brighton",

  // ===== La Liga =====
  "Real Madrid CF": "Real Madrid",
  "FC Barcelona": "Barca",
  "Club Atlético de Madrid": "Atletico Madrid",
  "Sevilla FC": "Sevilla",
  "Valencia CF": "Valencia",
  "Real Sociedad": "Sociedad",
  "Villarreal CF": "Villarreal",
  "Athletic Club": "Bilbao",

  // ===== Serie A =====
  "Juventus FC": "Juventus",
  "AC Milan": "AC Milan",
  "FC Internazionale Milano": "Inter",
  "SSC Napoli": "Napoli",
  "AS Roma": "Roma",
  "SS Lazio": "Lazio",
  "Atalanta BC": "Atalanta",
  "ACF Fiorentina": "Fiorentina",

  // ===== Bundesliga =====
  "FC Bayern München": "Bayern",
  "Borussia Dortmund": "Dortmund",
  "RB Leipzig": "Leipzig",
  "Bayer 04 Leverkusen": "Leverkusen",
  "VfL Wolfsburg": "Wolfsburg",
  "Eintracht Frankfurt": "Frankfurt",
  "Borussia Mönchengladbach": "Gladbach",

  // ===== Ligue 1 =====
  "Paris Saint-Germain FC": "PSG",
  "Olympique de Marseille": "Marseille",
  "Olympique Lyonnais": "Lyon",
  "AS Monaco FC": "Monaco",
  "LOSC Lille": "Lille",
  "OGC Nice": "Nice",

  // ===== Các CLB lớn khác =====
  "AFC Ajax": "Ajax",
  "PSV Eindhoven": "PSV",
  "SL Benfica": "Benfica",
  "FC Porto": "Porto",
  "Sporting CP": "Sporting",
  "Celtic FC": "Celtic",
  "Rangers FC": "Rangers",
  "Galatasaray SK": "Galatasaray",
  "Fenerbahçe SK": "Fenerbahce",
  "Beşiktaş JK": "Besiktas",

  // ===== ĐTQG mạnh =====
  Brazil: "Brazil",
  Argentina: "Argentina",
  France: "Pháp",
  Germany: "Đức",
  Spain: "Tây Ban Nha",
  England: "Anh",
  Portugal: "Bồ Đào Nha",
  Italy: "Ý",
  Netherlands: "Hà Lan",
  Belgium: "Bỉ",
  Croatia: "Croatia",
  Uruguay: "Uruguay",
  Colombia: "Colombia",
  Mexico: "Mexico",
  USA: "Mỹ",
  Canada: "Canada",
  Japan: "Nhật Bản",
  "South Korea": "Hàn Quốc",
  Australia: "Úc",
  Iran: "Iran",
  Qatar: "Qatar",

  // ===== Đông Nam Á =====
  Vietnam: "Việt Nam",
  Thailand: "Thái Lan",
  Indonesia: "Indonesia",
  Malaysia: "Malaysia",
  Singapore: "Singapore",
  Philippines: "Philippines",
};
export const translateTeam = (name: string) => teamMap[name] || name;
