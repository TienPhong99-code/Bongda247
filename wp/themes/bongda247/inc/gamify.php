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

// ─── Huy hiệu (suy ra từ chỉ số, không lưu state) ──────────────────────────
// tier ∈ bronze|silver|gold|brand ; metric ∈ points|streak|read|comment|unlock
const BD_BADGES = [
    ['id'=>'rookie',    'name'=>'Người mới',     'desc'=>'Đạt 100 điểm',       'icon'=>'🥉','tier'=>'bronze','metric'=>'points', 'need'=>100],
    ['id'=>'pro',       'name'=>'Cao thủ',       'desc'=>'Đạt 500 điểm',       'icon'=>'🥈','tier'=>'silver','metric'=>'points', 'need'=>500],
    ['id'=>'legend',    'name'=>'Huyền thoại',   'desc'=>'Đạt 2000 điểm',      'icon'=>'🥇','tier'=>'gold',  'metric'=>'points', 'need'=>2000],
    ['id'=>'diligent',  'name'=>'Chuyên cần',    'desc'=>'Streak 7 ngày',      'icon'=>'🔥','tier'=>'bronze','metric'=>'streak', 'need'=>7],
    ['id'=>'steadfast', 'name'=>'Kiên định',     'desc'=>'Streak 30 ngày',     'icon'=>'💎','tier'=>'gold',  'metric'=>'streak', 'need'=>30],
    ['id'=>'reader',    'name'=>'Mọt tin',       'desc'=>'Đọc 50 bài',         'icon'=>'📰','tier'=>'silver','metric'=>'read',   'need'=>50],
    ['id'=>'talker',    'name'=>'Nhà bình luận', 'desc'=>'Bình luận 20 bài',   'icon'=>'💬','tier'=>'silver','metric'=>'comment','need'=>20],
    ['id'=>'oracle',    'name'=>'Nhà tiên tri',  'desc'=>'Mở khóa 20 dự đoán', 'icon'=>'🔮','tier'=>'gold',  'metric'=>'unlock', 'need'=>20],
];

function bd_badge_metric($uid, $metric) {
    switch ($metric) {
        case 'points':  return bd_get_points($uid);
        case 'streak':  return (int) get_user_meta($uid, 'bd_streak_best', true);
        case 'read':    return count(array_filter((array) get_user_meta($uid, 'bd_read_posts', true)));
        case 'comment': return count(array_filter((array) get_user_meta($uid, 'bd_comment_posts', true)));
        case 'unlock':  return count(array_filter((array) get_user_meta($uid, 'bd_unlocked_insights', true)));
    }
    return 0;
}

/** Thứ hạng tier để chọn huy hiệu "cao nhất". */
function bd_badge_tier_rank($tier) {
    $rank = ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'brand' => 3];
    return $rank[$tier] ?? 0;
}

/** Tất cả huy hiệu + earned (bool). Không lưu meta. */
function bd_user_badges($uid) {
    $out = [];
    foreach (BD_BADGES as $b) {
        $b['earned'] = bd_badge_metric($uid, $b['metric']) >= $b['need'];
        $out[] = $b;
    }
    return $out;
}
