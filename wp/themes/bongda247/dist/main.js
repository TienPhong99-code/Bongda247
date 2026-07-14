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
      b.classList.toggle("text-white", active);
      b.classList.toggle("text-secondary", !active);
      b.setAttribute("aria-selected", String(active));
    });
    body.querySelectorAll("[data-fd-panel]").forEach(function (p) {
      p.hidden = p.getAttribute("data-fd-panel") !== name;
    });
  });

  // --- Swiper ---
  document.addEventListener("DOMContentLoaded", function () {
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
  });
})();
