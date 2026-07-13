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
