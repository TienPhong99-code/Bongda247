# Dashboard Sidebar Layout — Design

> Thêm **sidebar điều hướng cố định bên trái** (desktop) cho tương tác nhanh; **giữ nguyên header chính** trên đầu; content dời phải. Theo ảnh mẫu dashboard user gửi.

## Mục tiêu

- Cho người dùng **điều hướng nhanh** giữa các mục chính bằng sidebar dọc luôn hiện (desktop), thay vì phải lên header mỗi lần.
- Nổi bật **trạng thái tài khoản** (điểm/streak) ở đáy sidebar — kết nối hệ điểm SP1-4.
- **KHÔNG đụng** header chính (giữ trên đầu) và **KHÔNG đụng** template nội dung (chỉ dời phải theo offset).

## Bối cảnh & ràng buộc

- `header.php`: `<header class="fixed top-0 w-full z-50 header">` (h-16) + nav desktop + auth/points + search + theme-toggle + hamburger `data-menu-toggle` + `#bd-mobile-menu` (menu mobile đầy đủ) → kết thúc bằng `<main class="pt-24 pb-16">`.
- `footer.php`: đóng `</main>` + `<footer class="border-t border-card py-10">` + `wp_footer()`.
- Nội dung mọi trang dùng `.container` (căn giữa max-width). Tailwind v4 build `npm run build:css`.
- Route sẵn có: `/` (front-page), `/nhan-dinh/`, `/ket-qua-bong-da/`, `/lich-thi-dau/`, `/bang-xep-hang/`, `/bang-xep-hang-thanh-vien/` (đều là WP Page slug tương ứng, trừ front-page).
- Icon: dùng **Tabler Icons (MIT)** nhúng thẳng (đồng bộ badge-grid SP4).

## Quyết định thiết kế (đã chốt)

| # | Quyết định | Chọn |
|---|-----------|------|
| Header chính | Giữ hay bỏ | **GIỮ NGUYÊN** trên đầu (full-width, fixed top) |
| Sidebar | Vị trí | Cố định trái, **dưới header** (`top-16 → bottom-0`), rộng `w-60` |
| Mobile | Sidebar | **Ẩn** (`hidden lg:flex`) — mobile dùng hamburger menu header như cũ |
| Content | Dời | `<main>` + footer offset `lg:ml-60` (chỉ desktop) |
| Khối đáy sidebar | Nội dung | **Trạng thái tài khoản** (điểm/streak / đăng nhập) |
| Active state | Cơ chế | `is_front_page()`/`is_page(slug)` server-side (không JS) |

## Kiến trúc / thành phần

### 1. `template-parts/sidebar-nav.php` (MỚI)
`<aside class="hidden lg:flex flex-col fixed left-0 top-16 bottom-0 w-60 border-r border-card bg-card z-40 overflow-y-auto">`:
- **Nav nhanh** — mảng item `[label, url, active-check, icon]`, loop render `<a>` (icon Tabler + label). Mục đang xem → class active (`bg-brand/10 text-brand`), còn lại `text-secondary hover:text-brand hover:bg-control`. 6 mục:
  | Mục | URL | Active khi | Icon Tabler |
  |-----|-----|-----------|-------------|
  | Trang chủ | `home_url('/')` | `is_front_page()` | home |
  | Nhận định | `/nhan-dinh/` | `is_page('nhan-dinh')` | chart-line |
  | Kết quả | `/ket-qua-bong-da/` | `is_page('ket-qua-bong-da')` | ball-football |
  | Lịch thi đấu | `/lich-thi-dau/` | `is_page('lich-thi-dau')` | calendar |
  | BXH giải | `/bang-xep-hang/` | `is_page('bang-xep-hang')` | trophy |
  | Xếp hạng TV | `/bang-xep-hang-thanh-vien/` | `is_page('bang-xep-hang-thanh-vien')` | medal-2 |
