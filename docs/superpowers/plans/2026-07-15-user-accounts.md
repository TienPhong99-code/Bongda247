# SP1 Tài khoản người dùng — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Đăng ký/đăng nhập frontend dùng engine auth WordPress + trang `/tai-khoan/` + trạng thái đăng nhập ở header.

**Architecture:** Handler form qua `admin-post.php` (`inc/auth.php`) gọi `wp_insert_user`/`wp_signon` (WP lo hash+cookie+nonce); trang `page-tai-khoan.php` (form khi chưa đăng nhập, hồ sơ khi đã) + header.php đổi theo `is_user_logged_in()`.

**Tech Stack:** WordPress (PHP 8.2), Tailwind v4 (`npm run build:css`). Verify: curl + Playwright E2E.

**Spec:** `docs/superpowers/specs/2026-07-15-user-accounts-design.md`

## Global Constraints

- Auth = engine WP: `wp_insert_user`, `wp_signon`, `wp_set_auth_cookie`, `wp_logout_url`. KHÔNG tự code hash/session.
- Mọi form: `wp_nonce_field` + handler `check_admin_referer`. POST tới `admin-post.php` (`action=bd_login|bd_register`).
- Sanitize input: `sanitize_email`, `sanitize_text_field`; mật khẩu KHÔNG sanitize (WP hash). Escape MỌI output (`esc_html`/`esc_url`/`esc_attr`).
- Role user mới = `subscriber`. Mật khẩu ≥ 8 ký tự. Đăng nhập bằng email hoặc username.
- Lỗi → redirect `/tai-khoan/?auth_error=<code>` → thông báo tiếng Việt chung; lỗi đăng nhập dùng câu chung "Email hoặc mật khẩu không đúng" (chống dò email).
- Bám class theme (`bg-card`/`bg-control`/`border-card`, `font-hemi`, `text-brand`/`text-secondary`); page-template bám `page-lich-thi-dau.php`; dropdown header bám mẫu "Giải đấu" (CSS group-hover).

---

### Task 1: `inc/auth.php` — handler đăng ký/đăng nhập (admin-post)

**Files:**
- Create: `wp/themes/bongda247/inc/auth.php`
- Modify: `wp/themes/bongda247/functions.php` (require)

**Interfaces:**
- Produces: action `bd_register` + `bd_login` (admin-post, nopriv+priv); handler validate + WP auth + redirect. Task 2 (form) POST tới đây.

- [ ] **Step 1: Tạo `inc/auth.php`**

```php
<?php
defined('ABSPATH') || exit;

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
```

- [ ] **Step 2: Require trong `functions.php`** — sau dòng require `inc/prediction.php`:
```php
require_once get_stylesheet_directory() . '/inc/auth.php';
```

- [ ] **Step 3: Verify handler đã đăng ký + không vỡ trang**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
echo "home: $(curl -s -o /dev/null -w '%{http_code}' http://bongda247.local/) (auth.php load OK nếu 200)"
./wp/bin/wp eval 'echo has_action("admin_post_nopriv_bd_register") ? "register-hook OK\n" : "register-hook MISSING\n"; echo has_action("admin_post_nopriv_bd_login") ? "login-hook OK\n" : "login-hook MISSING\n";'
```
Expected: home 200; `register-hook OK`; `login-hook OK`.

- [ ] **Step 4: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/inc/auth.php wp/themes/bongda247/functions.php
git commit -m "feat(theme): inc/auth.php — handler đăng ký/đăng nhập frontend (WP auth)"
```

---

### Task 2: Trang `/tai-khoan/` + trạng thái đăng nhập ở header

**Files:**
- Create: `wp/themes/bongda247/page-tai-khoan.php`
- Modify: `wp/themes/bongda247/header.php` (actions desktop + mobile menu)
- Build: `wp/themes/bongda247/dist/main.css`

**Interfaces:**
- Consumes: action `bd_login`/`bd_register` (Task 1); `is_user_logged_in()`, `wp_get_current_user()`, `wp_logout_url()`.

- [ ] **Step 1: Tạo `page-tai-khoan.php`**

