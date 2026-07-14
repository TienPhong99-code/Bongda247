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
