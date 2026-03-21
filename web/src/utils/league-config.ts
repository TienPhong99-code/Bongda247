export const SUPPORTED_LEAGUES = {
  PL: { name: "Ngoại hạng Anh", color: "bg-purple-600" },
  CL: { name: "Champions League", color: "bg-blue-700" },
  BL1: { name: "Bundesliga", color: "bg-red-600" },
  SA: { name: "Serie A", color: "bg-blue-500" },
  PD: { name: "La Liga", color: "bg-yellow-500" },
  WC: { name: "World Cup", color: "bg-green-600" },
};
export const LEAGUE_TABS = [
  { id: "ALL", name: "Tất cả", code: "" },
  { id: "PL", name: "Ngoại hạng Anh", code: "PL" },
  { id: "CL", name: "Champions League", code: "CL" },
  { id: "PD", name: "La Liga", code: "PD" },
  { id: "BL1", name: "Bundesliga", code: "BL1" },
  { id: "SA", name: "Serie A", code: "SA" },
];
export const LEAGUE_CODES = Object.keys(SUPPORTED_LEAGUES);
