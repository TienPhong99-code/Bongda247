<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Bảng xếp hạng</h1>

  <?php
  $bd_req  = isset($_GET['league']) ? sanitize_key(wp_unslash($_GET['league'])) : '';
  $bd_slug = array_key_exists($bd_req, BD_FD_LEAGUES) ? $bd_req : 'ngoai-hang-anh';
  ?>
  <div class="flex flex-wrap gap-2 mb-8">
    <?php foreach (BD_FD_LEAGUES as $slug => $lg) : ?>
      <a href="<?php echo esc_url(add_query_arg('league', $slug, get_permalink())); ?>"
         class="px-4 py-2 rounded-full text-sm border border-card transition-colors <?php echo $slug === $bd_slug ? 'bg-brand text-on-brand' : 'text-secondary hover:text-brand'; ?>">
        <?php echo esc_html($lg['name']); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php
  $bd_rows = bd_fd_standings(bd_fd_code($bd_slug));
  if ($bd_rows) {
      set_query_var('bd_fd_rows', $bd_rows);
      get_template_part('template-parts/standings-table');
  } else {
      echo '<p class="text-secondary">Chưa có dữ liệu bảng xếp hạng.</p>';
  }
  ?>
</div>

<?php get_footer(); ?>
