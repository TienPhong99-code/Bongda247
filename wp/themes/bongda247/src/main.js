// Khởi tạo 3 Swiper + theme toggle. Swiper nạp từ assets/vendor (biến toàn cục window.Swiper).
(function () {
  "use strict";

  // --- Theme toggle ---
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-theme-toggle]");
    if (!btn) return;
    var isDark = document.documentElement.classList.toggle("dark");
    localStorage.setItem("theme", isDark ? "dark" : "light");
  });

  // --- Search toggle (icon xổ ô nhập) ---
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-search-toggle]");
    if (!btn) return;
    var box = document.getElementById("bd-search");
    if (!box) return;
    box.classList.toggle("hidden");
    // Cập nhật aria-expanded theo trạng thái thật của ô tìm kiếm
    btn.setAttribute("aria-expanded", String(!box.classList.contains("hidden")));
    if (!box.classList.contains("hidden")) {
      var input = box.querySelector('input[type="search"]');
      if (input) input.focus();
    }
  });

  // --- Mobile menu toggle (hamburger) ---
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-menu-toggle]");
    if (!btn) return;
    var menu = document.getElementById("bd-mobile-menu");
    if (menu) {
      menu.classList.toggle("hidden");
      // Cập nhật aria-expanded theo trạng thái thật của mobile menu
      btn.setAttribute("aria-expanded", String(!menu.classList.contains("hidden")));
    }
  });

  // --- Widget số liệu: đổi giải qua AJAX ---
  document.addEventListener("change", function (e) {
    var sel = e.target.closest("[data-fd-league]");
    if (!sel) return;
    var widget = sel.closest("[data-fd-ajax]");
    var body = widget && widget.querySelector("[data-fd-body]");
    if (!widget || !body) return;
    var url = widget.getAttribute("data-fd-ajax") + "?action=bd_fd_widget&league=" + encodeURIComponent(sel.value);
    fetch(url)
      .then(function (r) { return r.text(); })
      .then(function (html) { body.innerHTML = html; })
      .catch(function () {});
  });

  // --- Widget số liệu: đổi tab BXH/Lịch/Kết quả (client) ---
  document.addEventListener("click", function (e) {
    var tab = e.target.closest("[data-fd-tab]");
    if (!tab) return;
    var body = tab.closest("[data-fd-body]");
    if (!body) return;
    var name = tab.getAttribute("data-fd-tab");
    body.querySelectorAll("[data-fd-tab]").forEach(function (b) {
      var active = b === tab;
      b.classList.toggle("bg-brand", active);
      b.classList.toggle("text-on-brand", active);
      b.classList.toggle("text-secondary", !active);
      b.classList.toggle("hover:text-brand", !active); // hover chỉ cho tab KHÔNG active
      b.setAttribute("aria-selected", String(active));
    });
    body.querySelectorAll("[data-fd-panel]").forEach(function (p) {
      p.hidden = p.getAttribute("data-fd-panel") !== name;
    });
  });

  // --- Trang tài khoản: tab Đăng nhập / Đăng ký ---
  document.addEventListener("click", function (e) {
    var btn = e.target.closest ? e.target.closest("[data-bd-auth-btn]") : null;
    if (!btn) return;
    var wrap = btn.closest("[data-bd-auth-tabs]");
    if (!wrap) return;
    var name = btn.getAttribute("data-bd-auth-btn");
    wrap.querySelectorAll("[data-bd-auth-btn]").forEach(function (b) {
      var active = b === btn;
      b.classList.toggle("bg-brand", active);
      b.classList.toggle("text-on-brand", active);
      b.classList.toggle("text-secondary", !active);
      b.classList.toggle("hover:text-brand", !active); // hover chỉ cho tab KHÔNG active (tránh chữ trùng nền)
    });
    wrap.querySelectorAll("[data-bd-auth-panel]").forEach(function (p) {
      p.classList.toggle("hidden", p.getAttribute("data-bd-auth-panel") !== name);
    });
  });

  // --- Swiper ---
  document.addEventListener("DOMContentLoaded", function () {
    // --- Điểm: đọc + like ---
    var bdPts = document.querySelector('[data-bd-points]');
    if (bdPts) {
      var bdAjax = bdPts.getAttribute('data-bd-ajax');
      var bdNonce = bdPts.getAttribute('data-bd-nonce');
      var bdPost = bdPts.getAttribute('data-bd-post');
      var setBalance = function (p) {
        if (typeof p !== 'number') return;
        document.querySelectorAll('[data-bd-points-balance]').forEach(function (b) { b.textContent = p; });
      };
      var bdSend = function (action, extra) {
        var params = { action: action, post_id: bdPost, _wpnonce: bdNonce };
        for (var k in (extra || {})) params[k] = extra[k];
        return fetch(bdAjax, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(params),
        }).then(function (r) { return r.json(); });
      };
      // Đọc: cuộn >=60% VÀ ở lại >=20s → cộng 1 lần
      var readSent = false, maxScroll = 0, enoughTime = false;
      var tryRead = function () {
        if (readSent || !enoughTime || maxScroll < 60) return;
        readSent = true;
        bdSend('bd_award', { sub: 'read' }).then(function (res) {
          if (res && res.success) setBalance(res.data.points);
        }).catch(function () {});
      };
      setTimeout(function () { enoughTime = true; tryRead(); }, 20000);
      window.addEventListener('scroll', function () {
        var h = document.documentElement;
        var pct = (h.scrollTop + window.innerHeight) / h.scrollHeight * 100;
        if (pct > maxScroll) maxScroll = pct;
        tryRead();
      }, { passive: true });
      // Like
      var likeBtn = bdPts.querySelector('[data-bd-like]');
      if (likeBtn) {
        likeBtn.addEventListener('click', function () {
          bdSend('bd_toggle_like').then(function (res) {
            if (!res || !res.success) return;
            var d = res.data;
            likeBtn.setAttribute('aria-pressed', d.liked ? 'true' : 'false');
            likeBtn.classList.toggle('text-brand', d.liked);
            likeBtn.classList.toggle('border-brand', d.liked);
            likeBtn.classList.toggle('text-secondary', !d.liked);
            likeBtn.classList.toggle('border-card', !d.liked);
            var c = likeBtn.querySelector('[data-bd-like-count]');
            if (c) c.textContent = d.count;
            setBalance(d.points);
          }).catch(function () {});
        });
      }

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
    }

    // --- Mở khóa dự đoán ---
    document.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-bd-unlock]') : null;
      if (!btn) return;
      var ajax = btn.getAttribute('data-bd-ajax');
      var nonce = btn.getAttribute('data-bd-nonce');
      var iid = btn.getAttribute('data-bd-insight');
      btn.disabled = true;
      fetch(ajax, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'bd_unlock', insight_id: iid, _wpnonce: nonce }),
      }).then(function (r) { return r.json(); }).then(function (res) {
        if (res && res.success) {
          var gate = btn.closest('[data-bd-pred-gate]');
          if (gate) {
            // Dựng badge bằng createElement + textContent (KHÔNG innerHTML cho giá trị) — chống XSS.
            var badge = document.createElement('div');
            badge.className = 'inline-block mt-auto w-fit ml-auto text-sm transition-all p-2 px-4 rounded-full font-hemi bg-prediction';
            badge.textContent = res.data.prediction;
            gate.innerHTML = '';
            gate.appendChild(badge);
          }
          document.querySelectorAll('[data-bd-points-balance]').forEach(function (b) { b.textContent = res.data.points; });
        } else {
          btn.textContent = 'Không đủ điểm';
          btn.disabled = false;
        }
      }).catch(function () { btn.disabled = false; });
    });

    // --- Điểm danh ---
    document.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-bd-checkin]') : null;
      if (!btn || btn.disabled) return;
      var box = btn.closest('[data-bd-checkin-box]');
      if (!box) return;
      btn.disabled = true;
      fetch(box.getAttribute('data-bd-ajax'), {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'bd_checkin', _wpnonce: box.getAttribute('data-bd-nonce') }),
      }).then(function (r) { return r.json(); }).then(function (res) {
        if (res && res.success) {
          var d = res.data;
          if (typeof d.points === 'number') {
            document.querySelectorAll('[data-bd-points-balance]').forEach(function (b) { b.textContent = d.points; });
          }
          var st = box.querySelector('[data-bd-streak]');
          if (st && typeof d.streak === 'number') st.textContent = d.streak;
          btn.textContent = 'Đã điểm danh hôm nay ✓';
          btn.className = 'rounded-full px-4 py-1.5 text-sm font-medium border border-card text-secondary cursor-default';
        } else {
          btn.disabled = false;
        }
      }).catch(function () { btn.disabled = false; });
    });

    if (typeof Swiper === "undefined") return;

    if (document.querySelector(".hotSwiper")) {
      new Swiper(".hotSwiper", {
        autoplay: { delay: 5000, disableOnInteraction: false },
        pagination: { el: ".swiper-pagination", clickable: true },
        speed: 800,
      });
    }

    if (document.querySelector(".sidebarSwiper")) {
      new Swiper(".sidebarSwiper", {
        direction: "vertical",
        slidesPerView: "auto",
        spaceBetween: 10,
        mousewheel: true,
        autoplay: { delay: 5000, disableOnInteraction: false },
      });
    }

    if (document.querySelector(".insightSwiper")) {
      new Swiper(".insightSwiper", {
        slidesPerView: 1,
        spaceBetween: 12,
        navigation: { nextEl: ".insight-next", prevEl: ".insight-prev" },
        breakpoints: {
          640: { slidesPerView: 2, spaceBetween: 12 },
          768: { slidesPerView: 3, spaceBetween: 12 },
        },
      });
    }

    if (document.querySelector(".relatedSwiper")) {
      new Swiper(".relatedSwiper", {
        slidesPerView: 1.2,
        spaceBetween: 16,
        navigation: { nextEl: ".related-next", prevEl: ".related-prev" },
        breakpoints: {
          640: { slidesPerView: 2, spaceBetween: 16 },
          768: { slidesPerView: 3, spaceBetween: 16 },
          1024: { slidesPerView: 4, spaceBetween: 16 },
        },
      });
    }
  });
})();
