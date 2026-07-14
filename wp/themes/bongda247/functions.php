<?php
defined('ABSPATH') || exit;

require_once get_stylesheet_directory() . '/inc/query.php';
require_once get_stylesheet_directory() . '/inc/football-data.php';

add_action('after_setup_theme', function () {
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('html5', ['gallery', 'caption', 'style', 'script']);
    add_image_size('bd_hero', 1200, 675, true);
    add_image_size('bd_thumb', 200, 200, true);
    register_nav_menus(['primary' => 'Menu chính']);
});

add_action('wp_enqueue_scripts', function () {
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();

    $ver = fn($rel) => file_exists($dir . $rel) ? (string) filemtime($dir . $rel) : '1.0.0';

    wp_enqueue_style('swiper', $uri . '/assets/vendor/swiper-bundle.min.css', [], $ver('/assets/vendor/swiper-bundle.min.css'));
    wp_enqueue_style('bongda247', $uri . '/dist/main.css', ['swiper'], $ver('/dist/main.css'));

    wp_enqueue_script('swiper', $uri . '/assets/vendor/swiper-bundle.min.js', [], $ver('/assets/vendor/swiper-bundle.min.js'), true);
    wp_enqueue_script('bongda247', $uri . '/dist/main.js', ['swiper'], $ver('/dist/main.js'), true);
});

// Google Fonts: Inter + Oswald
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'bongda247-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@400;500;600;700&display=swap',
        [],
        null
    );
});

// AJAX: render lại widget số liệu theo giải (đổi giải không reload trang).
add_action('wp_ajax_bd_fd_widget', 'bd_fd_widget_ajax');
add_action('wp_ajax_nopriv_bd_fd_widget', 'bd_fd_widget_ajax');
function bd_fd_widget_ajax() {
    $req  = isset($_GET['league']) ? sanitize_key(wp_unslash($_GET['league'])) : '';
    $slug = array_key_exists($req, BD_FD_LEAGUES) ? $req : 'ngoai-hang-anh';
    set_query_var('bd_fd_widget_slug', $slug);
    get_template_part('template-parts/fd-widget-body');
    wp_die();
}
