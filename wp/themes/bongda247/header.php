<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>
    // Chạy trước khi render để không nháy màu khi reload
    (function () {
      var saved = localStorage.getItem('theme');
      var dark = saved ? saved === 'dark'
                       : window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (dark) document.documentElement.classList.add('dark');
    })();
  </script>
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
// Nav giải — đồng bộ 4 giải với trang chủ. Dùng cho cả nav desktop lẫn mobile.
$bd_nav = [
    ['name' => 'Ngoại hạng Anh',  'slug' => 'ngoai-hang-anh'],
    ['name' => 'La Liga',         'slug' => 'la-liga'],
    ['name' => 'Champions League','slug' => 'champions-league'],
    ['name' => 'Chuyển nhượng',   'slug' => 'chuyen-nhuong'],
];
// Trang tĩnh cho mobile menu (khớp footer).
$bd_menu_pages = ['gioi-thieu' => 'Giới thiệu', 'lien-he' => 'Liên hệ'];
?>

<header class="fixed top-0 w-full z-50 header">
  <div class="container mx-auto">
    <nav>
      <div class="flex items-center justify-between h-16">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="flex items-center space-x-2 group">
          <span class="font-hemi text-2xl font-bold uppercase">BONGDA<span class="text-brand">247</span></span>
        </a>

        <ul class="hidden lg:flex items-center space-x-7">
          <li class="group relative">
            <button type="button" class="flex items-center gap-1 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand transition-colors cursor-pointer">
              Giải đấu
              <svg class="w-3.5 h-3.5 transition-transform group-hover:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="invisible opacity-0 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100 transition-opacity absolute left-0 top-full pt-3">
              <ul class="w-52 rounded-xl border border-card bg-card shadow-xl p-2">
                <?php foreach ($bd_nav as $item) :
                    $term = get_term_by('slug', $item['slug'], 'category');
                    if (!$term) continue; ?>
                  <li><a href="<?php echo esc_url(get_term_link($term)); ?>" class="block px-3 py-2 rounded-lg text-sm font-medium text-secondary hover:text-brand hover:bg-control transition-colors"><?php echo esc_html($item['name']); ?></a></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </li>
            <li><a href="<?php echo esc_url(home_url('/nhan-dinh/')); ?>" class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">Nhận định</a></li>
            <li><a href="<?php echo esc_url(home_url('/ket-qua-bong-da/')); ?>" class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">Kết quả</a></li>
            <li><a href="<?php echo esc_url(home_url('/lich-thi-dau/')); ?>" class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">Lịch</a></li>
            <li><a href="<?php echo esc_url(home_url('/bang-xep-hang/')); ?>" class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">BXH</a></li>
        </ul>

        <div class="flex items-center space-x-3">
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
          <button data-search-toggle type="button" aria-label="Tìm kiếm" aria-expanded="false"
                  class="p-2 rounded-full border border-card bg-control cursor-pointer transition-colors hover:text-brand">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
          </button>

          <?php get_template_part('template-parts/theme-toggle'); ?>

          <button data-menu-toggle type="button" aria-label="Menu" aria-expanded="false"
                  class="lg:hidden p-2 rounded-full border border-card bg-control cursor-pointer transition-colors hover:text-brand">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M3 12h18M3 18h18"></path></svg>
          </button>
        </div>
      </div>

      <!-- Ô tìm kiếm (ẩn; icon xổ bằng JS) -->
      <div id="bd-search" class="hidden pb-4">
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="relative">
          <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Tìm bài viết..."
                 class="w-full rounded-lg bg-card border border-card px-4 py-3 pr-11 text-sm focus:outline-none focus:border-brand">
          <button type="submit" aria-label="Tìm" class="absolute right-3 top-1/2 -translate-y-1/2 text-secondary hover:text-brand">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
          </button>
        </form>
      </div>

      <!-- Menu mobile (ẩn; hamburger xổ bằng JS) -->
      <div id="bd-mobile-menu" class="hidden lg:hidden pb-4">
        <ul class="flex flex-col gap-1">
          <?php foreach ($bd_nav as $item) :
              $term = get_term_by('slug', $item['slug'], 'category');
              if (!$term) continue; ?>
            <li><a href="<?php echo esc_url(get_term_link($term)); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand"><?php echo esc_html($item['name']); ?></a></li>
          <?php endforeach; ?>
            <li><a href="<?php echo esc_url(home_url('/bang-xep-hang/')); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">BXH</a></li>
            <li><a href="<?php echo esc_url(home_url('/lich-thi-dau/')); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">Lịch</a></li>
            <li><a href="<?php echo esc_url(home_url('/ket-qua-bong-da/')); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">Kết quả</a></li>
            <li><a href="<?php echo esc_url(home_url('/nhan-dinh/')); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">Nhận định</a></li>
            <?php if (is_user_logged_in()) : ?>
              <li class="border-t border-card mt-2 pt-2"></li>
              <li><a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">Tài khoản</a></li>
              <li><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">Đăng xuất</a></li>
            <?php else : ?>
              <li class="border-t border-card mt-2 pt-2"></li>
              <li><a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">Đăng nhập</a></li>
            <?php endif; ?>
          <?php
          $bd_any_page = false;
          foreach ($bd_menu_pages as $bd_s => $bd_l) { if (get_page_by_path($bd_s)) { $bd_any_page = true; break; } }
          if ($bd_any_page) : ?>
            <li class="border-t border-card mt-2 pt-2"></li>
            <?php foreach ($bd_menu_pages as $bd_slug => $bd_label) :
                $bd_page = get_page_by_path($bd_slug);
                if (!$bd_page) continue; ?>
              <li><a href="<?php echo esc_url(get_permalink($bd_page)); ?>" class="block py-2 text-sm text-secondary hover:text-brand"><?php echo esc_html($bd_label); ?></a></li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>
    </nav>
  </div>
</header>

<main class="pt-24 pb-16">
