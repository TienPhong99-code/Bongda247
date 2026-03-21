import { useState } from 'react';
import { QueryClient, QueryClientProvider, useQuery } from '@tanstack/react-query';
import { LEAGUE_TABS } from '../../utils/league-config';
import { MatchCard, mapApiToMatch } from './LiveScoresTicker';

// 1. Khởi tạo QueryClient bên ngoài component
const queryClient = new QueryClient();

// 2. Component chứa logic chính
function TabsContent() {
  const [activeTab, setActiveTab] = useState('ALL');

  const { data: matches, isLoading, isFetching } = useQuery({
    queryKey: ['matches', activeTab],
    queryFn: async () => {
      const url = activeTab === 'ALL' 
        ? '/api/live-matches' 
        : `/api/live-matches?league=${activeTab}`;
      const res = await fetch(url);
      if (!res.ok) throw new Error('Lỗi fetch dữ liệu');
      const json = await res.json();
      return json.matches || [];
    },
    staleTime: 30000,
  });

  return (
    <div className="w-full bg-slate-950 py-8 border-b border-slate-900">
      <div className="max-w-7xl mx-auto px-4">
        {/* ... (Giữ nguyên phần HTML/JSX của thanh Tab và Danh sách trận đấu như tôi đã viết ở bước trước) ... */}
        <div className="flex items-center space-x-1 border-b border-slate-900 mb-8 overflow-x-auto no-scrollbar pb-1">
          {LEAGUE_TABS.map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`relative px-5 py-3 text-xs font-black uppercase tracking-widest transition-all duration-300 whitespace-nowrap
                ${activeTab === tab.id ? 'text-brand' : 'text-slate-500 hover:text-slate-300'}`}
            >
              {tab.name}
              {activeTab === tab.id && (
                <span className="absolute bottom-0 left-0 w-full h-0.5 bg-brand"></span>
              )}
            </button>
          ))}
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {isLoading ? (
            <div className="text-white text-xs">Đang tải...</div>
          ) : matches?.length > 0 ? (
            matches.map((match) => (
              <MatchCard key={match.id} match={mapApiToMatch(match)} />
            ))
          ) : (
            <div className="text-slate-500 italic text-xs">Không có trận đấu</div>
          )}
        </div>
      </div>
    </div>
  );
}

// 3. Component Export chính (Bọc Provider ở đây)
export default function LeagueTabs() {
  return (
    <QueryClientProvider client={queryClient}>
      <TabsContent />
    </QueryClientProvider>
  );
}