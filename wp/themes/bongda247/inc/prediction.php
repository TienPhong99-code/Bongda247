<?php
defined('ABSPATH') || exit;

/**
 * Số liệu độ chính xác từ CPT bd_prediction (chỉ record status=settled).
 * @return array ['total','outcome_correct','score_correct','outcome_pct','score_pct','recent']
 */
function bd_prediction_stats($recent = 10) {
    $settled = new WP_Query([
        'post_type'      => 'bd_prediction',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [['key' => 'status', 'value' => 'settled']],
    ]);
    $ids = $settled->posts;
    if ($ids) {
        update_meta_cache('post', $ids);
    }
    $total   = count($ids);
    $outcome = 0;
    $score   = 0;
    foreach ($ids as $id) {
        $outcome += (int) get_post_meta($id, 'outcome_correct', true);
        $score   += (int) get_post_meta($id, 'score_correct', true);
    }

    $rows = [];
    if ($total > 0 && $recent > 0) {
        $recentQ = new WP_Query([
            'post_type'      => 'bd_prediction',
            'posts_per_page' => $recent,
            'no_found_rows'  => true,
            'meta_query'     => [['key' => 'status', 'value' => 'settled']],
            'meta_key'       => 'match_date',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
        ]);
        foreach ($recentQ->posts as $p) {
            $id = $p->ID;
            $rows[] = [
                'home'            => (string) get_post_meta($id, 'home_team', true),
                'away'            => (string) get_post_meta($id, 'away_team', true),
                'pred_home'       => (int) get_post_meta($id, 'pred_home', true),
                'pred_away'       => (int) get_post_meta($id, 'pred_away', true),
                'actual_home'     => (int) get_post_meta($id, 'actual_home', true),
                'actual_away'     => (int) get_post_meta($id, 'actual_away', true),
                'outcome_correct' => (int) get_post_meta($id, 'outcome_correct', true),
                'league_code'     => (string) get_post_meta($id, 'league_code', true),
            ];
        }
        wp_reset_postdata();
    }

    return [
        'total'           => $total,
        'outcome_correct' => $outcome,
        'score_correct'   => $score,
        'outcome_pct'     => $total ? (int) round($outcome * 100 / $total) : 0,
        'score_pct'       => $total ? (int) round($score * 100 / $total) : 0,
        'recent'          => $rows,
    ];
}
