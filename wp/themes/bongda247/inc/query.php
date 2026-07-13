<?php
defined('ABSPATH') || exit;

/** 5 bài mới nhất — carousel tin hot trang chủ. */
function bd_hot_posts($n = 5) {
    return new WP_Query([
        'post_type'           => 'post',
        'posts_per_page'      => $n,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ]);
}

/** 10 bài mới nhất — sidebar "Tin mới nhận". */
function bd_sidebar_posts($n = 10) {
    return new WP_Query([
        'post_type'           => 'post',
        'posts_per_page'      => $n,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ]);
}

/**
 * Nhận định trận — hot lên trước, rồi tới mới nhất.
 * hot lưu dạng integer 0/1 nên meta_value_num sắp xếp tin cậy.
 */
function bd_insights($n = 15) {
    return new WP_Query([
        'post_type'      => 'match_insight',
        'posts_per_page' => $n,
        'no_found_rows'  => true,
        'meta_key'       => 'hot',
        'orderby'        => ['meta_value_num' => 'DESC', 'date' => 'DESC'],
    ]);
}

/**
 * Insight còn nên hiển thị không?
 *
 * Ưu tiên $match_date (ISO UTC đầy đủ, do bot lấy từ football-data.org) — chính xác
 * và đúng cả khi bắc cầu qua năm mới.
 *
 * Chỉ khi không có $match_date (insight nhập tay qua Telegram) mới rơi về parse chuỗi
 * "HH:mm - DD/MM". Nhánh fallback này so ngày/tháng mà không so năm nên sai quanh
 * giao thừa — chấp nhận được vì nó chỉ áp dụng cho insight thủ công.
 */
function bd_insight_is_upcoming($match_time, $match_date = '') {
    // Nhánh chính: có datetime đầy đủ.
    if ($match_date) {
        $ts = strtotime($match_date);
        if ($ts) {
            // Cho hiển thị tới 3 tiếng sau giờ bóng lăn — khớp ngưỡng cron dọn dẹp của bot.
            return $ts > (time() - 3 * HOUR_IN_SECONDS);
        }
    }

    // Fallback: chỉ có chuỗi "HH:mm - DD/MM".
    if (!$match_time) {
        return false;
    }

    $parts    = explode(' - ', $match_time);
    $date_str = count($parts) > 1 ? $parts[1] : $parts[0];

    if (!str_contains($date_str, '/')) {
        return true;
    }

    $bits  = explode('/', $date_str);
    $day   = (int) ($bits[0] ?? 0);
    $month = (int) ($bits[1] ?? 0);

    $now_day   = (int) current_time('j');
    $now_month = (int) current_time('n');

    if ($month > $now_month) {
        return true;
    }

    return $month === $now_month && $day >= $now_day;
}
