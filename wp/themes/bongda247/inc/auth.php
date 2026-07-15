<?php
defined('ABSPATH') || exit;

// Ẩn thanh admin bar WordPress với người đọc (dưới quyền editor) — trải nghiệm frontend sạch.
add_filter('show_admin_bar', function ($show) {
    return current_user_can('edit_posts') ? $show : false;
});

/** Redirect về trang tài khoản, kèm mã lỗi nếu có. */
function bd_auth_redirect($error = '') {
    $url = home_url('/tai-khoan/');
    if ($error) {
        $url = add_query_arg('auth_error', $error, $url);
    }
    wp_safe_redirect($url);
    exit;
}

add_action('admin_post_nopriv_bd_register', 'bd_handle_register');
add_action('admin_post_bd_register', 'bd_handle_register');
function bd_handle_register() {
    check_admin_referer('bd_register');
    $email = sanitize_email($_POST['email'] ?? '');
    $name  = sanitize_text_field($_POST['display_name'] ?? '');
    $pass  = (string) ($_POST['password'] ?? '');

    if (!is_email($email))    bd_auth_redirect('email');
    if (strlen($pass) < 8)    bd_auth_redirect('weakpass');
    if (email_exists($email)) bd_auth_redirect('emailexists');

    // username từ phần trước @, uniquify nếu trùng
    $base = sanitize_user(current(explode('@', $email)), true) ?: 'user';
    $username = $base;
    $i = 1;
    while (username_exists($username)) {
        $username = $base . $i;
        $i++;
    }

    $uid = wp_insert_user([
        'user_login'   => $username,
        'user_email'   => $email,
        'user_pass'    => $pass,
        'display_name' => $name !== '' ? $name : $username,
        'role'         => 'subscriber',
    ]);
    if (is_wp_error($uid)) {
        bd_auth_redirect('regfail');
    }

    wp_set_current_user($uid);
    wp_set_auth_cookie($uid, true);
    bd_auth_redirect();
}

add_action('admin_post_nopriv_bd_login', 'bd_handle_login');
add_action('admin_post_bd_login', 'bd_handle_login');
function bd_handle_login() {
    check_admin_referer('bd_login');
    $id = sanitize_text_field($_POST['login'] ?? '');
    // Cho phép đăng nhập bằng email → đổi ra username cho wp_signon
    if (is_email($id)) {
        $u = get_user_by('email', $id);
        if ($u) {
            $id = $u->user_login;
        }
    }
    $user = wp_signon([
        'user_login'    => $id,
        'user_password' => (string) ($_POST['password'] ?? ''),
        'remember'      => true,
    ], is_ssl());
    if (is_wp_error($user)) {
        bd_auth_redirect('login');
    }
    bd_auth_redirect();
}