- **Khối tài khoản (đáy, `mt-auto border-t`)**:
  - **Đăng nhập:** `display_name` + hàng `★ <bd_get_points> · 🔥 <bd_streak>` + 2 link Tài khoản (`/tai-khoan/`) & Đăng xuất (`wp_logout_url`).
  - **Khách:** nút "Đăng nhập / Đăng ký" → `/tai-khoan/`.
- Escape đầy đủ (`esc_url`/`esc_html`/`esc_attr`). Điểm/streak đọc `bd_get_points()` + user meta `bd_streak`.

### 2. `header.php` (SỬA nhẹ)
- NGAY SAU `</header>`, TRƯỚC `<main>`: `<?php get_template_part('template-parts/sidebar-nav'); ?>`.
- Đổi `<main class="pt-24 pb-16">` → `<main class="pt-24 pb-16 lg:ml-60">`.
- (Không đụng phần nav/header còn lại.)

### 3. `footer.php` (SỬA nhẹ)
- `<footer class="border-t border-card py-10">` → thêm offset: `<footer class="border-t border-card py-10 lg:ml-60">`.

### 4. Build
- `npm run build:css` (class mới: `w-60`, `lg:ml-60`, `top-16`, `bottom-0`, `border-r`, `z-40`, `overflow-y-auto`, `mt-auto`…). Không cần JS.

## Data flow

Mọi trang: header (giữ) render trên đầu → `sidebar-nav.php` render `<aside>` cố định trái (desktop) với active-state theo trang hiện tại + khối tài khoản (đọc điểm/streak) → `<main lg:ml-60>` chứa template nội dung dời phải. Mobile (`<lg`): `<aside>` ẩn, `ml-60` không áp → content full-width, hamburger menu header lo điều hướng.

## Error handling / edge

- Sidebar chỉ desktop; mobile không đổi hành vi (an toàn).
- Active-state: nếu không khớp mục nào (VD trang single bài viết) → không mục nào sáng (bình thường).
- Khối tài khoản khách vs đăng nhập tách nhánh `is_user_logged_in()`.
- Header search mở rộng (desktop) có thể che nhẹ đỉnh sidebar — chấp nhận (hiếm, search đóng mặc định).
- Footer offset `lg:ml-60` để không bị sidebar (fixed) đè.

## Kiểm thử

- **curl (khách):** `/` → có `<aside`, 6 link nav, `<main` chứa `lg:ml-60`, khối "Đăng nhập / Đăng ký"; 0 lỗi PHP. Mỗi route (`/nhan-dinh/`…) → đúng 1 mục active (`bg-brand/10`/`text-brand` trên đúng link).
- **E2E Playwright:**
  - Desktop (≥1024px): sidebar hiện, content dời phải (main có margin trái), mục đang xem sáng; điều hướng bấm sidebar sang trang khác đúng.
  - Mobile (375px): `<aside>` **ẩn** (không chiếm chỗ), header + hamburger nguyên vẹn, content full-width.
  - Đăng nhập: khối đáy hiện tên + điểm + streak; khách: nút Đăng nhập.
- Build CSS không lỗi; header/footer/template nội dung không vỡ.

## Tiêu chí thành công

- [ ] Header chính giữ nguyên trên đầu; sidebar cố định trái hiện dưới header (desktop).
- [ ] 6 mục nav nhanh + active-state đúng theo trang.
- [ ] Khối tài khoản: đăng nhập (tên/điểm/streak/link) vs khách (CTA đăng nhập).
- [ ] Content + footer dời phải desktop; mobile ẩn sidebar, full-width, header/hamburger nguyên vẹn.
- [ ] Icon Tabler MIT nhúng thẳng; 0 lỗi PHP; escape đầy đủ.

## Ngoài phạm vi

- Off-canvas drawer sidebar cho mobile (mobile dùng header cũ).
- Sửa/di chuyển header chính hay nav header.
- Thu gọn (collapse) sidebar chỉ-icon; lưu trạng thái collapse.
- Gói Pro / upsell nạp tiền (Giai đoạn 2).
- Đổi `.container` sang layout căn trái full-width (giữ container căn giữa hiện tại).
