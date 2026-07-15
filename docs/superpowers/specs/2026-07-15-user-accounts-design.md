# SP1: Tài khoản người dùng (frontend auth) — Design

> Giai đoạn 1 của hệ thống tài khoản + điểm. SP1 = **tài khoản**: đăng ký/đăng nhập frontend dùng engine auth WordPress, trang `/tai-khoan/`, trạng thái đăng nhập ở header. (SP2 điểm, SP3 mở khóa dự đoán — spec riêng.)

## Mục tiêu

- Người đọc tạo tài khoản + đăng nhập ngay trên theme (không rời sang wp-login).
- Nền tảng "user là ai" cho SP2 (tích điểm) + SP3 (mở khóa dự đoán).
- Bảo mật bằng cách **dùng engine WordPress** (hash mật khẩu, cookie, nonce) — KHÔNG tự code auth.

## Bối cảnh & ràng buộc

- Theme chưa có auth/tài khoản. WP: `users_can_register=0`, `default_role=subscriber`, 2 user (admin, bot).
- Theme classic: pattern WP Page + `page-{slug}.php`; header có vùng actions (search + theme-toggle + hamburger) + dropdown CSS-hover (mẫu "Giải đấu"). `.container`, `bg-card`/`border-card`, `text-brand`/`text-secondary`, input mẫu ở ô search.
- Xử lý form qua **`admin-post.php`** (hook `admin_post_{action}` + `admin_post_nopriv_{action}`) — chạy trước khi xuất HTML → set cookie + `wp_safe_redirect` an toàn.

## Quyết định thiết kế (đã chốt)

| # | Quyết định | Chọn |
|---|-----------|------|
| Auth | Cách làm | **Engine WP + form tự thiết kế** (không plugin, không tự code auth) |
| Giao diện | Vị trí form | **Trang riêng `/tai-khoan/`** |
| Đăng nhập | Định danh | Bằng **email hoặc username** |
| Xác thực email | Giai đoạn 1 | **KHÔNG** (tự kích hoạt + auto-login) — thêm khi bật nạp tiền |
| Role | Quyền | `subscriber` (người đọc) |

## Kiến trúc / thành phần

### 1. `inc/auth.php` (MỚI) — xử lý form (admin-post), require trong `functions.php`
- **`bd_register`** (`admin_post_nopriv_bd_register` + `admin_post_bd_register`):
  - `check_admin_referer('bd_register')`; `$email = sanitize_email($_POST['email'])`, `is_email` → không hợp lệ → redirect `?auth_error=email`.
  - `$name = sanitize_text_field($_POST['display_name'])`; `$pass = (string) $_POST['password']` (KHÔNG sanitize mật khẩu — WP tự hash); `strlen($pass) < 8` → `?auth_error=weakpass`.
  - `email_exists($email)` → `?auth_error=emailexists`.
  - Sinh username từ phần trước @ của email (`sanitize_user`), uniquify nếu trùng (`username_exists` → thêm số).
  - `wp_insert_user(['user_login'=>$username,'user_email'=>$email,'user_pass'=>$pass,'display_name'=>$name,'role'=>'subscriber'])`; `is_wp_error` → `?auth_error=regfail`.
  - Auto-login: `wp_set_current_user($uid)` + `wp_set_auth_cookie($uid, true)`; `wp_safe_redirect(home_url('/tai-khoan/'))` + `exit`.
- **`bd_login`** (nopriv + priv):
  - `check_admin_referer('bd_login')`; `$id = sanitize_text_field($_POST['login'])`. Nếu `is_email($id)` → `get_user_by('email',$id)` → dùng `user_login` tương ứng (wp_signon cần username).
  - `wp_signon(['user_login'=>$username,'user_password'=>(string)$_POST['password'],'remember'=>true], is_ssl())`; `is_wp_error` → `?auth_error=login`; else `wp_safe_redirect(home_url('/tai-khoan/'))` + `exit`.
- Đăng xuất: dùng `wp_logout_url(home_url('/'))` (WP có nonce sẵn) — KHÔNG cần handler riêng.

### 2. `page-tai-khoan.php` (MỚI) — trang `/tai-khoan/`
- **`is_user_logged_in()`:** hồ sơ — `display_name`, `user_email`, ngày tham gia (`user_registered`); chỗ dành sẵn "Điểm: — (sắp có)" cho SP2; nút **Đăng xuất** (`wp_logout_url`).
- **Chưa đăng nhập:** 2 form cạnh nhau (Đăng nhập | Đăng ký), mỗi form:
  - `<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">` + `<input type="hidden" name="action" value="bd_login|bd_register">` + `wp_nonce_field('bd_login'|'bd_register')`.
  - Login: `login` (email/username) + `password`. Register: `display_name` + `email` + `password`.
  - Đọc `$_GET['auth_error']` → map ra thông báo tiếng Việt (email/weakpass/emailexists/regfail/login → câu tương ứng).
- Escape toàn bộ output.

### 3. `header.php` (SỬA) — trạng thái đăng nhập
- Chưa đăng nhập: link **"Đăng nhập"** (`/tai-khoan/`) trong vùng actions (desktop + mobile).
- Đã đăng nhập: **tên hiển thị** + dropdown CSS-hover (mẫu "Giải đấu"): *Tài khoản* (`/tai-khoan/`) · *Đăng xuất* (`wp_logout_url(home_url('/'))`).

### 4. WP Page slug `tai-khoan` (wp-cli).

## Data flow

Form (page-tai-khoan) → POST `admin-post.php` (action bd_login/bd_register) → `inc/auth.php` handler (validate + WP auth) → set cookie + redirect về `/tai-khoan/` (thành công) hoặc `?auth_error=` (lỗi). Header + trang đọc `is_user_logged_in()` để đổi giao diện.

## Error handling

- Nonce hỏng → `check_admin_referer` tự chặn (403).
- Email không hợp lệ / trùng / mật khẩu < 8 / sai đăng nhập / tạo user lỗi → redirect kèm `auth_error` (mã) → thông báo tiếng Việt chung, KHÔNG lộ chi tiết (tránh dò email tồn tại: thông báo login sai dùng câu chung "Email hoặc mật khẩu không đúng").
- Escape mọi output; mật khẩu không echo lại.

## Kiểm thử

- **Playwright E2E:** vào `/tai-khoan/` → đăng ký (tên + email + mật khẩu ≥8) → tự đăng nhập → trang hiện hồ sơ (tên/email) → header hiện tên + dropdown → Đăng xuất → header về "Đăng nhập" → đăng nhập lại bằng email → OK. Thử lỗi: mật khẩu ngắn → thông báo; email trùng → thông báo.
- **wp-cli:** xác nhận user test tạo (role subscriber). **Dọn user test** sau (`wp user delete`).
- `curl /tai-khoan/` HTTP 200, 0 lỗi PHP.

## Tiêu chí thành công

- [ ] `/tai-khoan/` cho đăng ký + đăng nhập (email/username), auto-login sau đăng ký; hồ sơ khi đã đăng nhập.
- [ ] Header đổi trạng thái (Đăng nhập ↔ tên user + Đăng xuất).
- [ ] Auth dùng engine WP (nonce, hash, cookie); role subscriber; escape đầy đủ; lỗi thông báo an toàn.
- [ ] Không plugin, không tự code hash/session.

## Ngoài phạm vi (SP1)

- Điểm (SP2), mở khóa dự đoán (SP3). Xác thực email, quên mật khẩu (dùng WP có sẵn sau), social login, rate-limit đăng nhập (thêm khi cần), nạp tiền.
