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

        <ul class="hidden lg:flex space-x-8">
          <?php foreach ($bd_nav as $item) :
              $term = get_term_by('slug', $item['slug'], 'category');
              if (!$term) continue; ?>
            <li>
              <a href="<?php echo esc_url(get_term_link($term)); ?>"
                 class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">
                <?php echo esc_html($item['name']); ?>
              </a>
            </li>
          <?php endforeach; ?>
            <li><a href="<?php echo esc_url(home_url('/bang-xep-hang/')); ?>" class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">BXH</a></li>
            <li><a href="<?php echo esc_url(home_url('/lich-thi-dau/')); ?>" class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">Lịch</a></li>
        </ul>

        <div class="flex items-center space-x-3">
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
