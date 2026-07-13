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
 *
 * KHÔNG dùng 'meta_key' => 'hot' + orderby meta_value_num nữa: top-level 'meta_key' khiến
 * WP_Query inner-join wp_postmeta, nên bài match_insight nào KHÔNG có row 'hot' trong DB
 * (không phải hot=0, mà thiếu hẳn — ví dụ sửa tay qua wp-admin, `wp post create` thô, hoặc
 * bot quên set) bị loại khỏi kết quả hoàn toàn mà không báo lỗi. register_post_meta() với
 * 'default' => 0 (xem mu-plugins/bongda247-core.php) KHÔNG cứu được trường hợp này: default
 * chỉ áp dụng khi ĐỌC bằng get_post_meta(), nó không tự tạo row trong wp_postmeta để JOIN.
 *
 * Cách làm ở đây: lấy ID theo ngày đăng trước (không đụng bảng meta nên không loại ai), rồi
 * tự sắp lại bằng PHP — get_post_meta() vẫn trả về default 0 cho bài thiếu row nên thứ tự
 * hot-trước vẫn đúng dù bài đó chưa từng có row 'hot'.
 *
 * Đồng thời lấy DƯ một tập ứng viên (xem $candidate_pool) thay vì đúng $n rồi mới lọc hạn
 * ở template (bd_insight_is_upcoming). Lý do: nếu chỉ lấy đúng $n bài hot/mới nhất rồi mới
 * lọc hạn, các bài hot=1 nhưng đã quá hạn (insight nhập tay, không bị cron dọn tự động của
 * bot) có thể chiếm hết ngân sách $n, khiến carousel rỗng dù bảng còn nhiều insight hợp lệ.
 */
function bd_insights($n = 15) {
    // Số ứng viên lấy dư trước khi lọc hạn — gấp nhiều lần $n để dù bảng tích tụ hàng chục
    // bài hot=1 đã quá hạn (không bị cron dọn) cũng không thể chiếm hết chỗ của các insight
    // hợp lệ còn lại. Template sẽ lọc hạn rồi mới cắt về đúng $n khi render.
    $candidate_pool = $n * 4;

    $ids = get_posts([
        'post_type'      => 'match_insight',
        'posts_per_page' => $candidate_pool,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    // Prime meta cache 1 query cho cả $candidate_pool ID — tránh usort() bên dưới gọi
    // get_post_meta() lặp lại nhiều lần, mỗi lần 1 query riêng.
    update_meta_cache('post', $ids);

    // hot=1 lên trước; usort ổn định (PHP >= 8.0, xem wp/bin/wp) nên các bài cùng nhóm
    // hot/không-hot vẫn giữ nguyên thứ tự mới nhất đã lấy ở trên.
    usort($ids, function ($a, $b) {
        return (int) get_post_meta($b, 'hot', true) <=> (int) get_post_meta($a, 'hot', true);
    });

    return new WP_Query([
        'post_type'      => 'match_insight',
        // post__in rỗng bị WP_Query hiểu là "không giới hạn" — ép về [0] để trả về rỗng.
        'post__in'       => $ids ?: [0],
        'orderby'        => 'post__in',
        'posts_per_page' => $candidate_pool,
        'no_found_rows'  => true,
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
