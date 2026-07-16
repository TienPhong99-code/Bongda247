<?php
defined('ABSPATH') || exit;

const BD_POINTS = ['read' => 1, 'like' => 1, 'share' => 3, 'comment' => 5];
const BD_DEDUP_META = [
    'read'    => 'bd_read_posts',
    'like'    => 'bd_like_awarded_posts',
    'share'   => 'bd_share_posts',
    'comment' => 'bd_comment_posts',
];

function bd_get_points($uid) {
    return (int) get_user_meta($uid, 'bd_points', true);
}

/**
 * Cửa cộng điểm DUY NHẤT: cộng bd_points và bơm bd_points_week (điểm kiếm trong tuần
 * ISO, lazy-reset khi sang tuần mới). Mọi nguồn cộng điểm (award action, điểm danh,
 * nhiệm vụ) đi qua đây để bảng xếp hạng tuần đồng bộ. Tiêu điểm (bd_spend_points) KHÔNG gọi.
 */
function bd_credit_points($uid, $amount) {
    $amount = (int) $amount;
    if ($amount <= 0) {
        return bd_get_points($uid);
    }
    $wk = current_time('o-\WW'); // VD 2026-W29
    if ((string) get_user_meta($uid, 'bd_week_id', true) !== $wk) {
        update_user_meta($uid, 'bd_week_id', $wk);
        update_user_meta($uid, 'bd_points_week', 0);
    }
    update_user_meta($uid, 'bd_points_week', (int) get_user_meta($uid, 'bd_points_week', true) + $amount);
    $total = bd_get_points($uid) + $amount;
    update_user_meta($uid, 'bd_points', $total);
    return $total;
}

/** Cộng điểm 1 hành động cho 1 bài (dedup). true nếu vừa cộng, false nếu đã earn / hành động lạ. */
function bd_award_points($uid, $action, $post_id) {
    if (!isset(BD_POINTS[$action])) {
        return false;
    }
    $post_id  = (int) $post_id;
    $meta_key = BD_DEDUP_META[$action];
    // array_filter bỏ chuỗi rỗng '' khi meta chưa tồn tại ((array)'' = ['']) — tránh meta bẩn.
    $earned   = array_filter((array) get_user_meta($uid, $meta_key, true));
    if (in_array($post_id, $earned, true)) {
        return false;
    }
    $earned[] = $post_id;
    update_user_meta($uid, $meta_key, $earned);
    bd_credit_points($uid, BD_POINTS[$action]);
    return true;
}

// AJAX: cộng điểm hành động đơn (read / share)
add_action('wp_ajax_bd_award', 'bd_ajax_award');
function bd_ajax_award() {
    check_ajax_referer('bd_points');
    if (!is_user_logged_in()) {
        wp_send_json_error('auth', 403);
    }
    $sub     = sanitize_key($_POST['sub'] ?? '');
    $post_id = (int) ($_POST['post_id'] ?? 0);
    if (!in_array($sub, ['read', 'share'], true) || !get_post($post_id)) {
        wp_send_json_error('invalid', 400);
    }
    $uid     = get_current_user_id();
    $awarded = bd_award_points($uid, $sub, $post_id);
    wp_send_json_success(['points' => bd_get_points($uid), 'awarded' => $awarded]);
}

// AJAX: toggle like (+ cộng điểm lần đầu)
add_action('wp_ajax_bd_toggle_like', 'bd_ajax_toggle_like');
function bd_ajax_toggle_like() {
    check_ajax_referer('bd_points');
    if (!is_user_logged_in()) {
        wp_send_json_error('auth', 403);
    }
    $post_id = (int) ($_POST['post_id'] ?? 0);
    if (!get_post($post_id)) {
        wp_send_json_error('invalid', 400);
    }
    $uid       = get_current_user_id();
    $liked     = array_filter((array) get_user_meta($uid, 'bd_liked_posts', true));
    $count     = (int) get_post_meta($post_id, 'bd_like_count', true);
    $now_liked = in_array($post_id, $liked, true);

    if ($now_liked) {
        $liked = array_values(array_diff($liked, [$post_id]));
        $count = max(0, $count - 1);
    } else {
        $liked[] = $post_id;
        $count++;
        bd_award_points($uid, 'like', $post_id); // dedup qua bd_like_awarded_posts → re-like không cộng lại
    }
    update_user_meta($uid, 'bd_liked_posts', $liked);
    update_post_meta($post_id, 'bd_like_count', $count);
    wp_send_json_success(['liked' => !$now_liked, 'count' => $count, 'points' => bd_get_points($uid)]);
}

