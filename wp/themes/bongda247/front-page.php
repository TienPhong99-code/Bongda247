<?php defined('ABSPATH') || exit; get_header(); ?>

<section>
  <div class="container">
    <div class="row">
      <div class="col col-8">
        <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4 mb-6">Tin mới cập nhật</h2>
        <?php get_template_part('template-parts/hot-news-slider'); ?>
      </div>
      <div class="col col-4">
        <?php get_template_part('template-parts/sidebar-slider'); ?>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="container">
    <?php get_template_part('template-parts/match-insights'); ?>
  </div>
</section>

<?php get_footer(); ?>
