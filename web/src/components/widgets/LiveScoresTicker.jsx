// src/components/widgets/LiveScoresTicker.jsx
import { useState } from 'react';
import { QueryClient, QueryClientProvider, useQuery } from '@tanstack/react-query';
import { LEAGUE_TABS } from '../../utils/league-config';
import { translateTeam } from '../../utils/team-mapper';

const queryClient = new QueryClient();

// Các hàm bổ trợ giữ nguyên
export function mapApiToMatch(apiMatch) {
  return {
    id: apiMatch.id,
    home: translateTeam(apiMatch.homeTeam.shortName || apiMatch.homeTeam.name),
    away: translateTeam(apiMatch.awayTeam.shortName || apiMatch.awayTeam.name),
    homeLogo: apiMatch.homeTeam.crest, 
    awayLogo: apiMatch.awayTeam.crest,
    homeScore: apiMatch.score.fullTime.home ?? 0,
    awayScore: apiMatch.score.fullTime.away ?? 0,
    time: apiMatch.status === 'IN_PLAY' ? `${apiMatch.score.duration || "Đang đá"}` : "Live",
    league: apiMatch.competition.code || "WC",
    live: true
  }
}

// Tách riêng Component Ticker để xử lý logic hiển thị trận đấu
function TickerScroll({ leagueId }) {
  const { data: apiMatches, isLoading } = useQuery({
    queryKey: ['liveTicker', leagueId],
    queryFn: async () => {
      const url = leagueId === 'ALL' 
        ? '/api/live-matches' 
        : `/api/live-matches?league=${leagueId}`;
      const res = await fetch(url);
      const json = await res.json();
      
      return json.matches?.filter(m => 
        m.status === 'IN_PLAY' || m.status === 'PAUSED' || m.status === 'LIVE'
      ) || [];
    },
    refetchInterval: 30000,
  });

  if (isLoading) return <div className="text-[10px] text-slate-600 animate-pulse uppercase font-black">📡 Đang kết nối tín hiệu...</div>;

  if (!apiMatches || apiMatches.length === 0) {
    return (
      <div className="text-[10px] text-slate-500 italic uppercase flex items-center gap-2">
        <span className="w-1.5 h-1.5 rounded-full bg-slate-700"></span>
        Hiện không có trận đấu nào đang đá trực tiếp
      </div>
    );
  }

  const doubled = [...apiMatches].map(mapApiToMatch);

  return (
    <div className="flex gap-4 animate-marquee hover:pause whitespace-nowrap">
      {doubled.map((match, index) => (
        <MatchCard key={`${match.id}-${index}`} match={match} />
      ))}
    </div>
  );
}

function TickerContainer() {
  const [filterLeague, setFilterLeague] = useState('ALL');

  return (
    <div className="mx-auto max-w-7xl px-4 flex items-center h-12">
      {/* PHẦN TAB CONTROL - LUÔN HIỂN THỊ */}
      <div className="flex-shrink-0 flex items-center gap-2 mr-6 border-r border-slate-800 pr-6">
        <div className="flex items-center gap-2 bg-red-600/10 px-2 py-1 rounded-sm border border-red-600/20 mr-2">
           <span className="w-1.5 h-1.5 rounded-full bg-red-600 animate-ping"></span>
           <span className="text-[10px] font-black text-red-500 uppercase tracking-tighter">Live</span>
        </div>
        
        <div className="flex gap-1">
          {LEAGUE_TABS.slice(0, 5).map((league) => (
            <button
              key={league.id}
              onClick={() => setFilterLeague(league.id)}
              className={`px-2.5 py-1 rounded-xs text-[9px] font-black transition-all duration-200 uppercase tracking-widest ${
                filterLeague === league.id 
                ? 'bg-brand text-black shadow-[0_0_8px_#39FF14]' 
                : 'bg-slate-900 text-slate-500 hover:text-slate-300 border border-slate-800'
              }`}
            >
              {league.id === 'ALL' ? 'Tất cả' : league.id}
            </button>
          ))}
        </div>
      </div>

      {/* PHẦN CONTENT - CHỈ THAY ĐỔI NỘI DUNG BÊN TRONG */}
      <div className="flex-1 overflow-hidden">
        <TickerScroll leagueId={filterLeague} />
      </div>
    </div>
  );
}

export function LiveScoresTicker() {
  return (
    <QueryClientProvider client={queryClient}>
      <div className="w-full overflow-hidden bg-slate-950 border-b border-slate-900 shadow-xl relative z-50">
        <TickerContainer />
      </div>
    </QueryClientProvider>
  )
}

// Đừng quên export MatchCard nếu bạn dùng nó ở file khác
export function MatchCard({ match }) {
  return (
    <div className="inline-flex items-center gap-3 px-4 border-r border-slate-800/50">
      <div className="flex items-center gap-1.5">
        <img src={match.homeLogo} className="w-4 h-4 object-contain" alt="" />
        <span className="text-xs font-bold text-white uppercase">{match.home}</span>
      </div>
      <div className="flex items-center gap-1 bg-slate-900 px-2 py-0.5 rounded border border-slate-800">
        <span className="text-sm font-black text-brand tabular-nums">{match.homeScore}</span>
        <span className="text-[10px] text-slate-600">-</span>
        <span className="text-sm font-black text-brand tabular-nums">{match.awayScore}</span>
      </div>
      <div className="flex items-center gap-1.5">
        <span className="text-xs font-bold text-white uppercase">{match.away}</span>
        <img src={match.awayLogo} className="w-4 h-4 object-contain" alt="" />
      </div>
    </div>
  );
}