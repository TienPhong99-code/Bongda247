# SP2.3 Nút chia sẻ + tích điểm — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 3 nút chia sẻ (Facebook · X · Copy link) trong khối reactions bài viết + cộng 3đ lần chia sẻ đầu/bài (tái dùng AJAX `bd_award sub=share`).

**Architecture:** Chỉ UI + JS — backend `bd_award(sub=share)` + `bd_award_points('share')` (3đ, dedup `bd_share_posts`) đã có & test ở SP2.1. Thêm nút vào `single.php` (trong wrapper `[data-bd-points]` logged-in) + xử lý click trong `src/main.js`.

**Tech Stack:** WordPress (PHP 8.2), Tailwind v4, JS thuần (`npm run build:js`/`build:css`). Verify: curl + Playwright.

**Spec:** `docs/superpowers/specs/2026-07-15-share-earn-design.md`

## Global Constraints

- KHÔNG thêm backend (dùng `bd_award` với `sub:'share'` sẵn có). Cộng 3đ **lần đầu/bài**, dedup server-side; share lại không cộng.
- Nút chia sẻ CHỈ cho user đăng nhập (đặt TRONG `<div data-bd-points>` — chỉ render khi đăng nhập). Khách không có nút.
- Popup `noopener`; `navigator.clipboard` có guard; `.catch` im lặng. Escape `esc_url(get_permalink())`/`esc_attr(get_the_title())`.
- Không phá reactions cũ (nút Like + phát hiện đọc). Bám class theme.

---

### Task 1: Nút chia sẻ (single.php) + xử lý JS

**Files:**
- Modify: `wp/themes/bongda247/single.php` (thêm cụm share trong khối reactions)
- Modify: `wp/themes/bongda247/src/main.js` (xử lý click share)
- Build: `wp/themes/bongda247/dist/main.js` + `dist/main.css`

**Interfaces:**
- Consumes: AJAX `bd_award` (param `sub=share`) + `bdSend`/`setBalance` (SP2.1, trong main.js).

- [ ] **Step 1: `single.php` — thêm cụm chia sẻ.** (a) Đổi class wrapper reactions (dòng có `data-bd-points ... class="mt-8 flex items-center gap-3"`) → thêm `flex-wrap`: `class="mt-8 flex items-center gap-3 flex-wrap"`. (b) NGAY SAU `<span class="text-xs text-secondary">Thích để +1 điểm</span>`, TRƯỚC `</div>` đóng wrapper reactions, thêm:
```php
            <div class="flex items-center gap-2" data-bd-share-url="<?php echo esc_url(get_permalink()); ?>" data-bd-share-title="<?php echo esc_attr(get_the_title()); ?>">
              <span class="text-xs text-secondary">Chia sẻ +3đ:</span>
              <button data-bd-share="fb" type="button" aria-label="Chia sẻ Facebook" class="p-2 rounded-full border border-card text-secondary hover:text-brand hover:border-brand transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 9h3l.5-3H14V4.5c0-.8.3-1.5 1.5-1.5H17V.2C16.7.1 15.7 0 14.6 0 12.2 0 10.5 1.5 10.5 4.2V6H8v3h2.5v9h3.5V9z"/></svg>
              </button>
              <button data-bd-share="x" type="button" aria-label="Chia sẻ X" class="p-2 rounded-full border border-card text-secondary hover:text-brand hover:border-brand transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 1.2h3.5l-7.6 8.7 8.9 11.8h-7l-5.5-7.2-6.3 7.2H1.4l8.1-9.3L1 1.2h7.2l5 6.6 5.7-6.6zm-1.2 18.4h1.9L6.9 3.2H4.8l12.9 16.4z"/></svg>
              </button>
              <button data-bd-share="copy" type="button" aria-label="Copy link" class="p-2 rounded-full border border-card text-secondary hover:text-brand hover:border-brand transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></svg>
              </button>
            </div>
```

- [ ] **Step 2: `src/main.js` — xử lý share.** Trong khối `if (bdPts) { ... }`, NGAY SAU khối `likeBtn` (sau `}` đóng `if (likeBtn)`), thêm:
```js
      // Share
      var shareWrap = bdPts.querySelector('[data-bd-share-url]');
      if (shareWrap) {
        var shareUrl = shareWrap.getAttribute('data-bd-share-url');
        var shareTitle = shareWrap.getAttribute('data-bd-share-title') || '';
        var awardShare = function () {
          bdSend('bd_award', { sub: 'share' }).then(function (res) {
            if (res && res.success) setBalance(res.data.points);
          }).catch(function () {});
        };
        shareWrap.querySelectorAll('[data-bd-share]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var t = btn.getAttribute('data-bd-share');
            if (t === 'fb') {
              window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl), '_blank', 'noopener,width=600,height=500');
            } else if (t === 'x') {
              window.open('https://twitter.com/intent/tweet?url=' + encodeURIComponent(shareUrl) + '&text=' + encodeURIComponent(shareTitle), '_blank', 'noopener,width=600,height=500');
            } else if (t === 'copy') {
              if (navigator.clipboard) { navigator.clipboard.writeText(shareUrl).catch(function () {}); }
              btn.setAttribute('aria-label', 'Đã copy');
            }
            awardShare();
          });
        });
      }
```

- [ ] **Step 3: Build JS + CSS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247 && npm run build:js && npm run build:css`
Expected: build không lỗi.

- [ ] **Step 4: Verify (khách + build)**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
POST=$(./wp/bin/wp post get 103 --field=url 2>/dev/null)
curl -s "$POST" -o /tmp/sh.html -w "HTTP %{http_code}\n"
echo "khách: data-bd-share (KHÔNG có, trong wrapper login): $(grep -c 'data-bd-share' /tmp/sh.html) | lỗi PHP: $(grep -ci 'fatal error\|warning:\|notice:' /tmp/sh.html)"
echo "JS build có share: $(grep -c "sub: 'share'" wp/themes/bongda247/dist/main.js) + facebook: $(grep -c 'facebook.com/sharer' wp/themes/bongda247/dist/main.js)"
```
Expected: HTTP 200; khách `data-bd-share`=0 (nút trong wrapper login); lỗi PHP=0; dist/main.js chứa `sub: 'share'`=1 + `facebook.com/sharer`=1. *(E2E đăng nhập → share → +3đ: controller Playwright sau.)*

- [ ] **Step 5: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/single.php wp/themes/bongda247/src/main.js wp/themes/bongda247/dist/main.js wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): nút chia sẻ FB/X/Copy + cộng 3đ (tái dùng bd_award sub=share)"
```

---

## Sau khi xong

- Controller Playwright E2E: đăng nhập → click share → điểm +3 (badge); click lại → không +3 (dedup). Dọn user test.
- Cập nhật CLAUDE.md (single có nút chia sẻ; share earn 3đ hoàn tất SP2).
- Ledger `.superpowers/sdd/share-earn/progress.md`.
- Finishing gate (bot `npm test` 15/15; curl single 200) → merge + push.
- **Hoàn tất SP2** (đọc/like/comment/share). Còn **SP3 mở khóa dự đoán** là hết Giai đoạn 1.
