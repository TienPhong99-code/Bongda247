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

// ─── Nhiệm vụ hằng ngày ────────────────────────────────────────────────────
const BD_QUESTS = [
    'read'    => ['target' => 3, 'reward' => 3, 'label' => 'Đọc 3 bài hôm nay'],
    'like'    => ['target' => 1, 'reward' => 2, 'label' => 'Thích 1 bài'],
    'comment' => ['target' => 1, 'reward' => 5, 'label' => 'Bình luận 1 bài'],
];

/** Lazy-reset khi sang ngày mới; trả progress + done hiện tại. */
function bd_quest_state($uid) {
    $today = current_time('Y-m-d');
    if ((string) get_user_meta($uid, 'bd_quest_day', true) !== $today) {
        update_user_meta($uid, 'bd_quest_day', $today);
        update_user_meta($uid, 'bd_quest_progress', []);
        update_user_meta($uid, 'bd_quest_done', []);
    }
    return [
        'progress' => (array) get_user_meta($uid, 'bd_quest_progress', true),
        'done'     => (array) get_user_meta($uid, 'bd_quest_done', true),
    ];
}

/** Tăng tiến độ 1 loại; cộng thưởng đúng 1 lần khi đạt target. */
function bd_quest_bump($uid, $type) {
    if (!isset(BD_QUESTS[$type])) {
        return ['completed' => false, 'reward' => 0];
    }
    $state    = bd_quest_state($uid); // đảm bảo đúng ngày
    $progress = $state['progress'];
    $done     = $state['done'];
    if (!empty($done[$type])) {
        return ['completed' => false, 'reward' => 0]; // đã xong hôm nay
    }
    $progress[$type] = (int) ($progress[$type] ?? 0) + 1;
    update_user_meta($uid, 'bd_quest_progress', $progress);
    if ($progress[$type] >= BD_QUESTS[$type]['target']) {
        $done[$type] = 1;
        update_user_meta($uid, 'bd_quest_done', $done);
        bd_credit_points($uid, BD_QUESTS[$type]['reward']);
        return ['completed' => true, 'reward' => BD_QUESTS[$type]['reward']];
    }
    return ['completed' => false, 'reward' => 0];
}