```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Tài khoản</h1>

  <?php if (is_user_logged_in()) :
    $bd_u = wp_get_current_user(); ?>
    <div class="max-w-md rounded-2xl border border-card bg-card p-6">
      <div class="font-hemi text-xl mb-3"><?php echo esc_html($bd_u->display_name); ?></div>
      <p class="text-sm text-secondary">Email: <?php echo esc_html($bd_u->user_email); ?></p>
      <p class="text-sm text-secondary">Tham gia: <?php echo esc_html(date_i18n('d/m/Y', strtotime($bd_u->user_registered))); ?></p>
      <p class="text-sm text-secondary mt-2">Điểm: <span class="text-brand font-bold">— (sắp có)</span></p>
      <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="inline-block mt-4 text-sm text-brand hover:underline">Đăng xuất</a>
    </div>
  <?php else :
    $bd_err  = isset($_GET['auth_error']) ? sanitize_key($_GET['auth_error']) : '';
    $bd_msgs = [
      'email'       => 'Email không hợp lệ.',
      'weakpass'    => 'Mật khẩu phải từ 8 ký tự trở lên.',
      'emailexists' => 'Email này đã được đăng ký.',
      'regfail'     => 'Đăng ký không thành công, vui lòng thử lại.',
      'login'       => 'Email hoặc mật khẩu không đúng.',
    ];
  ?>
    <?php if ($bd_err && isset($bd_msgs[$bd_err])) : ?>
      <div class="max-w-3xl rounded-lg border border-red-500/40 bg-red-500/10 text-red-500 px-4 py-3 mb-6 text-sm"><?php echo esc_html($bd_msgs[$bd_err]); ?></div>
    <?php endif; ?>
    <div class="grid md:grid-cols-2 gap-6 max-w-3xl">
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="rounded-2xl border border-card bg-card p-6">
        <h2 class="font-hemi text-lg uppercase mb-4">Đăng nhập</h2>
        <input type="hidden" name="action" value="bd_login">
        <?php wp_nonce_field('bd_login'); ?>
        <label class="block text-sm text-secondary mb-1">Email hoặc tên đăng nhập</label>
        <input type="text" name="login" required class="w-full rounded-lg bg-control border border-card px-3 py-2 mb-3 text-sm focus:outline-none focus:border-brand">
        <label class="block text-sm text-secondary mb-1">Mật khẩu</label>
        <input type="password" name="password" required class="w-full rounded-lg bg-control border border-card px-3 py-2 mb-4 text-sm focus:outline-none focus:border-brand">
        <button type="submit" class="w-full rounded-lg bg-brand text-white py-2 text-sm font-medium hover:opacity-90 transition-opacity">Đăng nhập</button>
      </form>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="rounded-2xl border border-card bg-card p-6">
        <h2 class="font-hemi text-lg uppercase mb-4">Đăng ký</h2>
        <input type="hidden" name="action" value="bd_register">
        <?php wp_nonce_field('bd_register'); ?>
        <label class="block text-sm text-secondary mb-1">Tên hiển thị</label>
        <input type="text" name="display_name" required class="w-full rounded-lg bg-control border border-card px-3 py-2 mb-3 text-sm focus:outline-none focus:border-brand">
        <label class="block text-sm text-secondary mb-1">Email</label>
        <input type="email" name="email" required class="w-full rounded-lg bg-control border border-card px-3 py-2 mb-3 text-sm focus:outline-none focus:border-brand">
        <label class="block text-sm text-secondary mb-1">Mật khẩu (≥ 8 ký tự)</label>
        <input type="password" name="password" required minlength="8" class="w-full rounded-lg bg-control border border-card px-3 py-2 mb-4 text-sm focus:outline-none focus:border-brand">
        <button type="submit" class="w-full rounded-lg bg-brand text-white py-2 text-sm font-medium hover:opacity-90 transition-opacity">Đăng ký</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 2: header.php — trạng thái đăng nhập (desktop).** Trong `<div class="flex items-center space-x-3">` (vùng actions), NGAY TRƯỚC nút `data-search-toggle`, thêm:
```php
          <?php if (is_user_logged_in()) : $bd_cu = wp_get_current_user(); ?>
            <div class="hidden lg:block relative group">
              <button type="button" class="flex items-center gap-1 text-sm font-medium text-secondary hover:text-brand transition-colors cursor-pointer">
                <?php echo esc_html($bd_cu->display_name); ?>
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
              </button>
              <div class="invisible opacity-0 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100 transition-opacity absolute right-0 top-full pt-3">
                <ul class="w-40 rounded-xl border border-card bg-card shadow-xl p-2">
                  <li><a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="block px-3 py-2 rounded-lg text-sm text-secondary hover:text-brand hover:bg-control transition-colors">Tài khoản</a></li>
                  <li><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="block px-3 py-2 rounded-lg text-sm text-secondary hover:text-brand hover:bg-control transition-colors">Đăng xuất</a></li>
                </ul>
              </div>
            </div>
          <?php else : ?>
            <a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="hidden lg:block text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand transition-colors">Đăng nhập</a>
          <?php endif; ?>
