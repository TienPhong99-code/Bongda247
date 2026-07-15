<?php
defined('ABSPATH') || exit;

/**
 * JSON-LD SportsEvent cho CPT match_insight (nhận định trận).
 * RankMath không map được field trận (home/away_team, match_date) nên hand-code riêng.
 * KHÔNG trùng: RankMath đặt pt_match_insight_default_rich_snippet = off (chỉ xuất Breadcrumb).
 */
add_action('wp_head', function () {
    if (!is_singular('match_insight')) {
        return;
    }
    $id   = get_the_ID();
    $home = trim((string) get_post_meta($id, 'home_team', true));
    $away = trim((string) get_post_meta($id, 'away_team', true));
    if ($home === '' || $away === '') {
        return; // thiếu đội → không đủ dữ liệu event
    }

    $graph = [
        '@context' => 'https://schema.org',
        '@type'    => 'SportsEvent',
        'name'     => $home . ' vs ' . $away,
        'sport'    => 'Soccer',
        'url'      => get_permalink($id),
        'homeTeam' => ['@type' => 'SportsTeam', 'name' => $home],
        'awayTeam' => ['@type' => 'SportsTeam', 'name' => $away],
    ];

    $date = trim((string) get_post_meta($id, 'match_date', true)); // ISO UTC, có thể rỗng (insight thủ công)
    if ($date !== '') {
        $graph['startDate']       = $date;
        $graph['eventStatus']     = 'https://schema.org/EventScheduled';
        $graph['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
    }

    // KHÔNG đưa `prediction` vào schema: đây là giá trị bị khóa (mở bằng điểm, xem SP3
    // inc/points.php). Nếu để trong JSON-LD, khách xem-nguồn/curl hoặc Google index đều
    // đọc được → bypass paywall. SportsEvent không cần dự đoán tỉ số.

    echo "\n<script type=\"application/ld+json\">"
        . wp_json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . "</script>\n";
}, 20);
