<?php
defined('ABSPATH') || exit;

// 5 giải VĐQG: slug WordPress → mã competition football-data.org + tên hiển thị.
if (!defined('BD_FD_LEAGUES')) {
    define('BD_FD_LEAGUES', [
        'ngoai-hang-anh' => ['code' => 'PL',  'name' => 'Ngoại hạng Anh'],
        'la-liga'        => ['code' => 'PD',  'name' => 'La Liga'],
        'bundesliga'     => ['code' => 'BL1', 'name' => 'Bundesliga'],
        'serie-a'        => ['code' => 'SA',  'name' => 'Serie A'],
        'ligue-1'        => ['code' => 'FL1', 'name' => 'Ligue 1'],
    ]);
}

/** slug → mã competition, hoặc null nếu không phải 1 trong 5 giải. */
function bd_fd_code($slug) {
    return BD_FD_LEAGUES[$slug]['code'] ?? null;
}

/** slug → tên giải hiển thị. */
function bd_fd_league_name($slug) {
    return BD_FD_LEAGUES[$slug]['name'] ?? '';
}

/**
 * Gọi API football-data.org. Trả mảng decode, hoặc null nếu thiếu key / lỗi / non-200.
 * Key đọc từ hằng FOOTBALL_DATA_KEY (định nghĩa trong wp-config, KHÔNG commit).
 */
function bd_fd_api($path, $query = []) {
    if (!defined('FOOTBALL_DATA_KEY') || !FOOTBALL_DATA_KEY) {
        return null;
    }
    $url = 'https://api.football-data.org/v4' . $path;
    if (!empty($query)) {
        $url = add_query_arg($query, $url);
    }
    $res = wp_remote_get($url, [
        'headers' => ['X-Auth-Token' => FOOTBALL_DATA_KEY],
        'timeout' => 10,
    ]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
        // Ghi log lỗi để debug trên prod (không ảnh hưởng người dùng)
        error_log('bd_fd_api lỗi: ' . (is_wp_error($res) ? $res->get_error_message() : ('HTTP ' . wp_remote_retrieve_response_code($res))) . ' — ' . $url);
        return null;
    }
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return is_array($data) ? $data : null;
}

/**
 * Cache stale-while-revalidate.
 * option 'bd_fd_{key}' giữ data bền; transient 'bd_fd_fresh_{key}' là cờ tươi theo TTL.
 * Còn tươi → trả option. Hết hạn → gọi $fetch: thành công cập nhật cả hai; hỏng (null)
 * → phục vụ option cũ nếu có, else [].
 */
function bd_fd_get($key, $ttl, callable $fetch) {
    $opt_key = 'bd_fd_' . $key;
    $data = get_option($opt_key, null);

    if (get_transient('bd_fd_fresh_' . $key) && $data !== null) {
        return $data;
    }

    $fresh = $fetch();
    if ($fresh !== null) {
        update_option($opt_key, $fresh, false);
        set_transient('bd_fd_fresh_' . $key, 1, $ttl);
        return $fresh;
    }

    return $data ?? [];
}

/** BXH 1 giải theo mã. Mảng hàng. TTL 6h. */
function bd_fd_standings($code) {
    if (!$code) return [];
    return bd_fd_get('standings_' . $code, 6 * HOUR_IN_SECONDS, function () use ($code) {
        $data = bd_fd_api("/competitions/$code/standings");
        if (!$data) return null;
        $table = $data['standings'][0]['table'] ?? [];
        $rows = [];
        foreach ($table as $r) {
            $rows[] = [
                'position' => (int) ($r['position'] ?? 0),
                'name'     => $r['team']['name'] ?? '',
                'crest'    => $r['team']['crest'] ?? '',
                'played'   => (int) ($r['playedGames'] ?? 0),
                'won'      => (int) ($r['won'] ?? 0),
                'draw'     => (int) ($r['draw'] ?? 0),
                'lost'     => (int) ($r['lost'] ?? 0),
                'gd'       => (int) ($r['goalDifference'] ?? 0),
                'points'   => (int) ($r['points'] ?? 0),
            ];
        }
        return $rows;
    });
}

