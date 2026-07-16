<?php
defined('ABSPATH') || exit;

// ─── Điểm danh + streak ────────────────────────────────────────────────────
const BD_CHECKIN_REWARD     = 2;
const BD_STREAK_BONUS_EVERY = 7;
const BD_STREAK_BONUS       = 10;

/**
 * Điểm danh 1 lần/ngày (timezone site). Trả:
 *   ['already'=>bool, 'reward'=>int, 'streak'=>int, 'points'=>int]
 */
function bd_checkin($uid) {
    $today = current_time('Y-m-d');
    $last  = (string) get_user_meta($uid, 'bd_checkin_last', true);
    if ($last === $today) {
        return ['already' => true, 'reward' => 0,
                'streak' => (int) get_user_meta($uid, 'bd_streak', true),
                'points' => bd_get_points($uid)];
    }
    $yesterday = gmdate('Y-m-d', current_time('timestamp') - DAY_IN_SECONDS);
    $streak = ($last === $yesterday) ? ((int) get_user_meta($uid, 'bd_streak', true) + 1) : 1;

    $reward = BD_CHECKIN_REWARD;
    if ($streak % BD_STREAK_BONUS_EVERY === 0) {
        $reward += BD_STREAK_BONUS;
    }
    $points = bd_credit_points($uid, $reward);
    update_user_meta($uid, 'bd_checkin_last', $today);
    update_user_meta($uid, 'bd_streak', $streak);
    update_user_meta($uid, 'bd_streak_best', max((int) get_user_meta($uid, 'bd_streak_best', true), $streak));
    return ['already' => false, 'reward' => $reward, 'streak' => $streak, 'points' => $points];
}

add_action('wp_ajax_bd_checkin', 'bd_ajax_checkin');
function bd_ajax_checkin() {
    check_ajax_referer('bd_points');
    if (!is_user_logged_in()) {
        wp_send_json_error('auth', 403);
    }
    wp_send_json_success(bd_checkin(get_current_user_id()));
}
