# SP2.3: Nút chia sẻ + tích điểm — Design

> Sub-project cuối của SP2. Thêm nút chia sẻ (Facebook · X · Copy link) vào bài viết + cộng **3 điểm** cho lần chia sẻ đầu tiên/bài. Tái dùng AJAX `bd_award sub=share` đã có (SP2.1).

## Mục tiêu

- Nguồn earn thứ 4 (share), hoàn tất bộ tương tác đọc/like/comment/share.
- Tăng khả năng lan tỏa bài viết.

## Bối cảnh & ràng buộc

- SP2.1 đã có: AJAX `bd_award` **đã nhận `sub ∈ {read, share}`** (whitelist) + `bd_award_points($uid,'share',$post_id)` = **3đ**, dedup key `bd_share_posts`. Đã test.
- `single.php` có khối reactions (wrapper `<div data-bd-points data-bd-ajax data-bd-nonce data-bd-post>` chỉ render khi đăng nhập, chứa nút Like). `src/main.js` có khối `[data-bd-points]` (đọc + like) + helper `bdSend(action, extra)` + `setBalance(p)`.
- Theme: nút tròn `rounded-full border border-card px-.. py-..`, `text-secondary`/`text-brand`.

## Quyết định thiết kế (đã chốt)

| # | Quyết định | Chọn |
|---|-----------|------|
| Kênh | Chia sẻ đâu | **Facebook · X (Twitter) · Copy link** |
| Ai thấy nút | Quyền | **Chỉ user đăng nhập** (trong wrapper `[data-bd-points]`, nhất quán Like) |
| Điểm | Cộng khi nào | **3đ lần chia sẻ đầu/bài** (dedup `bd_share_posts`); share lại không cộng |

## Kiến trúc / thành phần

### 1. `single.php` (SỬA) — nút chia sẻ trong khối reactions
- Trong `<div data-bd-points ...>` (sau nút Like), thêm cụm chia sẻ:
  - Wrapper `<div data-bd-share-url="<?php echo esc_url(get_permalink()); ?>" data-bd-share-title="<?php echo esc_attr(get_the_title()); ?>">` + nhãn "Chia sẻ +3đ".
  - 3 `<button data-bd-share="fb|x|copy" type="button" aria-label="...">` với icon (SVG). Bám class theme.

### 2. `src/main.js` (SỬA → build) — xử lý share
- Trong khối `[data-bd-points]` sẵn có: nếu có `[data-bd-share-url]` → gắn click cho từng `[data-bd-share]`:
  - `fb` → `window.open('https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(url), '_blank', 'noopener,width=600,height=500')`.
  - `x` → `window.open('https://twitter.com/intent/tweet?url='+encodeURIComponent(url)+'&text='+encodeURIComponent(title), '_blank', 'noopener,width=600,height=500')`.
  - `copy` → `navigator.clipboard.writeText(url)` (guard tồn tại) + đổi nhãn nút "✓".
  - Sau mọi kênh: `bdSend('bd_award', {sub:'share'})` → `setBalance(res.data.points)`. `.catch` im lặng.

## Data flow

User đăng nhập bấm nút share → mở kênh/copy + AJAX `bd_award(sub=share)` → `bd_award_points('share')` (dedup 1 lần/bài) → +3đ → badge cập nhật. Không reload.

## Error handling

- Khách không thấy nút (wrapper chỉ render khi đăng nhập).
- AJAX thiếu nonce/không đăng nhập → error (không xảy ra vì nút chỉ hiện khi đăng nhập); `.catch` im lặng.
- Dedup server-side → share lại cùng bài không cộng.
- `navigator.clipboard` không có (http/khác) → guard, không vỡ.
- Popup `noopener` (bảo mật). Escape `esc_url`/`esc_attr` cho url/title.

## Kiểm thử

- Không backend test mới (`bd_award sub=share` + `bd_award_points('share')` đã test SP2.1).
- Verify: đăng nhập → single có 3 nút `data-bd-share`; `dist/main.js` chứa `sub: 'share'` + `facebook.com/sharer`; khách không có nút; 0 lỗi PHP; build OK.
- E2E Playwright: đăng nhập → gọi `bd_award(sub=share)` (qua click hoặc evaluate) → điểm +3; gọi lại → không +3 (dedup). Dọn user.

## Tiêu chí thành công

- [ ] Bài viết (user đăng nhập) có 3 nút chia sẻ FB/X/Copy; khách không có.
- [ ] Bấm share → mở kênh/copy + cộng 3đ lần đầu/bài; share lại không cộng (dedup); badge cập nhật.
- [ ] Không backend mới; escape đầy đủ; không phá reactions cũ (like/đọc).

## Ngoài phạm vi

- Nút share cho khách (reach); Zalo/Telegram; verify share thật; SP3 mở khóa dự đoán; nạp tiền.