/** Lịch/kết quả trong cửa sổ -7..+14 ngày. Mảng trận. TTL 3h. */
function bd_fd_fixtures($code) {
    if (!$code) return [];
    return bd_fd_get('fixtures_' . $code, 3 * HOUR_IN_SECONDS, function () use ($code) {
        $from = gmdate('Y-m-d', time() - 7 * DAY_IN_SECONDS);
        $to   = gmdate('Y-m-d', time() + 14 * DAY_IN_SECONDS);
        $data = bd_fd_api("/competitions/$code/matches", ['dateFrom' => $from, 'dateTo' => $to]);
        if (!$data) return null;
        $out = [];
        foreach (($data['matches'] ?? []) as $m) {
            $out[] = [
                'utcDate'   => $m['utcDate'] ?? '',
                'status'    => $m['status'] ?? '',
                'home'      => $m['homeTeam']['name'] ?? '',
                'homeCrest' => $m['homeTeam']['crest'] ?? '',
                'away'      => $m['awayTeam']['name'] ?? '',
                'awayCrest' => $m['awayTeam']['crest'] ?? '',
                'sh'        => $m['score']['fullTime']['home'] ?? null,
                'sa'        => $m['score']['fullTime']['away'] ?? null,
            ];
        }
        return $out;
    });
}

/**
 * Gộp trận nổi bật 5 giải: tối đa $limit trận cân bằng quanh now
 * (vừa đá + sắp tới), sắp tăng dần theo utcDate để hiển thị.
 * Mỗi phần tử = 1 trận của bd_fd_fixtures() + 'ts' (unix) + 'league_slug' + 'league_name'.
 * Tái dùng cache bd_fd_fixtures() — không thêm API endpoint.
 */
function bd_fd_featured_matches($limit = 8) {
    $finished = [];
    $upcoming = [];
    foreach (BD_FD_LEAGUES as $slug => $lg) {
        foreach (bd_fd_fixtures($lg['code']) as $m) {
            if (empty($m['utcDate'])) continue;
            $ts = strtotime($m['utcDate']);
            if ($ts === false) continue;
            $m['ts']          = $ts;
            $m['league_slug'] = $slug;
            $m['league_name'] = $lg['name'];
            if ($m['status'] === 'FINISHED') {
                $finished[] = $m;
            } elseif ($m['status'] === 'SCHEDULED' || $m['status'] === 'TIMED') {
                $upcoming[] = $m;
            }
            // IN_PLAY / PAUSED / khác → bỏ qua (live ngoài phạm vi)
        }
    }

    // finished: gần now nhất trước (giảm dần theo ts); upcoming: gần now nhất trước (tăng dần)
    usort($finished, function ($a, $b) { return $b['ts'] <=> $a['ts']; });
    usort($upcoming, function ($a, $b) { return $a['ts'] <=> $b['ts']; });

    $taken_finished = array_slice($finished, 0, intdiv($limit, 2));
    $taken_upcoming = array_slice($upcoming, 0, $limit - count($taken_finished));
    // upcoming ít → lấp thêm finished tới $limit
    if (count($taken_finished) + count($taken_upcoming) < $limit) {
        $taken_finished = array_slice($finished, 0, $limit - count($taken_upcoming));
    }

    $out = array_merge($taken_finished, $taken_upcoming);
    usort($out, function ($a, $b) { return $a['ts'] <=> $b['ts']; }); // hiển thị: quá khứ → tương lai
    return $out;
}

/**
 * Kết quả (FINISHED) 5 giải trong $days ngày gần nhất, nhóm theo ngày (giờ site) giảm dần.
 * Trả: [ ['label'=>'Hôm nay'|'Hôm qua'|'dd/mm', 'date'=>'Y-m-d', 'matches'=>[...]], ... ]
 * Mỗi match = 1 trận bd_fd_fixtures() + 'ts' (unix) + 'league_slug' + 'league_name'.
 * Tái dùng cache bd_fd_fixtures() — không thêm API.
 */
function bd_fd_results_by_date($days = 7) {
    $cutoff  = time() - $days * DAY_IN_SECONDS;
    $matches = [];
    foreach (BD_FD_LEAGUES as $slug => $lg) {
        foreach (bd_fd_fixtures($lg['code']) as $m) {
            if (($m['status'] ?? '') !== 'FINISHED') continue;
            if (empty($m['utcDate'])) continue;
            $ts = strtotime($m['utcDate']);
            if ($ts === false || $ts < $cutoff) continue;
            $m['ts']          = $ts;
            $m['league_slug'] = $slug;
            $m['league_name'] = $lg['name'];
            $matches[] = $m;
        }
    }
    if (!$matches) return [];

    $groups = [];
    foreach ($matches as $m) {
        $groups[wp_date('Y-m-d', $m['ts'])][] = $m;
    }
    krsort($groups); // ngày giảm dần (khóa ISO sort chuỗi = đúng thứ tự)

    $today     = wp_date('Y-m-d');
    $yesterday = wp_date('Y-m-d', time() - DAY_IN_SECONDS);
    $out = [];
    foreach ($groups as $day => $ms) {
        usort($ms, function ($a, $b) { return $b['ts'] <=> $a['ts']; }); // trong ngày: mới nhất trước
        if ($day === $today)          $label = 'Hôm nay';
        elseif ($day === $yesterday)  $label = 'Hôm qua';
        else                          $label = wp_date('d/m', $ms[0]['ts']);
        $out[] = ['label' => $label, 'date' => $day, 'matches' => $ms];
    }
    return $out;
}