// Cộng điểm khi user đăng nhập bình luận (5đ, dedup 1 lần/bài).
add_action('comment_post', 'bd_award_comment_points', 10, 2);
function bd_award_comment_points($comment_id, $approved) {
    if ($approved !== 1) {
        return; // chỉ khi đã duyệt (auto-approve = 1)
    }
    $c = get_comment($comment_id);
    if (!$c || (int) $c->user_id < 1) {
        return; // chỉ user đăng nhập
    }
    bd_award_points((int) $c->user_id, 'comment', (int) $c->comment_post_ID);
}

// ─── SP3: Mở khóa dự đoán ──────────────────────────────────────────────────

const BD_UNLOCK_COST = 5;

/** Trừ điểm nếu đủ. true nếu vừa trừ, false nếu không đủ. */
function bd_spend_points($uid, $amount) {
    $cur = bd_get_points($uid);
    if ($cur < $amount) {
        return false;
    }
    update_user_meta($uid, 'bd_points', $cur - $amount);
    return true;
}

/** Đã mở khóa insight này chưa? */
function bd_is_unlocked($uid, $iid) {
    $unlocked = array_filter((array) get_user_meta($uid, 'bd_unlocked_insights', true));
    return in_array((int) $iid, array_map('intval', $unlocked), true);
}

// AJAX: mở khóa dự đoán 1 match_insight (trừ 5đ, idempotent).
add_action('wp_ajax_bd_unlock', 'bd_ajax_unlock');
function bd_ajax_unlock() {
    check_ajax_referer('bd_points');
    if (!is_user_logged_in()) {
        wp_send_json_error('auth', 403);
    }
    $iid = (int) ($_POST['insight_id'] ?? 0);
    $p   = get_post($iid);
    if (!$p || $p->post_type !== 'match_insight') {
        wp_send_json_error('invalid', 400);
    }
    $uid  = get_current_user_id();
    $pred = (string) get_post_meta($iid, 'prediction', true);
    if ($pred === '') {
        wp_send_json_error('invalid', 400); // không có gì để mở → không trừ điểm
    }

    if (bd_is_unlocked($uid, $iid)) {
        wp_send_json_success(['points' => bd_get_points($uid), 'prediction' => $pred]);
    }
    if (bd_get_points($uid) < BD_UNLOCK_COST) {
        wp_send_json_error('nopoints', 402);
    }
    // Ghi mở khóa TRƯỚC, trừ điểm SAU: nếu update_user_meta lỗi thì chưa trừ điểm (không thiệt user).
    $unlocked   = array_filter((array) get_user_meta($uid, 'bd_unlocked_insights', true));
    $unlocked[] = $iid;
    update_user_meta($uid, 'bd_unlocked_insights', array_values(array_unique(array_map('intval', $unlocked))));
    bd_spend_points($uid, BD_UNLOCK_COST);
    wp_send_json_success(['points' => bd_get_points($uid), 'prediction' => $pred]);
}

/** Badge dự đoán: khóa / đã mở / khách. Dùng chung carousel + hub. */
function bd_prediction_badge($iid, $prediction) {
    $prediction = (string) $prediction;
    if ($prediction === '') {
        return '';
    }
    $badge_cls = 'inline-block mt-auto w-fit ml-auto text-sm transition-all p-2 px-4 rounded-full font-hemi bg-prediction';

    if (is_user_logged_in() && bd_is_unlocked(get_current_user_id(), $iid)) {
        return '<div data-bd-pred-gate class="mt-auto ml-auto w-fit"><div class="' . $badge_cls . '">' . esc_html($prediction) . '</div></div>';
    }

    $out = '<div data-bd-pred-gate class="mt-auto ml-auto w-fit">';
    if (!is_user_logged_in()) {
        $out .= '<a href="' . esc_url(home_url('/tai-khoan/')) . '" class="inline-flex items-center gap-1 text-xs rounded-full border border-card px-3 py-1.5 text-secondary hover:text-brand hover:border-brand transition-colors">🔒 Đăng nhập để xem dự đoán</a>';
    } else {
        $out .= '<button type="button" data-bd-unlock data-bd-insight="' . esc_attr((int) $iid) . '" data-bd-ajax="' . esc_url(admin_url('admin-ajax.php')) . '" data-bd-nonce="' . esc_attr(wp_create_nonce('bd_points')) . '" class="inline-flex items-center gap-1 text-xs rounded-full border border-brand px-3 py-1.5 text-brand hover:bg-brand hover:text-white transition-colors cursor-pointer">🔒 Mở khóa (' . (int) BD_UNLOCK_COST . ' điểm)</button>';
    }
    $out .= '</div>';
    return $out;
}