```

- [ ] **Step 3: header.php — mobile menu.** SAU link "Nhận định" mobile (dòng `home_url('/nhan-dinh/')` với class `block py-2 ...`), thêm:
```php
            <?php if (is_user_logged_in()) : ?>
              <li class="border-t border-card mt-2 pt-2"></li>
              <li><a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">Tài khoản</a></li>
              <li><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">Đăng xuất</a></li>
            <?php else : ?>
              <li class="border-t border-card mt-2 pt-2"></li>
              <li><a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">Đăng nhập</a></li>
            <?php endif; ?>
```

- [ ] **Step 4: Tạo WP Page `tai-khoan`**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
./wp/bin/wp post create --post_type=page --post_status=publish --post_title="Tài khoản" --post_name="tai-khoan" --porcelain
```
Expected: in ra 1 ID. WP dùng `page-tai-khoan.php`.

- [ ] **Step 5: Build CSS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247 && npm run build:css`
Expected: `Done in ...ms`.

- [ ] **Step 6: Verify (chưa đăng nhập)**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
curl -s "http://bongda247.local/tai-khoan/" -o /tmp/tk.html -w "HTTP %{http_code}\n"
echo "form Đăng nhập: $(grep -c 'value="bd_login"' /tmp/tk.html) | form Đăng ký: $(grep -c 'value="bd_register"' /tmp/tk.html)"
echo "nonce: $(grep -c '_wpnonce' /tmp/tk.html) (>=2) | lỗi PHP: $(grep -ci 'fatal error\|warning:\|notice:' /tmp/tk.html)"
echo "header có 'Đăng nhập': $(curl -s http://bongda247.local/ | grep -c '>Đăng nhập<')"
```
Expected: HTTP 200; 2 form (bd_login=1, bd_register=1); nonce ≥ 2; lỗi PHP=0; header 'Đăng nhập' ≥ 1. Nếu lệch → điều tra, đừng sửa kỳ vọng.
*(E2E đăng ký→đăng nhập→đăng xuất: controller chạy Playwright sau — cần session/cookie, không làm bằng curl.)*

- [ ] **Step 7: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/page-tai-khoan.php wp/themes/bongda247/header.php wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): trang /tai-khoan/ (đăng nhập/đăng ký/hồ sơ) + trạng thái đăng nhập header"
```

---

## Sau khi xong 2 task

- Controller chạy Playwright E2E: đăng ký → auto-login → hồ sơ → header đổi → đăng xuất → đăng nhập lại (email). Dọn user test qua wp-cli.
- Cập nhật CLAUDE.md (route `/tai-khoan/`; `inc/auth.php`; header có auth state) + data model (user role subscriber).
- Ledger `.superpowers/sdd/user-accounts/progress.md`.
- Finishing gate (bot `npm test` 9/9 — không đụng bot; curl trang 200) → merge + push.
- Tiếp theo: **SP2 (tích điểm khi đọc)** + **SP3 (mở khóa dự đoán)** — spec riêng.
