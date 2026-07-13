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
