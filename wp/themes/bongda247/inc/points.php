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
    update_user_meta($uid, 'bd_points', bd_get_points($uid) + BD_POINTS[$action]);
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
