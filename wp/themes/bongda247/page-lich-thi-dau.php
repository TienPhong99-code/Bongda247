<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Lịch thi đấu</h1>

  <?php
  $bd_req  = isset($_GET['league']) ? sanitize_key(wp_unslash($_GET['league'])) : '';
  $bd_slug = array_key_exists($bd_req, BD_FD_LEAGUES) ? $bd_req : 'ngoai-hang-anh';
  ?>
  <div class="flex flex-wrap gap-2 mb-8">
    <?php foreach (BD_FD_LEAGUES as $slug => $lg) : ?>
      <a href="<?php echo esc_url(add_query_arg('league', $slug, get_permalink())); ?>"
         class="px-4 py-2 rounded-full text-sm border border-card transition-colors <?php echo $slug === $bd_slug ? 'bg-brand text-white' : 'text-secondary hover:text-brand'; ?>">
        <?php echo esc_html($lg['name']); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php
  $bd_matches = bd_fd_fixtures(bd_fd_code($bd_slug));
  if ($bd_matches) {
      set_query_var('bd_fd_matches', $bd_matches);
      get_template_part('template-parts/fixtures-list');
  } else {
      echo '<p class="text-secondary">Chưa có lịch thi đấu.</p>';
  }
  ?>
</div>

<?php get_footer(); ?>
