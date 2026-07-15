<?php
/**
 * Plugin Name: Bongda247 Core
 * Description: CPT match_insight + post meta để bot đẩy dữ liệu qua REST API.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Quyền ghi meta qua REST. Bot chạy bằng user role editor.
 */
function bd_meta_auth() {
    return current_user_can('edit_posts');
}

add_action('init', 'bd_register_match_insight');
function bd_register_match_insight() {
    register_post_type('match_insight', [
        'labels' => [
            'name'          => 'Nhận định trận',
            'singular_name' => 'Nhận định trận',
            'menu_name'     => 'Nhận định trận',
            'add_new_item'  => 'Thêm nhận định trận',
            'edit_item'     => 'Sửa nhận định trận',
        ],
        'public'       => true,
        'has_archive'  => false,
        'menu_icon'    => 'dashicons-shield-alt',
        'supports'     => ['title', 'custom-fields'],
        'show_in_rest' => true,
        'rest_base'    => 'match_insight',
        'rewrite'      => ['slug' => 'nhan-dinh-tran'],
    ]);
}

add_action('init', 'bd_register_prediction');
function bd_register_prediction() {
    register_post_type('bd_prediction', [
        'labels' => [
            'name'          => 'Dự đoán',
            'singular_name' => 'Dự đoán',
            'menu_name'     => 'Dự đoán',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_rest' => true,
        'rest_base'    => 'bd_prediction',
        'menu_icon'    => 'dashicons-chart-line',
        'supports'     => ['title', 'custom-fields'],
    ]);
}

add_action('init', 'bd_register_meta');
function bd_register_meta() {
    // Meta chuỗi của match_insight
    foreach (['home_team', 'away_team', 'match_time', 'match_date', 'prediction'] as $key) {
        register_post_meta('match_insight', $key, [
            'type'          => 'string',
            'single'        => true,
            'default'       => '',
            'show_in_rest'  => true,
            'auth_callback' => 'bd_meta_auth',
        ]);
    }

    // hot: dùng integer 0/1 (KHÔNG dùng boolean) để WP_Query orderby meta_value_num
    // sắp xếp tin cậy — boolean false được WP lưu thành chuỗi rỗng, sort không ổn định.
    register_post_meta('match_insight', 'hot', [
        'type'          => 'integer',
        'single'        => true,
        'default'       => 0,
        'show_in_rest'  => true,
        'auth_callback' => 'bd_meta_auth',
    ]);

    // insights: mảng chuỗi. REST CHỈ ghi được nếu khai đủ schema.items.type —
    // thiếu schema thì WP im lặng bỏ qua field này khi POST.
    register_post_meta('match_insight', 'insights', [
        'type'          => 'array',
        'single'        => true,
        'default'       => [],
        'show_in_rest'  => [
            'schema' => [
                'type'  => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
        'auth_callback' => 'bd_meta_auth',
    ]);

    // Meta CPT bd_prediction — theo dõi độ chính xác nhận định
    foreach (['home_team', 'away_team', 'league_code', 'match_date', 'pred_text', 'status', 'settled_at'] as $key) {
        register_post_meta('bd_prediction', $key, [
            'type'          => 'string',
            'single'        => true,
            'default'       => '',
            'show_in_rest'  => true,
            'auth_callback' => 'bd_meta_auth',
        ]);
    }
    foreach (['match_id', 'pred_home', 'pred_away', 'actual_home', 'actual_away', 'outcome_correct', 'score_correct'] as $key) {
        register_post_meta('bd_prediction', $key, [
            'type'          => 'integer',
            'single'        => true,
            'default'       => 0,
            'show_in_rest'  => true,
            'auth_callback' => 'bd_meta_auth',
        ]);
    }

    // Nguồn bài RSS trên post thường
    foreach (['source_url', 'source_credit'] as $key) {
        register_post_meta('post', $key, [
            'type'          => 'string',
            'single'        => true,
            'default'       => '',
            'show_in_rest'  => true,
            'auth_callback' => 'bd_meta_auth',
        ]);
    }
}

/**
 * Bảo mật: tước quyền unfiltered_html của user bot.
 *
 * Trên site single-site, role editor mặc định có capability unfiltered_html,
 * nghĩa là content_save_pre KHÔNG chạy qua wp_kses_post — nội dung post do
 * user bot lưu được ghi thẳng vào DB và render RAW qua the_content(). Bot
 * đăng bài bằng HTML do Gemini viết lại từ RSS bên thứ ba: một lần bị
 * prompt-injection hoặc hallucination chèn <script>/onerror... sẽ được lưu
 * y nguyên và chạy trong trình duyệt người xem.
 *
 * Bot chỉ cần phát <h2>, <p>, <figure><img><figcaption> — các tag này đều
 * nằm trong whitelist mặc định của wp_kses_post nên việc bị KSES lọc không
 * ảnh hưởng gì tới đăng bài, upload ảnh, tạo tag hay ghi post meta.
 *
 * Dùng filter user_has_cap (chạy mỗi lần current_user_can() được gọi, kể cả
 * sau khi WP xác thực Application Password ở set_current_user) thay vì sửa
 * capability trong DB — filter nằm trong version control, không thể bị
 * plugin khác hay thao tác reset role âm thầm gỡ bỏ. Nhận diện qua
 * user_login (không hardcode ID) để không phụ thuộc DB cụ thể của môi
 * trường nào.
 */
add_filter('user_has_cap', 'bd_deny_unfiltered_html_for_bot', 10, 4);
function bd_deny_unfiltered_html_for_bot($allcaps, $caps, $args, $user) {
    if (!empty($allcaps['unfiltered_html']) && isset($user->user_login) && $user->user_login === 'bot') {
        $allcaps['unfiltered_html'] = false;
    }

    return $allcaps;
}
