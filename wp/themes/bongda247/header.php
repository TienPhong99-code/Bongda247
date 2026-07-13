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

<header class="fixed top-0 w-full z-50 header">
  <div class="container mx-auto">
    <nav>
      <div class="flex items-center justify-between h-16">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="flex items-center space-x-2 group">
          <span class="font-hemi text-2xl font-bold uppercase">
            BONGDA<span class="text-brand">247</span>
          </span>
        </a>

        <ul class="hidden lg:flex space-x-8">
          <?php
          $nav = [
              ['name' => 'Ngoại hạng Anh', 'slug' => 'ngoai-hang-anh'],
              ['name' => 'Chuyển nhượng',  'slug' => 'chuyen-nhuong'],
              ['name' => 'La Liga',        'slug' => 'la-liga'],
          ];
          foreach ($nav as $item) :
              $term = get_term_by('slug', $item['slug'], 'category');
              if (!$term) continue;
          ?>
            <li>
              <a href="<?php echo esc_url(get_term_link($term)); ?>"
                 class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">
                <?php echo esc_html($item['name']); ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="flex items-center space-x-4">
          <?php get_template_part('template-parts/theme-toggle'); ?>
        </div>
      </div>
    </nav>
  </div>
</header>

<main class="pt-24 pb-16">