// ─── Logo đội/giải cho card nhận định (khớp tên qua BXH 5 giải) ─────────────

/** Chuẩn hoá tên đội để khớp giữa insight (chữ tự do) và football-data. */
function bd_fd_norm_team($name) {
    $s = function_exists('remove_accents') ? remove_accents((string) $name) : (string) $name;
    $s = strtolower($s);
    // bỏ hậu tố/tiền tố CLB phổ biến để "Arsenal" khớp "Arsenal FC"
    $s = preg_replace('/\b(fc|cf|afc|sc|ac|as|cd|ssc|rc|us|ss|bsc|vfl|vfb|tsg|fk|club)\b/', ' ', $s);
    return preg_replace('/[^a-z0-9]+/', '', $s);
}

/**
 * Logo đội + giải cho 1 tên đội, khớp qua BXH 5 giải (cache sẵn). KHỚP CHÍNH XÁC
 * theo tên chuẩn hoá (không khớp mờ → tránh gắn nhầm logo). Trả
 * ['crest'=>url,'league_code'=>code,'league_emblem'=>url] hoặc [] nếu không khớp.
 */
function bd_insight_team_logo($name) {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (BD_FD_LEAGUES as $info) {
            foreach (bd_fd_standings($info['code']) as $row) {
                $k = bd_fd_norm_team($row['name']);
                if ($k !== '' && !isset($map[$k]) && !empty($row['crest'])) {
                    $map[$k] = ['crest' => $row['crest'], 'league_code' => $info['code']];
                }
            }
        }
    }
    // Alias tên rút gọn (bot/Gemini hay dùng) → tên chuẩn hoá của football-data.
    static $alias = [
        'mancity' => 'manchestercity',
        'manutd' => 'manchesterunited', 'manunited' => 'manchesterunited', 'mu' => 'manchesterunited',
        'newcastle' => 'newcastleunited', 'leeds' => 'leedsunited',
        'spurs' => 'tottenhamhotspur', 'tottenham' => 'tottenhamhotspur',
        'wolves' => 'wolverhamptonwanderers',
        'bayern' => 'bayernmunchen', 'bayernmunich' => 'bayernmunchen',
        'leverkusen' => 'bayer04leverkusen', 'dortmund' => 'borussiadortmund', 'bvb' => 'borussiadortmund',
        'gladbach' => 'borussiamonchengladbach',
        'inter' => 'internazionalemilano', 'intermilan' => 'internazionalemilano',
        'juve' => 'juventus',
        'atletico' => 'atleticodemadrid', 'atleticomadrid' => 'atleticodemadrid', 'atleti' => 'atleticodemadrid',
        'psg' => 'parissaintgermain', 'parissg' => 'parissaintgermain',
        'barca' => 'barcelona', 'sociedad' => 'realsociedad',
    ];
    $k = bd_fd_norm_team($name);
    if ($k === '') {
        return [];
    }
    $key = isset($map[$k]) ? $k : ($alias[$k] ?? null);
    if ($key === null || !isset($map[$key])) {
        return [];
    }
    $hit = $map[$key];
    $hit['league_emblem'] = 'https://crests.football-data.org/' . $hit['league_code'] . '.png';
    return $hit;
}

/** HTML tên đội kèm logo (nếu khớp). Escape đầy đủ. */
function bd_insight_team_badge($name) {
    $logo = bd_insight_team_logo($name);
    $out  = '<span class="inline-flex items-center gap-1.5 min-w-0">';
    if (!empty($logo['crest'])) {
        $out .= '<img src="' . esc_url($logo['crest']) . '" alt="" width="24" height="24" class="w-6 h-6 object-contain shrink-0" loading="lazy" decoding="async">';
    }
    $out .= '<span class="truncate">' . esc_html($name) . '</span></span>';
    return $out;
}

/** Emblem giải của card (lấy từ đội nào khớp trước). '' nếu không đội nào khớp. */
function bd_insight_league_emblem($home, $away) {
    foreach ([$home, $away] as $n) {
        $l = bd_insight_team_logo($n);
        if (!empty($l['league_emblem'])) {
            return $l['league_emblem'];
        }
    }
    return '';
}
